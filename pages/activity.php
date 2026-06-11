<?php
$user = current_user();
$search = trim($_GET['q'] ?? '');
$actionFilter = trim($_GET['action_filter'] ?? '');
$actorFilter = trim($_GET['actor_filter'] ?? '');
$targetFilter = $_GET['target'] ?? 'all';
$selectedId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->query("
    SELECT a.*, u.name, u.role, p.title project_title, t.title task_title
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN projects p ON p.id = a.project_id
    LEFT JOIN tasks t ON t.id = a.task_id
    ORDER BY a.created_at DESC
    LIMIT 1000
");
$all = $stmt->fetchAll();
$visible = $user['role'] === 'SUPERADMIN' ? $all : array_values(array_filter($all, function($row) use ($user) {
    return (int)$row['user_id'] === (int)$user['id'] || role_rank($row['role']) < role_rank($user['role']);
}));
$moduleOf = fn($row) => !empty($row['task_id']) ? 'Task' : (!empty($row['project_id']) ? 'Project' : (stripos($row['action'],'user')!==false||stripos($row['action'],'password')!==false ? 'User' : (stripos($row['action'],'login')!==false ? 'Auth' : 'System')));
$rows = array_values(array_filter($visible, function($row) use ($search,$actionFilter,$actorFilter,$targetFilter,$moduleOf) {
    $haystack = strtolower($row['action'].' '.$row['detail'].' '.$row['name'].' '.$row['role'].' '.$moduleOf($row));
    if ($search && !str_contains($haystack, strtolower($search))) return false;
    if ($actionFilter && $row['action'] !== $actionFilter) return false;
    if ($actorFilter && (string)$row['user_id'] !== $actorFilter) return false;
    if ($targetFilter === 'project' && empty($row['project_id'])) return false;
    if ($targetFilter === 'task' && empty($row['task_id'])) return false;
    if ($targetFilter === 'general' && (!empty($row['project_id']) || !empty($row['task_id']))) return false;
    return true;
}));
$actionOptions = array_values(array_unique(array_column($visible,'action'))); sort($actionOptions);
$actorOptions = []; foreach($visible as $row) $actorOptions[$row['user_id']] = [$row['name'],$row['role']];
$activityFailed = count(array_filter($rows, fn($row) => str_contains(strtolower($row['action'].' '.$row['detail']), 'gagal')));
$activitySuccess = count($rows) - $activityFailed;
$activityChanges = count(array_filter($rows, fn($row) => preg_match('/ubah|edit|update|buat|tambah|hapus/i', $row['action'].' '.$row['detail'])));
$perPage = 10; $totalPages=max(1,(int)ceil(count($rows)/$perPage)); $currentPage=min(max(1,(int)($_GET['p']??1)),$totalPages);
$pageRows=array_slice($rows,($currentPage-1)*$perPage,$perPage);
if(!$selectedId&&$pageRows)$selectedId=(int)$pageRows[0]['id'];
$selected=null; foreach($visible as $row)if((int)$row['id']===$selectedId)$selected=$row;
$activityUrl=function(array $changes=[])use($search,$actionFilter,$actorFilter,$targetFilter,$selectedId){return app_url('index.php?'.http_build_query(array_merge(['page'=>'activity','q'=>$search,'action_filter'=>$actionFilter,'actor_filter'=>$actorFilter,'target'=>$targetFilter,'id'=>$selectedId],$changes)));};
$actionTone=function($action){$a=strtolower($action);if(str_contains($a,'hapus')||str_contains($a,'ditolak'))return'red';if(str_contains($a,'buat')||str_contains($a,'tambah')||str_contains($a,'login'))return'green';if(str_contains($a,'edit')||str_contains($a,'ubah')||str_contains($a,'verified'))return'blue';return'orange';};
?>
<div class="activity-log-page">
 <section class="activity-mobile-summary-v3"><div><b><?= e(count($rows)) ?></b><span>Semua</span></div><div><b><?= e($activitySuccess) ?></b><span>Berhasil</span></div><div><b><?= e($activityChanges) ?></b><span>Perubahan</span></div><div><b><?= e($activityFailed) ?></b><span>Gagal</span></div></section>
 <header class="activity-log-header"><div><p>Dashboard / Activity Log / Activity Log</p><h2>Activity Log</h2><span>Riwayat aktivitas pengguna dalam sistem sesuai hierarchy role.</span></div><div><a href="<?= e(app_url('actions/activity-export.php')) ?>">↓ Export Log</a><a href="<?= e($activityUrl()) ?>">↻ Refresh</a></div></header>
 <form method="get" class="activity-filter-bar"><input type="hidden" name="page" value="activity"><label><span>⌕</span><input name="q" value="<?= e($search) ?>" placeholder="Cari aktivitas..."></label><select name="target"><option value="all">Semua Modul</option><option value="project" <?= $targetFilter==='project'?'selected':'' ?>>Project</option><option value="task" <?= $targetFilter==='task'?'selected':'' ?>>Task</option><option value="general" <?= $targetFilter==='general'?'selected':'' ?>>Umum</option></select><select name="actor_filter"><option value="">Semua User</option><?php foreach($actorOptions as $id=>[$name,$role]): ?><option value="<?= e($id) ?>" <?= $actorFilter===(string)$id?'selected':'' ?>><?= e($name) ?> - <?= e($role) ?></option><?php endforeach; ?></select><select name="action_filter"><option value="">Semua Aksi</option><?php foreach($actionOptions as $action): ?><option value="<?= e($action) ?>" <?= $actionFilter===$action?'selected':'' ?>><?= e($action) ?></option><?php endforeach; ?></select><button>Filter</button><a href="<?= e(app_url('index.php?page=activity')) ?>">Reset</a></form>
 <section class="activity-log-layout">
  <section class="activity-table-panel"><header><h3><?= e(count($rows)) ?> Aktivitas</h3><span><?= e(count(array_unique(array_column($visible,'user_id')))) ?> aktor visible</span></header><div class="activity-table-scroll"><table><thead><tr><th>Waktu</th><th>User</th><th>Aksi</th><th>Modul</th><th>Deskripsi / Target</th><th>Status</th></tr></thead><tbody>
  <?php foreach($pageRows as $row): $tone=$actionTone($row['action']); $module=$moduleOf($row); $failed=str_contains(strtolower($row['action'].' '.$row['detail']),'gagal'); ?><tr class="<?= $selectedId===(int)$row['id']?'selected':'' ?>" onclick="location.href='<?= e($activityUrl(['id'=>$row['id']])) ?>'"><td><b><?= e(date('d M Y',strtotime($row['created_at']))) ?></b><small><?= e(date('H:i:s',strtotime($row['created_at']))) ?> WIB</small></td><td><span class="activity-user"><i><?= e(strtoupper(substr($row['name'],0,2))) ?></i><span><b><?= e($row['name']) ?></b><small><?= e($row['role']) ?></small></span></span></td><td><span class="activity-action <?= e($tone) ?>"><?= e($row['action']) ?></span></td><td><?= e($module) ?></td><td><b><?= e($row['detail']) ?></b><small><?= e($row['task_title']??$row['project_title']??'Log umum') ?></small></td><td><span class="<?= $failed?'activity-failed':'activity-success' ?>"><?= $failed?'Failed':'Success' ?></span></td></tr><?php endforeach; ?>
  <?php if(!$pageRows): ?><tr><td colspan="6"><div class="activity-empty"><h3>Data Tidak Ditemukan</h3><p>Belum ada activity log yang cocok dengan filter saat ini.</p><a href="<?= e(app_url('index.php?page=activity')) ?>">Reset Filter</a></div></td></tr><?php endif; ?></tbody></table></div><footer><span>Menampilkan <?= count($rows)?e(($currentPage-1)*$perPage+1):0 ?> - <?= e(min($currentPage*$perPage,count($rows))) ?> dari <?= e(count($rows)) ?> aktivitas</span><nav><?php for($p=1;$p<=$totalPages;$p++): ?><a class="<?= $p===$currentPage?'active':'' ?>" href="<?= e($activityUrl(['p'=>$p,'id'=>0])) ?>"><?= e($p) ?></a><?php endfor; ?></nav><b><?= e($perPage) ?> / halaman</b></footer></section>
  <aside class="activity-detail-panel"><h3>Detail Aktivitas</h3><?php if($selected): $module=$moduleOf($selected); $targetUrl=get_activity_target_url($selected); ?><div class="activity-detail-icon">✎</div><span class="activity-success">Success</span><h4><?= e($selected['action']) ?>: <?= e($selected['detail']) ?></h4><section><h5>Informasi Umum</h5><dl><div><dt>Waktu</dt><dd><?= e(date('d M Y, H:i:s',strtotime($selected['created_at']))) ?> WIB</dd></div><div><dt>User</dt><dd><?= e($selected['name']) ?> <small><?= e($selected['role']) ?></small></dd></div><div><dt>Modul</dt><dd><?= e($module) ?></dd></div><div><dt>Aksi</dt><dd><?= e($selected['action']) ?></dd></div><div><dt>Target</dt><dd><?= e($selected['task_title']??$selected['project_title']??'Log umum') ?></dd></div></dl></section><section><h5>Catatan Audit</h5><p><?= e($selected['detail']) ?></p><small>Schema saat ini belum merekam IP address, user agent, atau data sebelum/sesudah perubahan.</small></section><?php if($targetUrl!=='index.php?page=activity'): ?><a class="activity-open-target" href="<?= e(app_url($targetUrl)) ?>">Buka <?= e($module) ?> Terkait</a><?php endif; ?><?php else: ?><p class="activity-empty">Pilih aktivitas untuk melihat detail.</p><?php endif; ?></aside>
 </section>
</div>
