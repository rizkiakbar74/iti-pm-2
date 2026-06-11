<?php
$user = current_user();
$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$selectedId = (int)($_GET['id'] ?? 0);
$allowedFilters = ['all','unread','read'];
if (!in_array($filter, $allowedFilters, true)) $filter = 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $notificationId = (int)($_POST['notification_id'] ?? 0);
    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user['id']]);
    } elseif ($action === 'clear_read') {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
        $stmt->execute([$user['id']]);
    } elseif ($action === 'mark_one_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $user['id']]);
    } elseif ($action === 'delete_one') {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $user['id']]);
        if ($selectedId === $notificationId) $selectedId = 0;
    }
    redirect(app_url('index.php?' . http_build_query([
        'page' => 'notifications',
        'filter' => $filter,
        'type' => $type,
        'id' => $selectedId,
    ])));
}

$stmt = $pdo->prepare("
    SELECT n.*, p.title project_title, t.title task_title
    FROM notifications n
    LEFT JOIN projects p ON p.id = n.project_id
    LEFT JOIN tasks t ON t.id = n.task_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$user['id']]);
$allNotifications = $stmt->fetchAll();

$totalCount = count($allNotifications);
$unreadCount = count(array_filter($allNotifications, fn($n) => !(int)$n['is_read']));
$readCount = $totalCount - $unreadCount;
$typeLabels = ['all'=>'Semua Jenis','submit'=>'Submit','comment'=>'Komentar','project'=>'Project','task'=>'Task','deadline'=>'Deadline','reject'=>'Reject','approved'=>'Verified','general'=>'Umum'];
$typeCounts = [];
foreach ($allNotifications as $n) {
    $notificationType = get_notification_type($n['title'], $n['message']);
    $typeCounts[$notificationType] = ($typeCounts[$notificationType] ?? 0) + 1;
}
$rows = array_values(array_filter($allNotifications, function($n) use ($filter,$type) {
    $statusMatches = $filter === 'all' || ($filter === 'unread' && !(int)$n['is_read']) || ($filter === 'read' && (int)$n['is_read']);
    return $statusMatches && ($type === 'all' || get_notification_type($n['title'], $n['message']) === $type);
}));

$perPage = 10;
$totalPages = max(1, (int)ceil(count($rows) / $perPage));
$currentPage = min(max(1, (int)($_GET['p'] ?? 1)), $totalPages);
$pageRows = array_slice($rows, ($currentPage - 1) * $perPage, $perPage);
if (!$selectedId && $pageRows) $selectedId = (int)$pageRows[0]['id'];
$selected = null;
foreach ($allNotifications as $n) if ((int)$n['id'] === $selectedId) $selected = $n;

$notificationUrl = function(array $changes = []) use ($filter,$type,$selectedId) {
    return app_url('index.php?' . http_build_query(array_merge(['page'=>'notifications','filter'=>$filter,'type'=>$type,'id'=>$selectedId], $changes)));
};
$tones = ['approved'=>'blue','deadline'=>'orange','project'=>'purple','task'=>'green','submit'=>'green','comment'=>'purple','reject'=>'red','general'=>'slate'];
?>
<div class="notification-center-page">
  <header class="notification-center-header">
    <div><p>Dashboard / Notification / Notification Center</p><h2>Notification Center</h2><span>Pusat semua notifikasi sistem. Pantau dan kelola notifikasi Anda.</span></div>
    <form method="post"><?= csrf_field() ?><button name="action" value="mark_all_read">✓ Tandai Semua Dibaca</button></form>
  </header>

  <section class="notification-center-layout">
    <aside class="notification-filter-panel">
      <h3>Filter Notifikasi</h3>
      <nav>
        <?php foreach ([['all','Semua Notifikasi',$totalCount],['unread','Belum Dibaca',$unreadCount],['read','Sudah Dibaca',$readCount]] as [$key,$label,$count]): ?>
          <a class="<?= $filter===$key?'active':'' ?>" href="<?= e($notificationUrl(['filter'=>$key,'p'=>1,'id'=>0])) ?>"><i><?= $key==='all'?'✓':($key==='unread'?'○':'□') ?></i><span><?= e($label) ?></span><b><?= e($count) ?></b></a>
        <?php endforeach; ?>
        <?php foreach (['project','task','deadline','comment','approved','reject','general'] as $key): ?>
          <a class="<?= $type===$key?'active':'' ?>" href="<?= e($notificationUrl(['type'=>$key,'p'=>1,'id'=>0])) ?>"><i>•</i><span><?= e($typeLabels[$key]) ?></span><b><?= e($typeCounts[$key]??0) ?></b></a>
        <?php endforeach; ?>
      </nav>
      <div class="notification-advanced-filter">
        <h3>Filter Lanjutan</h3>
        <form method="get"><input type="hidden" name="page" value="notifications"><input type="hidden" name="filter" value="<?= e($filter) ?>"><select name="type" onchange="this.form.submit()"><?php foreach($typeLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $type===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></form>
        <a href="<?= e(app_url('index.php?page=notifications')) ?>">↻ Reset Filter</a>
        <form method="post" onsubmit="return confirm('Hapus semua notifikasi yang sudah dibaca?')"><?= csrf_field() ?><button name="action" value="clear_read">Bersihkan Terbaca</button></form>
      </div>
    </aside>

    <section class="notification-inbox-panel">
      <header><h3><?= e(count($rows)) ?> Notifikasi</h3><span>Terbaru di Atas</span></header>
      <div class="notification-list">
        <?php foreach ($pageRows as $n): $nType=get_notification_type($n['title'],$n['message']); $tone=$tones[$nType]??'slate'; ?>
          <a class="notification-list-item <?= !(int)$n['is_read']?'unread':'' ?> <?= $selectedId===(int)$n['id']?'selected':'' ?>" href="<?= e($notificationUrl(['id'=>$n['id']])) ?>">
            <i class="tone-<?= e($tone) ?>"><?= e(strtoupper(substr($nType,0,1))) ?></i>
            <span><b><?= e($n['title']) ?></b><small><?= e($n['message']) ?></small><em><?= e($typeLabels[$nType]??'Umum') ?><?= $n['project_title']?' • '.e($n['project_title']):'' ?></em></span>
            <time><?= e(date('d M, H:i',strtotime($n['created_at']))) ?><?= !(int)$n['is_read']?'<b></b>':'' ?></time>
          </a>
        <?php endforeach; ?>
        <?php if(!$pageRows): ?><div class="notification-empty"><h3>Data Tidak Ditemukan</h3><p>Belum ada notifikasi untuk filter ini.</p><a href="<?= e(app_url('index.php?page=notifications')) ?>">Reset Filter</a></div><?php endif; ?>
      </div>
      <footer><span>Halaman <?= e($currentPage) ?> dari <?= e($totalPages) ?></span><nav><?php for($p=1;$p<=$totalPages;$p++): ?><a class="<?= $p===$currentPage?'active':'' ?>" href="<?= e($notificationUrl(['p'=>$p,'id'=>0])) ?>"><?= e($p) ?></a><?php endfor; ?></nav><b><?= e($perPage) ?> / halaman</b></footer>
    </section>

    <aside class="notification-detail-panel">
      <h3>Detail Notifikasi</h3>
      <?php if($selected): $selectedType=get_notification_type($selected['title'],$selected['message']); $targetUrl=($selected['task_id']||$selected['project_id'])?'actions/notification-open.php?id='.(int)$selected['id']:''; ?>
        <div class="notification-detail-icon tone-<?= e($tones[$selectedType]??'slate') ?>"><?= e(strtoupper(substr($selectedType,0,1))) ?></div>
        <span class="notification-detail-type"><?= e($typeLabels[$selectedType]??'Umum') ?></span>
        <h4><?= e($selected['title']) ?></h4>
        <dl><div><dt>Waktu</dt><dd><?= e(date('d M Y, H:i',strtotime($selected['created_at']))) ?> WIB</dd></div><div><dt>Status</dt><dd><?= (int)$selected['is_read']?'Sudah Dibaca':'Belum Dibaca' ?></dd></div><?php if($selected['project_title']): ?><div><dt>Project</dt><dd><?= e($selected['project_title']) ?></dd></div><?php endif; ?><?php if($selected['task_title']): ?><div><dt>Task</dt><dd><?= e($selected['task_title']) ?></dd></div><?php endif; ?></dl>
        <div class="notification-message"><b>Pesan</b><p><?= nl2br(e($selected['message'])) ?></p></div>
        <div class="notification-detail-actions">
          <?php if(!(int)$selected['is_read']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="notification_id" value="<?= e($selected['id']) ?>"><button class="primary" name="action" value="mark_one_read">✓ Tandai sebagai Dibaca</button></form><?php endif; ?>
          <?php if($targetUrl): ?><a href="<?= e($targetUrl) ?>">Buka <?= $selected['task_id']?'Task':'Project' ?></a><?php endif; ?>
          <form method="post" onsubmit="return confirm('Hapus notifikasi ini?')"><?= csrf_field() ?><input type="hidden" name="notification_id" value="<?= e($selected['id']) ?>"><button name="action" value="delete_one">Hapus Notifikasi</button></form>
        </div>
      <?php else: ?><p class="notification-empty">Pilih notifikasi untuk melihat detail.</p><?php endif; ?>
    </aside>
  </section>
</div>
