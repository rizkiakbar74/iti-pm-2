<?php
$user = current_user();
$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$allowedFilters = ['all','unread','read'];
if (!in_array($filter, $allowedFilters, true)) { $filter = 'all'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user['id']]);
    }
    if ($action === 'clear_read') {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
        $stmt->execute([$user['id']]);
    }
    if ($action === 'delete_one') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $user['id']]);
    }
}

$where = "user_id = ?";
$params = [$user['id']];
if ($filter === 'unread') {
    $where .= " AND is_read = 0";
}
if ($filter === 'read') {
    $where .= " AND is_read = 1";
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);
$unreadCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$user['id']]);
$totalCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE {$where} ORDER BY created_at DESC LIMIT 200");
$stmt->execute($params);
$allRows = $stmt->fetchAll();

$typeCounts = [];
foreach ($allRows as $row) {
    $rowType = get_notification_type($row['title'], $row['message']);
    $typeCounts[$rowType] = ($typeCounts[$rowType] ?? 0) + 1;
}

$rows = array_values(array_filter($allRows, function($row) use ($type) {
    if ($type === 'all') return true;
    return get_notification_type($row['title'], $row['message']) === $type;
}));

$typeLabels = [
    'all' => 'Semua Jenis',
    'submit' => 'Submit',
    'comment' => 'Komentar',
    'project' => 'Project',
    'task' => 'Task',
    'deadline' => 'Deadline',
    'reject' => 'Reject',
    'approved' => 'Verified',
    'general' => 'Umum',
];
?>

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <p class="text-sm font-black uppercase tracking-wide text-orange-600">Notifikasi</p>
        <h2 class="text-3xl font-black">Pusat Notifikasi</h2>
        <p class="text-slate-500"><?= e($unreadCount) ?> belum dibaca dari <?= e($totalCount) ?> notifikasi.</p>
    </div>
    <form method="post" class="flex flex-wrap gap-2">
        <?= csrf_field() ?>
        <button name="action" value="mark_all_read" class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-black text-white">Tandai Dibaca</button>
        <button name="action" value="clear_read" class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700">Bersihkan Terbaca</button>
    </form>
</div>

<div class="mb-4 grid gap-3 lg:grid-cols-[1fr_auto]">
    <div class="flex flex-wrap gap-2">
        <?php foreach (['all'=>'Semua','unread'=>'Belum Dibaca','read'=>'Sudah Dibaca'] as $key => $label): ?>
            <a class="rounded-full px-4 py-2 text-sm font-black <?= $filter === $key ? 'bg-orange-600 text-white' : 'bg-white text-slate-700 border border-slate-200' ?>" href="index.php?page=notifications&filter=<?= e($key) ?>&type=<?= e($type) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" class="flex gap-2">
        <input type="hidden" name="page" value="notifications">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <select name="type" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-bold" onchange="this.form.submit()">
            <?php foreach ($typeLabels as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($label) ?><?= $key !== 'all' && isset($typeCounts[$key]) ? ' (' . e($typeCounts[$key]) . ')' : '' ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="space-y-3">
        <?php foreach ($rows as $n): ?>
            <?php $nType = get_notification_type($n['title'], $n['message']); ?>
            <div class="grid gap-2 rounded-2xl border p-4 <?= $n['is_read'] ? 'border-slate-100' : 'border-orange-200 bg-orange-50/40' ?>">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <a class="block flex-1" href="actions/notification-open.php?id=<?= e($n['id']) ?>">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong><?= e($n['title']) ?></strong>
                            <?= notification_type_badge($nType) ?>
                            <span class="rounded-full px-2 py-1 text-xs font-black <?= $n['is_read'] ? 'bg-slate-100 text-slate-500' : 'bg-orange-100 text-orange-700' ?>"><?= $n['is_read'] ? 'Terbaca' : 'Baru' ?></span>
                        </div>
                        <p class="mt-2 text-sm text-slate-500"><?= e($n['message']) ?></p>
                        <small class="text-slate-400"><?= e(date('d M Y H:i', strtotime($n['created_at']))) ?></small>
                    </a>
                    <form method="post" onsubmit="return confirm('Hapus notifikasi ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_one">
                        <input type="hidden" name="notification_id" value="<?= e($n['id']) ?>">
                        <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-700">Hapus</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada notifikasi untuk filter ini.</p>
        <?php endif; ?>
    </div>
</section>
