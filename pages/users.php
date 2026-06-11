<?php
$user = current_user();
$message = '';
$error = '';

$creatableRoles = get_creatable_roles($user['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && can_manage_users($user['role'])) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? 'USER';
        $unit = trim($_POST['unit'] ?? '');
        $password = $_POST['password'] ?? 'password';

        if (!$name || !$email || !$unit) {
            $error = 'Nama, email, dan unit wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } elseif (!can_create_user_role($user['role'], $role)) {
            $error = 'Role kamu tidak boleh membuat user dengan role tersebut.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role, unit, status, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())
                ");
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $unit, $user['id']]);
                $newUserId = (int)$pdo->lastInsertId();
                log_activity($pdo, $user['id'], 'User dibuat', $name . ' (' . $role . ')');
                notify_user($pdo, $newUserId, 'Akun dibuat', 'Akun ITI Project Manager kamu sudah dibuat.');
                $message = 'User berhasil dibuat.';
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) {
                    $error = 'Email sudah digunakan.';
                } else {
                    $error = 'Gagal membuat user: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'update_user') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $role = $_POST['role'] ?? 'USER';
        $unit = trim($_POST['unit'] ?? '');
        $status = $_POST['status'] ?? 'active';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if (!$target || !can_manage_target_user($user, $target)) {
            $error = 'Kamu tidak boleh mengubah user ini.';
        } elseif (!$name || !$unit) {
            $error = 'Nama dan unit wajib diisi.';
        } elseif (!in_array($status, ['active','inactive'], true)) {
            $error = 'Status tidak valid.';
        } elseif ($target['role'] === 'SUPERADMIN' && $role !== 'SUPERADMIN' && $user['role'] !== 'SUPERADMIN') {
            $error = 'Hanya SUPERADMIN yang boleh mengubah role SUPERADMIN.';
        } elseif ($user['role'] !== 'SUPERADMIN' && !can_create_user_role($user['role'], $role)) {
            $error = 'Role kamu tidak boleh mengubah user ke role tersebut.';
        } else {
            [$okRole, $roleReason] = user_can_change_role($pdo, $target, $role);
            [$okDeactivate, $deactivateReason] = $status === 'inactive' && $target['status'] !== 'inactive'
                ? user_can_be_deactivated($pdo, $target)
                : [true, ''];

            if (!$okRole) {
                $error = $roleReason;
            } elseif (!$okDeactivate) {
                $error = $deactivateReason;
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET name = ?, role = ?, unit = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $role, $unit, $status, $targetId]);
                log_activity($pdo, $user['id'], 'User diperbarui', $target['email'] . ' → ' . $role . ' / ' . $status);
                notify_user($pdo, $targetId, 'Profil diperbarui', 'Data profil atau role akun kamu diperbarui.');
                $message = 'User berhasil diperbarui.';
            }
        }
    }

    if ($action === 'reset_password') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newPassword = $_POST['new_password'] ?? 'password';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if (!$target || !can_manage_target_user($user, $target)) {
            $error = 'Kamu tidak boleh reset password user ini.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password baru minimal 6 karakter.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
            log_activity($pdo, $user['id'], 'Password user direset', $target['email']);
            notify_user($pdo, $targetId, 'Password direset', 'Password akun kamu telah direset oleh pengelola.');
            $message = 'Password user berhasil direset.';
        }
    }

    if ($action === 'delete_user') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$targetId]);
        $target = $stmt->fetch();

        if (!$target || !can_manage_target_user($user, $target)) {
            $error = 'Kamu tidak boleh menghapus user ini.';
        } else {
            [$canDelete, $deleteReason] = user_can_be_deactivated($pdo, $target);
            if (!$canDelete) {
                $error = $deleteReason;
            } else {
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive', deleted_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmt->execute([$targetId]);
                log_activity($pdo, $user['id'], 'User dihapus', $target['email'] . ' (' . $target['role'] . ')');
                $message = 'User berhasil dihapus dengan aman.';
            }
        }
    }
}

$stmt = $pdo->query("
    SELECT u.id, u.name, u.email, u.role, u.unit, u.status, u.created_at, u.created_by,
        creator.name AS creator_name,
        (SELECT COUNT(*) FROM projects p WHERE p.owner_id = u.id AND p.deleted_at IS NULL AND p.status <> 'archived') AS owned_projects,
        (SELECT COUNT(*) FROM task_assignees ta JOIN tasks t ON t.id = ta.task_id WHERE ta.user_id = u.id AND t.deleted_at IS NULL AND t.status IN ('open','submitted','rejected')) AS active_tasks,
        (SELECT COUNT(*) FROM users child WHERE child.created_by = u.id AND child.deleted_at IS NULL AND child.status = 'active') AS subordinate_count
    FROM users u
    LEFT JOIN users creator ON creator.id = u.created_by
    WHERE u.deleted_at IS NULL
    ORDER BY FIELD(u.role,'SUPERADMIN','ADMIN','MODERATOR','USER'), u.name ASC
");
$users = $stmt->fetchAll();

$visible = array_values(array_filter($users, function($u) use ($user) {
    if ($user['role'] === 'SUPERADMIN') return true;
    if ((int)$u['id'] === (int)$user['id']) return true;
    return role_rank($u['role']) < role_rank($user['role']);
}));

$roleOptionsForCreate = $creatableRoles;
$search = trim($_GET['q'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$unitFilter = $_GET['unit'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$requestedPerPage = (int)($_GET['per_page'] ?? 10);
$perPage = in_array($requestedPerPage, [5,10,20,50], true) ? $requestedPerPage : 10;
$units = array_values(array_unique(array_filter(array_column($visible, 'unit'))));
sort($units);
$totalVisible = count($visible);
$activeUsers = count(array_filter($visible, fn($u) => $u['status'] === 'active'));
$inactiveUsers = count(array_filter($visible, fn($u) => $u['status'] === 'inactive'));
$newUsers = count(array_filter($visible, fn($u) => strtotime($u['created_at']) >= strtotime('-30 days')));
$filteredUsers = array_values(array_filter($visible, function($u) use ($search,$roleFilter,$unitFilter,$statusFilter) {
    $haystack = strtolower($u['name'].' '.$u['email'].' '.$u['unit'].' '.$u['role']);
    return (!$search || strpos($haystack, strtolower($search)) !== false)
        && (!$roleFilter || $u['role'] === $roleFilter)
        && (!$unitFilter || $u['unit'] === $unitFilter)
        && (!$statusFilter || $u['status'] === $statusFilter);
}));
$filteredCount = count($filteredUsers);
$totalPages = max(1, (int)ceil($filteredCount / $perPage));
$currentPage = min(max(1, (int)($_GET['p'] ?? 1)), $totalPages);
$offset = ($currentPage - 1) * $perPage;
$pageUsers = array_slice($filteredUsers, $offset, $perPage);
$usersUrl = function($changes = []) use ($search,$roleFilter,$unitFilter,$statusFilter,$perPage) {
    return 'index.php?' . http_build_query(array_merge(['page'=>'users','q'=>$search,'role'=>$roleFilter,'unit'=>$unitFilter,'status'=>$statusFilter,'per_page'=>$perPage], $changes));
};
?>
<div class="users-management-page">
 <header class="users-management-header"><div><p>Dashboard / User / User Management</p><h2>User Management</h2><span>Kelola pengguna, role, dan akses sistem.</span></div><div><?php if($roleOptionsForCreate): ?><button class="open-user-create" type="button">+ Tambah User Baru</button><?php endif; ?></div></header>
 <?php if($message): ?><div class="user-management-alert success"><?= e($message) ?></div><?php endif; ?><?php if($error): ?><div class="user-management-alert error"><?= e($error) ?></div><?php endif; ?>
 <?php $userKpis=[['Total User',$totalVisible,'blue'],['Active User',$activeUsers,'green'],['New User',$newUsers,'amber'],['Inactive User',$inactiveUsers,'purple'],['Blocked User',0,'red']]; ?>
 <section class="user-management-kpis"><?php foreach($userKpis as [$label,$value,$tone]): ?><article class="user-management-kpi <?= e($tone) ?>"><i><?= e(strtoupper(substr($label,0,1))) ?></i><div><small><?= e($label) ?></small><b><?= e($value) ?></b><span><?= $label==='Blocked User'?'Belum tersedia di sistem':'Data pengguna saat ini' ?></span></div></article><?php endforeach; ?></section>
 <section class="user-management-filter"><form method="get"><input type="hidden" name="page" value="users"><label><span>⌕</span><input name="q" value="<?= e($search) ?>" placeholder="Cari nama, email, atau unit..."></label><select name="role"><option value="">Semua Role</option><?php foreach(['SUPERADMIN','ADMIN','MODERATOR','USER'] as $role): ?><option value="<?= e($role) ?>" <?= $roleFilter===$role?'selected':'' ?>><?= e($role) ?></option><?php endforeach; ?></select><select name="unit"><option value="">Semua Department</option><?php foreach($units as $unit): ?><option value="<?= e($unit) ?>" <?= $unitFilter===$unit?'selected':'' ?>><?= e($unit) ?></option><?php endforeach; ?></select><select name="status"><option value="">Semua Status</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option></select><button>Filter</button><a href="index.php?page=users">Reset</a></form></section>
 <section class="user-management-table"><div class="data-table-scroll"><table><thead><tr><th>User</th><th>Role</th><th>Department</th><th>Status</th><th>Project</th><th>Task Aktif</th><th>Dibuat Pada</th><th>Aksi</th></tr></thead><tbody><?php foreach($pageUsers as $u): $canEdit=can_manage_target_user($user,$u); ?><tr class="user-management-row"><td><span class="user-management-person"><i><?= e(strtoupper(substr($u['name'],0,1))) ?></i><span><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?></small></span></span></td><td><span class="user-role-badge role-<?= e(strtolower($u['role'])) ?>"><?= e($u['role']) ?></span></td><td><?= e($u['unit']) ?></td><td><span class="user-status-badge <?= e($u['status']) ?>"><?= e(ucfirst($u['status'])) ?></span></td><td><b><?= e($u['owned_projects']) ?></b><small class="user-table-subcopy"><?= e($u['subordinate_count']) ?> bawahan</small></td><td><b><?= e($u['active_tasks']) ?></b></td><td><?= e(date('d M Y',strtotime($u['created_at']))) ?></td><td><?php if($canEdit): ?><span class="user-action-buttons"><button class="user-edit-button" type="button" data-user-edit="<?= e($u['id']) ?>">Edit</button><button class="user-delete-button" type="button" data-delete-id="<?= e($u['id']) ?>" data-delete-name="<?= e($u['name']) ?>" data-delete-email="<?= e($u['email']) ?>" data-delete-role="<?= e($u['role']) ?>">Hapus</button></span><?php else: ?><span class="user-action-disabled">-</span><?php endif; ?></td></tr>
 <?php if($canEdit): ?><tr id="edit-user-<?= e($u['id']) ?>" class="user-edit-row" hidden><td colspan="8"><div class="user-form-modal"><div class="user-form-shell"><header class="user-form-header"><div><p>Dashboard / User / Edit User</p><h3>Edit User</h3><span>Ubah informasi pengguna dan akses akun.</span></div><button class="close-user-edit" data-user-edit="<?= e($u['id']) ?>" type="button">Kembali ke User</button></header><div class="user-form-layout"><form method="post" class="user-form-main user-summary-source"><?= csrf_field() ?><input type="hidden" name="action" value="update_user"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><section><h4>Informasi Pribadi</h4><div class="user-form-grid"><label>Nama Lengkap <b>*</b><input name="name" value="<?= e($u['name']) ?>" required></label><label>Email<input value="<?= e($u['email']) ?>" disabled></label></div></section><section><h4>Informasi Pekerjaan</h4><div class="user-form-grid"><?php $allowedRoles=$user['role']==='SUPERADMIN'?['SUPERADMIN','ADMIN','MODERATOR','USER']:get_creatable_roles($user['role']); ?><label>Role <b>*</b><select name="role" required><?php foreach($allowedRoles as $role): ?><option value="<?= e($role) ?>" <?= $u['role']===$role?'selected':'' ?>><?= e($role) ?></option><?php endforeach; ?></select></label><label>Unit / Department <b>*</b><input name="unit" value="<?= e($u['unit']) ?>" required></label></div></section><section><h4>Status & Akses</h4><div class="user-form-grid"><label>Status Akun <b>*</b><select name="status"><option value="active" <?= $u['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $u['status']==='inactive'?'selected':'' ?>>Inactive</option></select></label><div class="user-form-info">Perubahan role dan status mengikuti hierarchy serta guard kepemilikan project.</div></div></section><button class="user-form-save">Simpan Perubahan</button></form><aside class="user-form-aside"><section><h4>Ringkasan</h4><dl><div><dt>Nama</dt><dd data-summary="name"><?= e($u['name']) ?></dd></div><div><dt>Email</dt><dd><?= e($u['email']) ?></dd></div><div><dt>Role</dt><dd data-summary="role"><?= e($u['role']) ?></dd></div><div><dt>Department</dt><dd data-summary="unit"><?= e($u['unit']) ?></dd></div><div><dt>Status</dt><dd data-summary="status"><?= e(ucfirst($u['status'])) ?></dd></div></dl></section><section><h4>Keamanan & Akses</h4><form method="post" class="user-password-form"><?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= e($u['id']) ?>"><label>Password Baru<input name="new_password" value="password" required></label><button>Reset Password</button></form><p>Password minimal 6 karakter. User akan menerima notifikasi setelah password direset.</p></section><section><h4>Aktivitas Akun</h4><p>Dibuat <?= e(date('d M Y, H:i',strtotime($u['created_at']))) ?></p><p>Dibuat oleh <?= e($u['creator_name'] ?: '-') ?></p><p><?= e($u['owned_projects']) ?> project aktif dan <?= e($u['active_tasks']) ?> task aktif.</p></section></aside></div></div></div></td></tr><?php endif; ?><?php endforeach; ?></tbody></table></div>
 <?php if(!$pageUsers): ?><div class="user-management-empty"><h3>Data Tidak Ditemukan</h3><p>Tidak ada user yang cocok dengan filter atau pencarian saat ini.</p><a href="<?= e(app_url('index.php?page=users')) ?>">Reset Filter</a><?php if($roleOptionsForCreate):?><a href="<?= e(app_url('index.php?page=users&create=1')) ?>">Tambah Data Baru</a><?php endif;?></div><?php endif; ?><footer><p>Menampilkan <?= $filteredCount?e($offset+1):0 ?> - <?= e(min($offset+$perPage,$filteredCount)) ?> dari <?= e($filteredCount) ?> user</p><nav><?php for($n=max(1,$currentPage-2);$n<=min($totalPages,$currentPage+2);$n++): ?><a class="<?= $n===$currentPage?'active':'' ?>" href="<?= e($usersUrl(['p'=>$n])) ?>"><?= e($n) ?></a><?php endfor; ?></nav><form method="get"><input type="hidden" name="page" value="users"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><input type="hidden" name="unit" value="<?= e($unitFilter) ?>"><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><select name="per_page" onchange="this.form.submit()"><?php foreach([5,10,20,50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage===$size?'selected':'' ?>><?= e($size) ?> / halaman</option><?php endforeach; ?></select></form></footer></section>
</div>
<div class="user-delete-modal fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm">
 <form method="post" class="user-delete-dialog"><?= csrf_field() ?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" data-delete-user-id>
  <button class="user-delete-close" type="button" aria-label="Tutup">×</button><div class="user-delete-icon">⌫</div>
  <h3>Hapus User Ini?</h3><p>Anda akan menghapus user berikut secara permanen dari daftar aktif.<br>Tindakan ini tidak dapat dibatalkan melalui aplikasi.</p>
  <div class="user-delete-person"><i data-delete-initial>-</i><span><b data-delete-name>-</b><small data-delete-email>-</small></span><em data-delete-role>-</em></div>
  <div class="user-delete-warning"><b>!</b><span>Data terkait seperti project, task, komentar, notifikasi, dan aktivitas tidak ikut terhapus. Penghapusan akan ditolak jika user masih memiliki tanggung jawab aktif.</span></div>
  <footer><button class="user-delete-cancel" type="button">Batal</button><button class="user-delete-confirm" type="submit">Ya, Hapus User</button></footer>
 </form>
</div>
<?php if($roleOptionsForCreate): ?><div class="user-create-modal fixed inset-0 z-50 <?= $error&&($_POST['action']??'')==='create_user'?'flex':'hidden' ?> items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"><div class="user-form-shell"><header class="user-form-header"><div><p>Dashboard / User / Create User</p><h3>Create User</h3><span>Tambah pengguna baru dan atur akses awal.</span></div><button class="close-user-create" type="button">Kembali ke User</button></header><div class="user-form-layout"><form method="post" class="user-form-main user-summary-source"><?= csrf_field() ?><input type="hidden" name="action" value="create_user"><section><h4>Informasi Pribadi</h4><div class="user-form-grid"><label>Nama Lengkap <b>*</b><input name="name" required placeholder="Nama lengkap pengguna"></label><label>Email <b>*</b><input name="email" type="email" required placeholder="nama@iti.ac.id"></label></div></section><section><h4>Informasi Pekerjaan</h4><div class="user-form-grid"><label>Role <b>*</b><select name="role"><?php foreach($roleOptionsForCreate as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?></select></label><label>Unit / Department <b>*</b><input name="unit" required placeholder="Unit kerja"></label></div></section><section><h4>Keamanan & Akses</h4><div class="user-form-grid"><label>Password Awal <b>*</b><input name="password" value="password" required></label><div class="user-form-info">Akun baru dibuat dengan status Active. Password minimal 6 karakter.</div></div></section><button class="user-form-save">Simpan User</button></form><aside class="user-form-aside"><section><h4>Status Akun</h4><p class="user-form-active">Active</p><small>Pengguna dapat login dan mengakses sistem setelah dibuat.</small></section><section><h4>Ringkasan</h4><dl><div><dt>Nama</dt><dd data-summary="name">-</dd></div><div><dt>Email</dt><dd data-summary="email">-</dd></div><div><dt>Role</dt><dd data-summary="role"><?= e($roleOptionsForCreate[0] ?? '-') ?></dd></div><div><dt>Department</dt><dd data-summary="unit">-</dd></div><div><dt>Status</dt><dd>Active</dd></div></dl></section><section><h4>Batasan Akses</h4><p>Role yang tersedia mengikuti hierarchy akun Anda: <?= e(role_label($user['role'])) ?>.</p><p>Pengaturan permission rinci akan tersedia pada Screen 17.</p></section></aside></div></div></div><?php endif; ?>
<script>(()=>{const modal=document.querySelector('.user-create-modal');document.querySelectorAll('.open-user-create').forEach(b=>b.addEventListener('click',()=>{modal?.classList.remove('hidden');modal?.classList.add('flex')}));document.querySelectorAll('.close-user-create').forEach(b=>b.addEventListener('click',()=>{modal?.classList.add('hidden');modal?.classList.remove('flex')}));document.querySelectorAll('[data-user-edit]').forEach(b=>b.addEventListener('click',()=>{const row=document.getElementById('edit-user-'+b.dataset.userEdit);if(row)row.hidden=!row.hidden}));document.querySelectorAll('.user-summary-source').forEach(form=>{const sync=()=>form.closest('.user-form-layout')?.querySelectorAll('[data-summary]').forEach(out=>{const input=form.elements[out.dataset.summary];if(input)out.textContent=input.value||'-'});form.addEventListener('input',sync);form.addEventListener('change',sync);sync()});if(new URLSearchParams(location.search).get('create')==='1')document.querySelector('.open-user-create')?.click();const deleteModal=document.querySelector('.user-delete-modal'),closeDelete=()=>{deleteModal?.classList.add('hidden');deleteModal?.classList.remove('flex')};document.querySelectorAll('.user-delete-close,.user-delete-cancel').forEach(b=>b.addEventListener('click',closeDelete));deleteModal?.addEventListener('click',e=>{if(e.target===deleteModal)closeDelete()});document.querySelectorAll('[data-delete-id]').forEach(b=>b.addEventListener('click',()=>{deleteModal.querySelector('[data-delete-user-id]').value=b.dataset.deleteId;deleteModal.querySelector('[data-delete-name]').textContent=b.dataset.deleteName;deleteModal.querySelector('[data-delete-email]').textContent=b.dataset.deleteEmail;deleteModal.querySelector('[data-delete-role]').textContent=b.dataset.deleteRole;deleteModal.querySelector('[data-delete-initial]').textContent=(b.dataset.deleteName||'?').slice(0,2).toUpperCase();deleteModal.classList.remove('hidden');deleteModal.classList.add('flex')}));})();</script>
