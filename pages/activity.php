<?php
$user = current_user();
$actionFilter = trim($_GET['action_filter'] ?? '');
$actorFilter = trim($_GET['actor_filter'] ?? '');
$targetFilter = $_GET['target'] ?? 'all';

$stmt = $pdo->query("
    SELECT a.*, u.name, u.role
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT 500
");
$all = $stmt->fetchAll();

if ($user['role'] === 'SUPERADMIN') {
    $visible = $all;
} else {
    $visible = array_values(array_filter($all, function($row) use ($user) {
        if ((int)$row['user_id'] === (int)$user['id']) return true;
        return role_rank($row['role']) < role_rank($user['role']);
    }));
}

$rows = array_values(array_filter($visible, function($row) use ($actionFilter, $actorFilter, $targetFilter) {
    if ($actionFilter && stripos($row['action'], $actionFilter) === false && stripos($row['detail'], $actionFilter) === false) return false;
    if ($actorFilter && stripos($row['name'], $actorFilter) === false && stripos($row['role'], $actorFilter) === false) return false;
    if ($targetFilter === 'project' && empty($row['project_id'])) return false;
    if ($targetFilter === 'task' && empty($row['task_id'])) return false;
    if ($targetFilter === 'general' && (!empty($row['project_id']) || !empty($row['task_id']))) return false;
    return true;
}));
$rows = array_slice($rows, 0, 150);

$actionSummary = [];
foreach ($visible as $row) {
    $actionSummary[$row['action']] = ($actionSummary[$row['action']] ?? 0) + 1;
}
arsort($actionSummary);
$topActions = array_slice($actionSummary, 0, 5, true);
?>

<div class="mb-6">
    <p class="text-sm font-black uppercase tracking-wide text-orange-600">Activity Log</p>
    <h2 class="text-3xl font-black">Riwayat Aktivitas</h2>
    <p class="text-slate-500">Log ditampilkan sesuai hierarki role aktif dan bisa diklik jika terkait project/task.</p>
</div>

<div class="mb-6 grid gap-4 md:grid-cols-4">
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-bold text-slate-500">Log Visible</p>
        <strong class="mt-2 block text-3xl font-black"><?= e(count($visible)) ?></strong>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-bold text-slate-500">Hasil Filter</p>
        <strong class="mt-2 block text-3xl font-black"><?= e(count($rows)) ?></strong>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-bold text-slate-500">Aktor Unik</p>
        <strong class="mt-2 block text-3xl font-black"><?= e(count(array_unique(array_column($visible, 'user_id')))) ?></strong>
    </div>
    <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-bold text-slate-500">Jenis Aktivitas</p>
        <strong class="mt-2 block text-3xl font-black"><?= e(count($actionSummary)) ?></strong>
    </div>
</div>

<form method="get" class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <input type="hidden" name="page" value="activity">
    <div class="grid gap-3 lg:grid-cols-4">
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="action_filter" placeholder="Cari aksi/detail..." value="<?= e($actionFilter) ?>">
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="actor_filter" placeholder="Cari aktor/role..." value="<?= e($actorFilter) ?>">
        <select class="rounded-xl border border-slate-200 px-4 py-3" name="target">
            <?php foreach (['all'=>'Semua Target','project'=>'Terkait Project','task'=>'Terkait Task','general'=>'Umum'] as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $targetFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="rounded-xl bg-orange-600 px-4 py-3 font-black text-white">Filter Log</button>
    </div>
</form>

<?php if ($topActions): ?>
<div class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="mb-3 text-sm font-black text-slate-700">Top Activity</p>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($topActions as $actionName => $count): ?>
            <a class="rounded-full bg-slate-100 px-3 py-2 text-xs font-black text-slate-700 hover:bg-slate-200" href="index.php?page=activity&action_filter=<?= e(urlencode($actionName)) ?>"><?= e($actionName) ?> • <?= e($count) ?></a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<section class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm iti-scrollbar">
    <div class="grid min-w-[920px] grid-cols-5 gap-3 bg-slate-50 px-5 py-3 text-xs font-black uppercase text-slate-500">
        <span>Waktu</span>
        <span>Aktor</span>
        <span>Aksi</span>
        <span class="col-span-2">Detail / Target</span>
    </div>
    <?php foreach ($rows as $row): ?>
        <?php $url = get_activity_target_url($row); ?>
        <a href="<?= e($url) ?>" class="grid min-w-[920px] grid-cols-5 gap-3 border-t border-slate-100 px-5 py-4 text-sm hover:bg-slate-50">
            <span><?= e(date('d M Y H:i', strtotime($row['created_at']))) ?></span>
            <span><?= e($row['name']) ?> <small class="block text-slate-400"><?= e($row['role']) ?></small></span>
            <span class="font-bold"><?= e($row['action']) ?></span>
            <span class="col-span-2 text-slate-500">
                <?= e($row['detail']) ?>
                <small class="mt-1 block font-bold text-orange-600">
                    <?= !empty($row['task_id']) ? 'Buka Task' : (!empty($row['project_id']) ? 'Buka Project' : 'Log umum') ?>
                </small>
            </span>
        </a>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
        <div class="border-t border-slate-100 p-6 text-sm text-slate-500">Belum ada activity log yang cocok dengan filter.</div>
    <?php endif; ?>
</section>
