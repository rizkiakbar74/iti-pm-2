<?php
$user = current_user();

$roleDefinitions = [
    'SUPERADMIN' => ['Super Admin', 'SA', 'Akses penuh ke seluruh sistem', '#ef4444'],
    'ADMIN' => ['Admin', 'AD', 'Mengelola user, project, task, dan review', '#2563eb'],
    'MODERATOR' => ['Moderator', 'MO', 'Mengelola workflow untuk area yang diizinkan', '#7c3aed'],
    'USER' => ['User', 'US', 'Melihat assignment dan mengirim bukti pekerjaan', '#f59e0b'],
];
$selectedRole = strtoupper($_GET['role'] ?? 'SUPERADMIN');
if (!isset($roleDefinitions[$selectedRole])) $selectedRole = 'SUPERADMIN';

$stmt = $pdo->query("SELECT role, COUNT(*) total, SUM(status = 'active') active_total FROM users WHERE deleted_at IS NULL GROUP BY role");
$roleCounts = [];
foreach ($stmt->fetchAll() as $row) {
    $roleCounts[$row['role']] = ['total' => (int)$row['total'], 'active' => (int)$row['active_total']];
}

$permissions = [
    'Dashboard' => [
        'Lihat dashboard' => ['SUPERADMIN','ADMIN','MODERATOR','USER'],
        'Lihat seluruh data sistem' => ['SUPERADMIN'],
        'Generate report' => ['SUPERADMIN','ADMIN'],
        'System backup' => ['SUPERADMIN','ADMIN'],
    ],
    'Project' => [
        'Lihat project yang diizinkan' => ['SUPERADMIN','ADMIN','MODERATOR','USER'],
        'Buat project' => ['SUPERADMIN','ADMIN','MODERATOR'],
        'Edit project yang dikelola' => ['SUPERADMIN','ADMIN','MODERATOR'],
        'Kelola anggota project' => ['SUPERADMIN','ADMIN','MODERATOR'],
        'Arsipkan project tanpa batas progress' => ['SUPERADMIN'],
    ],
    'Task' => [
        'Lihat task yang diizinkan' => ['SUPERADMIN','ADMIN','MODERATOR','USER'],
        'Buat dan assign task' => ['SUPERADMIN','ADMIN','MODERATOR'],
        'Edit task sebelum submission' => ['SUPERADMIN','ADMIN','MODERATOR'],
        'Submit bukti pekerjaan' => ['SUPERADMIN','ADMIN','MODERATOR','USER'],
        'Review approve / reject / reopen' => ['SUPERADMIN','ADMIN','MODERATOR'],
    ],
    'User & Audit' => [
        'Lihat activity log sesuai hierarchy' => ['SUPERADMIN','ADMIN','MODERATOR','USER'],
        'Kelola user di bawah hierarchy' => ['SUPERADMIN','ADMIN','MODERATOR'],
        'Kelola role Super Admin' => ['SUPERADMIN'],
        'Lihat Role & Permission Management' => ['SUPERADMIN'],
    ],
];

$allowedCount = 0;
$permissionTotal = 0;
foreach ($permissions as $items) {
    foreach ($items as $allowedRoles) {
        $permissionTotal++;
        if (in_array($selectedRole, $allowedRoles, true)) $allowedCount++;
    }
}
$deniedCount = $permissionTotal - $allowedCount;
$allowedPercent = $permissionTotal ? round($allowedCount / $permissionTotal * 100) : 0;
$selected = $roleDefinitions[$selectedRole];
?>
<div class="role-management-page">
  <header class="role-management-header">
    <div><p>Dashboard / Role & Permission / Role & Permission Management</p><h2>Role & Permission Management</h2><span>Ringkasan akses berdasarkan permission backend yang sedang aktif.</span></div>
    <a href="<?= e(app_url('index.php?page=users&create=1')) ?>">+ Tambah User Baru</a>
  </header>

  <section class="role-kpis">
    <?php foreach ([
      ['Total Role', count($roleDefinitions), 'R', 'blue'],
      ['Total Permission', $permissionTotal, 'P', 'green'],
      ['User dengan Role', array_sum(array_column($roleCounts, 'total')), 'U', 'amber'],
      ['Permission Aktif', $allowedCount, 'A', 'purple'],
      ['Permission Terbatas', $deniedCount, 'D', 'red'],
    ] as [$label,$value,$icon,$tone]): ?>
      <article class="role-kpi <?= e($tone) ?>"><i><?= e($icon) ?></i><div><small><?= e($label) ?></small><b><?= e($value) ?></b><span>Data akses saat ini</span></div></article>
    <?php endforeach; ?>
  </section>

  <section class="role-workspace">
    <aside class="role-list-panel">
      <div class="role-panel-title"><h3>Daftar Role</h3><span><?= e(count($roleDefinitions)) ?> role aktif</span></div>
      <label class="role-search"><span>⌕</span><input type="search" placeholder="Cari role..." autocomplete="off" data-role-search></label>
      <div class="role-list">
        <?php foreach ($roleDefinitions as $key => [$name,$code,$description,$color]): $count=$roleCounts[$key]['total']??0; ?>
          <a href="<?= e(app_url('index.php?page=roles&role='.$key)) ?>" data-role-name="<?= e(strtolower($name.' '.$key.' '.$code.' '.$description)) ?>" class="<?= $selectedRole===$key?'active':'' ?>">
            <i style="--role-color:<?= e($color) ?>"><?= e($code) ?></i><span><b><?= e($name) ?></b><small><?= e($description) ?></small></span><em><?= e($count) ?> User</em>
          </a>
        <?php endforeach; ?>
        <p class="role-search-empty" data-role-empty hidden>Tidak ada role yang cocok.</p>
      </div>
      <p class="role-readonly-note">Permission mengikuti helper backend dan ditampilkan read-only agar workflow yang sudah stabil tidak berubah.</p>
    </aside>

    <section class="permission-panel">
      <header><div><h3>Detail Role: <?= e($selected[0]) ?></h3><span><?= e($selected[2]) ?></span></div><b>Read-only</b></header>
      <div class="permission-toolbar"><label><span>⌕</span><input type="search" placeholder="Cari permission atau modul..." autocomplete="off" data-permission-search></label><div><i class="allow"></i> Allow <i class="deny"></i> Deny</div></div>
      <div class="permission-table-wrap">
        <table class="permission-table">
          <thead><tr><th>Modul / Permission</th><th>Status</th><th>Keterangan</th></tr></thead>
          <tbody>
          <?php foreach ($permissions as $module => $items): ?>
            <tr class="permission-module" data-permission-module="<?= e(strtolower($module)) ?>"><td colspan="3"><?= e($module) ?></td></tr>
            <?php foreach ($items as $label => $allowedRoles): $allowed=in_array($selectedRole,$allowedRoles,true); ?>
              <tr data-permission-name="<?= e(strtolower($module.' '.$label.' '.($allowed?'allow diizinkan':'deny tidak tersedia'))) ?>" data-permission-parent="<?= e(strtolower($module)) ?>"><td><?= e($label) ?></td><td><span class="permission-state <?= $allowed?'allow':'deny' ?>"><?= $allowed?'✓ Allow':'× Deny' ?></span></td><td><?= $allowed?'Diizinkan oleh aturan backend saat ini':'Tidak tersedia untuk role ini' ?></td></tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
            <tr class="permission-search-empty" data-permission-empty hidden><td colspan="3">Tidak ada permission yang cocok.</td></tr>
          </tbody>
        </table>
      </div>
    </section>

    <aside class="role-info-column">
      <section><h3>Informasi Role</h3><dl><div><dt>Nama Role</dt><dd><?= e($selected[0]) ?></dd></div><div><dt>Kode Role</dt><dd><?= e($selected[1]) ?></dd></div><div><dt>Deskripsi</dt><dd><?= e($selected[2]) ?></dd></div><div><dt>Status</dt><dd><span class="role-active">Active</span></dd></div><div><dt>Total User</dt><dd><?= e($roleCounts[$selectedRole]['total']??0) ?></dd></div><div><dt>User Aktif</dt><dd><?= e($roleCounts[$selectedRole]['active']??0) ?></dd></div></dl></section>
      <section><h3>Ringkasan Akses</h3><div class="role-donut" style="--allowed:<?= e($allowedPercent) ?>"><span><b><?= e($allowedCount) ?></b><small>Aktif</small></span></div><div class="role-legend"><p><i class="allow"></i><span>Allow</span><b><?= e($allowedCount) ?> (<?= e($allowedPercent) ?>%)</b></p><p><i class="deny"></i><span>Deny</span><b><?= e($deniedCount) ?> (<?= e(100-$allowedPercent) ?>%)</b></p></div></section>
    </aside>
  </section>
</div>
<script>
(() => {
  const normalize = value => value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();

  const roleSearch = document.querySelector('[data-role-search]');
  const roleItems = [...document.querySelectorAll('[data-role-name]')];
  const roleEmpty = document.querySelector('[data-role-empty]');
  roleSearch?.addEventListener('input', () => {
    const query = normalize(roleSearch.value);
    let visible = 0;
    roleItems.forEach(item => {
      const matches = !query || normalize(item.dataset.roleName).includes(query);
      item.hidden = !matches;
      if (matches) visible++;
    });
    if (roleEmpty) roleEmpty.hidden = visible > 0;
  });

  const permissionSearch = document.querySelector('[data-permission-search]');
  const permissionRows = [...document.querySelectorAll('[data-permission-name]')];
  const moduleRows = [...document.querySelectorAll('[data-permission-module]')];
  const permissionEmpty = document.querySelector('[data-permission-empty]');
  permissionSearch?.addEventListener('input', () => {
    const query = normalize(permissionSearch.value);
    let visible = 0;
    permissionRows.forEach(row => {
      const moduleMatches = normalize(row.dataset.permissionParent).includes(query);
      const matches = !query || moduleMatches || normalize(row.dataset.permissionName).includes(query);
      row.hidden = !matches;
      if (matches) visible++;
    });
    moduleRows.forEach(moduleRow => {
      const module = moduleRow.dataset.permissionModule;
      moduleRow.hidden = !permissionRows.some(row => row.dataset.permissionParent === module && !row.hidden);
    });
    if (permissionEmpty) permissionEmpty.hidden = visible > 0;
  });
})();
</script>
