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
?>

<div class="mb-6">
    <p class="text-sm font-black uppercase tracking-wide text-orange-600">Pengguna</p>
    <h2 class="text-3xl font-black">Manajemen Pengguna</h2>
    <p class="text-slate-500">Phase 1.4: create, update, reset password, dan guard role hierarchy dasar.</p>
</div>

<?php if ($message): ?>
    <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-700"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700"><?= e($error) ?></div>
<?php endif; ?>

<?php if (can_manage_users($user['role']) && $roleOptionsForCreate): ?>
<section class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="mb-4 text-lg font-black">Tambah Pengguna</h3>
    <form method="post" class="grid gap-3 lg:grid-cols-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_user">
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="name" placeholder="Nama" required>
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="email" type="email" placeholder="Email" required>
        <select class="rounded-xl border border-slate-200 px-4 py-3" name="role" required>
            <?php foreach ($roleOptionsForCreate as $role): ?>
                <option value="<?= e($role) ?>"><?= e($role) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="unit" placeholder="Unit kerja" required>
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="password" placeholder="Password awal" value="password" required>
        <button class="rounded-xl bg-orange-600 px-4 py-3 font-black text-white">Tambah User</button>
    </form>
</section>
<?php endif; ?>

<section class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm iti-scrollbar">
    <div class="grid grid-cols-8 gap-3 bg-slate-50 px-5 py-3 text-xs font-black uppercase text-slate-500">
        <span class="col-span-2">Pengguna</span>
        <span>Role</span>
        <span>Unit</span>
        <span>Status</span>
        <span>Ownership</span>
        <span>Task Aktif</span>
        <span>Aksi</span>
    </div>
    <?php foreach ($visible as $u): ?>
        <?php $canEdit = can_manage_target_user($user, $u); ?>
        <div class="grid grid-cols-8 gap-3 border-t border-slate-100 px-5 py-4 text-sm">
            <span class="col-span-2">
                <b><?= e($u['name']) ?></b>
                <small class="block text-slate-500"><?= e($u['email']) ?></small>
                <small class="block text-slate-400">Dibuat oleh: <?= e($u['creator_name'] ?: '-') ?></small>
            </span>
            <span><?= e($u['role']) ?></span>
            <span><?= e($u['unit']) ?></span>
            <span><?= status_badge($u['status']) ?></span>
            <span><?= e($u['owned_projects']) ?> project<br><small class="text-slate-400"><?= e($u['subordinate_count']) ?> bawahan</small></span>
            <span><?= e($u['active_tasks']) ?></span>
            <span>
                <?php if ($canEdit): ?>
                    <button type="button" class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-black text-white" onclick="document.getElementById('edit-user-<?= e($u['id']) ?>').classList.toggle('hidden')">Edit</button>
                <?php else: ?>
                    <small class="text-slate-400">Tidak tersedia</small>
                <?php endif; ?>
            </span>
        </div>
        <?php if ($canEdit): ?>
            <div id="edit-user-<?= e($u['id']) ?>" class="hidden border-t border-slate-100 bg-slate-50 px-5 py-4">
                <div class="grid gap-4 xl:grid-cols-2">
                    <form method="post" class="grid gap-3 rounded-2xl bg-white p-4 md:grid-cols-5">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                        <input class="rounded-xl border border-slate-200 px-3 py-3" name="name" value="<?= e($u['name']) ?>" required>
                        <select class="rounded-xl border border-slate-200 px-3 py-3" name="role" required>
                            <?php
                                $allowedRoles = $user['role'] === 'SUPERADMIN' ? ['SUPERADMIN','ADMIN','MODERATOR','USER'] : get_creatable_roles($user['role']);
                                foreach ($allowedRoles as $role):
                                    if ($role === 'SUPERADMIN' && $u['role'] !== 'SUPERADMIN' && $user['role'] !== 'SUPERADMIN') continue;
                            ?>
                                <option value="<?= e($role) ?>" <?= $u['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input class="rounded-xl border border-slate-200 px-3 py-3" name="unit" value="<?= e($u['unit']) ?>" required>
                        <select class="rounded-xl border border-slate-200 px-3 py-3" name="status" required>
                            <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>active</option>
                            <option value="inactive" <?= $u['status'] === 'inactive' ? 'selected' : '' ?>>inactive</option>
                        </select>
                        <button class="rounded-xl bg-orange-600 px-4 py-3 font-black text-white">Simpan</button>
                    </form>

                    <form method="post" class="grid gap-3 rounded-2xl bg-white p-4 md:grid-cols-[1fr_auto]">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                        <input class="rounded-xl border border-slate-200 px-3 py-3" name="new_password" value="password" required>
                        <button class="rounded-xl bg-slate-900 px-4 py-3 font-black text-white">Reset Password</button>
                    </form>
                </div>
                <p class="mt-3 text-xs text-slate-500">
                    Guard: user tidak bisa dinonaktifkan/diturunkan jika masih owner project aktif, punya task aktif, punya bawahan aktif, atau menjadi SUPERADMIN terakhir.
                </p>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
