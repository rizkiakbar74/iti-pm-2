<?php
$user = current_user();
$unreadSidebarCount = get_unread_notification_count($pdo, (int)$user['id']);
$allowedPeriods = [1, 3, 6, 12];
$period = (int)($_GET['period'] ?? ($_SESSION['dashboard_period'] ?? 6));
if (!in_array($period, $allowedPeriods, true)) { $period = 6; }
$search = trim($_GET['q'] ?? '');

if ($user['role'] === 'SUPERADMIN') {
    $projectWhere = "1=1";
    $params = [];
} else {
    $projectWhere = "p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)";
    $params = [$user['id']];
}

function build_visible_query_params($baseParams, $extra = []) {
    return array_merge($baseParams, $extra);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE {$projectWhere} AND p.deleted_at IS NULL");
$stmt->execute($params);
$totalProjects = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE {$projectWhere} AND p.deleted_at IS NULL AND p.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$stmt->execute($params);
$projectsBeforeMonth = (int)$stmt->fetchColumn();
$projectTrend = $projectsBeforeMonth > 0 ? (int)round((($totalProjects - $projectsBeforeMonth) / $projectsBeforeMonth) * 100) : ($totalProjects > 0 ? 100 : 0);

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE {$projectWhere} AND t.deleted_at IS NULL
");
$stmt->execute($params);
$totalTasks = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id = t.project_id
    WHERE {$projectWhere} AND t.deleted_at IS NULL AND t.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')
");
$stmt->execute($params);
$tasksBeforeMonth = (int)$stmt->fetchColumn();
$taskTrend = $tasksBeforeMonth > 0 ? (int)round((($totalTasks - $tasksBeforeMonth) / $tasksBeforeMonth) * 100) : ($totalTasks > 0 ? 100 : 0);

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL");
$totalUsers = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$usersBeforeMonth = (int)$stmt->fetchColumn();
$userTrend = $usersBeforeMonth > 0 ? (int)round((($totalUsers - $usersBeforeMonth) / $usersBeforeMonth) * 100) : ($totalUsers > 0 ? 100 : 0);

$stmt = $pdo->prepare("
    SELECT 
      SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS open_count,
      SUM(CASE WHEN t.status = 'submitted' THEN 1 ELSE 0 END) AS review_count,
      SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) AS done_count,
      SUM(CASE WHEN t.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
      SUM(CASE WHEN t.deadline_at < NOW() AND t.status <> 'approved' THEN 1 ELSE 0 END) AS overdue_count,
      SUM(CASE WHEN DATE(t.deadline_at) = CURDATE() AND t.status <> 'approved' THEN 1 ELSE 0 END) AS due_today_count,
      SUM(CASE WHEN t.deadline_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY) AND t.status <> 'approved' THEN 1 ELSE 0 END) AS due_soon_count
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE {$projectWhere} AND t.deleted_at IS NULL
");
$stmt->execute($params);
$stats = $stmt->fetch() ?: [];

$open = (int)($stats['open_count'] ?? 0);
$review = (int)($stats['review_count'] ?? 0);
$done = (int)($stats['done_count'] ?? 0);
$rejected = (int)($stats['rejected_count'] ?? 0);
$overdue = (int)($stats['overdue_count'] ?? 0);
$dueToday = (int)($stats['due_today_count'] ?? 0);
$dueSoon = (int)($stats['due_soon_count'] ?? 0);
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id = t.project_id
    WHERE {$projectWhere} AND t.deleted_at IS NULL AND t.status <> 'approved'
      AND t.deadline_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
");
$stmt->execute($params);
$dueNextSevenDays = (int)$stmt->fetchColumn();
$completionRate = $totalTasks ? (int)round(($done / $totalTasks) * 100) : 0;
$reviewRate = $totalTasks ? (int)round(($review / $totalTasks) * 100) : 0;
$overdueRate = $totalTasks ? (int)round(($overdue / $totalTasks) * 100) : 0;

$months = [];
for ($i = $period - 1; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("first day of -{$i} month"));
    $months[$key] = [
        'month' => $key,
        'label' => date('M y', strtotime($key . '-01')),
        'active_count' => 0,
        'review_count' => 0,
        'done_count' => 0,
        'project_count' => 0,
        'task_count' => 0,
        'overdue_count' => 0,
    ];
}

$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(t.created_at, '%Y-%m') AS month,
      SUM(CASE WHEN t.status = 'open' THEN 1 ELSE 0 END) AS active_count,
      SUM(CASE WHEN t.status = 'submitted' THEN 1 ELSE 0 END) AS review_count,
      SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) AS done_count,
      SUM(CASE WHEN t.deadline_at < NOW() AND t.status <> 'approved' THEN 1 ELSE 0 END) AS overdue_count,
      COUNT(*) AS task_count
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE {$projectWhere}
      AND t.deleted_at IS NULL
      AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL {$period} MONTH)
    GROUP BY DATE_FORMAT(t.created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
    if (isset($months[$row['month']])) {
        $months[$row['month']]['active_count'] = (int)$row['active_count'];
        $months[$row['month']]['review_count'] = (int)$row['review_count'];
        $months[$row['month']]['done_count'] = (int)$row['done_count'];
        $months[$row['month']]['overdue_count'] = (int)$row['overdue_count'];
        $months[$row['month']]['task_count'] = (int)$row['task_count'];
    }
}

$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(p.created_at, '%Y-%m') AS month, COUNT(*) AS project_count
    FROM projects p
    WHERE {$projectWhere}
      AND p.deleted_at IS NULL
      AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL {$period} MONTH)
    GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
    ORDER BY month ASC
");
$stmt->execute($params);
foreach ($stmt->fetchAll() as $row) {
    if (isset($months[$row['month']])) {
        $months[$row['month']]['project_count'] = (int)$row['project_count'];
    }
}

$chartRows = array_values($months);
$maxChart = max(1, ...array_map(fn($r) => max((int)$r['active_count'], (int)$r['review_count'], (int)$r['done_count']), $chartRows));
$maxSparkProject = max(1, ...array_map(fn($r) => (int)$r['project_count'], $chartRows));
$maxSparkTask = max(1, ...array_map(fn($r) => (int)$r['task_count'], $chartRows));

$stmt = $pdo->prepare("
    SELECT p.*, u.name AS owner_name,
       (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS task_count,
       (SELECT SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS approved_count
    FROM projects p
    JOIN users u ON u.id = p.owner_id
    WHERE {$projectWhere} AND p.deleted_at IS NULL AND p.status <> 'archived'
    ORDER BY p.deadline_at ASC
    LIMIT 6
");
$stmt->execute($params);
$projects = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT t.id, t.title, t.deadline_at, t.status, p.title AS project_title
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE {$projectWhere}
      AND t.deleted_at IS NULL
      AND t.status <> 'approved'
    ORDER BY
      CASE WHEN t.deadline_at < NOW() THEN 0 ELSE 1 END,
      t.deadline_at ASC
    LIMIT 6
");
$stmt->execute($params);
$deadlineRows = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT a.*, u.name, u.role
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN projects p ON p.id = a.project_id
    WHERE (" . ($user['role'] === 'SUPERADMIN' ? "1=1" : "(a.user_id = ? OR a.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?))") . ")
    ORDER BY a.created_at DESC
    LIMIT 6
");
$activityParams = $user['role'] === 'SUPERADMIN' ? [] : [$user['id'], $user['id']];
$stmt->execute($activityParams);
$activityRows = $stmt->fetchAll();

$searchResults = ['projects' => [], 'tasks' => [], 'users' => []];
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $pdo->prepare("SELECT p.id, p.title FROM projects p WHERE {$projectWhere} AND p.deleted_at IS NULL AND p.title LIKE ? ORDER BY p.title LIMIT 5");
    $stmt->execute(array_merge($params, [$like]));
    $searchResults['projects'] = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT t.id, t.title, p.title AS project_title FROM tasks t JOIN projects p ON p.id = t.project_id WHERE {$projectWhere} AND t.deleted_at IS NULL AND t.title LIKE ? ORDER BY t.title LIMIT 5");
    $stmt->execute(array_merge($params, [$like]));
    $searchResults['tasks'] = $stmt->fetchAll();

    if (can_manage_users($user['role'])) {
        $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE deleted_at IS NULL AND status = 'active' AND name LIKE ? ORDER BY name LIMIT 5");
        $stmt->execute([$like]);
        $searchResults['users'] = $stmt->fetchAll();
    }
}

$donePercent = $totalTasks ? round(($done / $totalTasks) * 100, 2) : 0;
$openPercent = $totalTasks ? round(($open / $totalTasks) * 100, 2) : 0;
$reviewPercent = $totalTasks ? round(($review / $totalTasks) * 100, 2) : 0;
$rejectedPercent = max(0, 100 - $donePercent - $openPercent - $reviewPercent);
$donutSegments = [
    ['key' => 'done', 'label' => 'Selesai', 'value' => $done, 'percent' => $donePercent, 'color' => '#22c55e'],
    ['key' => 'open', 'label' => 'Berjalan', 'value' => $open, 'percent' => $openPercent, 'color' => '#f97316'],
    ['key' => 'review', 'label' => 'Review', 'value' => $review, 'percent' => $reviewPercent, 'color' => '#fbbf24'],
    ['key' => 'rejected', 'label' => 'Revisi', 'value' => $rejected, 'percent' => $rejectedPercent, 'color' => '#ef4444'],
];
$progressSeries = array_map(fn($row) => (int)$row['task_count'] > 0 ? (int)round(((int)$row['done_count'] / (int)$row['task_count']) * 100) : 0, $chartRows);
$progressPoints = [];
$seriesCount = max(1, count($progressSeries) - 1);
foreach ($progressSeries as $index => $value) {
    $progressPoints[] = round(($index / $seriesCount) * 600, 2) . ',' . round(140 - (($value / 100) * 140), 2);
}
$progressPolyline = implode(' ', $progressPoints);

$cards = [
    [
        'title' => 'Total Project',
        'value' => $totalProjects,
        'note' => 'Project visible',
        'type' => 'spark_project',
        'color' => 'bg-blue-600',
        'percent' => 0,
    ],
    [
        'title' => 'Total Task',
        'value' => $totalTasks,
        'note' => 'Task visible',
        'type' => 'spark_task',
        'color' => 'bg-orange-600',
        'percent' => 0,
    ],
    [
        'title' => 'Task Berjalan',
        'value' => $open,
        'note' => 'Belum submit',
        'type' => 'progress',
        'color' => 'bg-blue-600',
        'percent' => $totalTasks ? round($open / $totalTasks * 100) : 0,
    ],
    [
        'title' => 'Dalam Review',
        'value' => $review,
        'note' => 'Menunggu validasi',
        'type' => 'progress',
        'color' => 'bg-amber-500',
        'percent' => $reviewRate,
    ],
    [
        'title' => 'Lewat Deadline',
        'value' => $overdue,
        'note' => 'Butuh tindakan',
        'type' => 'progress',
        'color' => 'bg-red-600',
        'percent' => $overdueRate,
    ],
];

if ($user['role'] === 'ADMIN') {
    $stmt = $pdo->prepare("SELECT p.status, COUNT(*) total FROM projects p WHERE {$projectWhere} AND p.deleted_at IS NULL GROUP BY p.status");
    $stmt->execute($params);
    $adminProjectStatus = array_fill_keys(['active','completed','review','draft'], 0);
    foreach ($stmt->fetchAll() as $row) if (isset($adminProjectStatus[$row['status']])) $adminProjectStatus[$row['status']] = (int)$row['total'];

    $stmt = $pdo->prepare("
        SELECT
          SUM(CASE WHEN t.status <> 'approved' AND t.deadline_at <= DATE_ADD(NOW(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) high_count,
          SUM(CASE WHEN t.status <> 'approved' AND t.deadline_at > DATE_ADD(NOW(), INTERVAL 3 DAY) AND t.deadline_at <= DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) medium_count,
          SUM(CASE WHEN t.status = 'approved' OR t.deadline_at > DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) low_count
        FROM tasks t
        JOIN projects p ON p.id = t.project_id
        WHERE {$projectWhere} AND t.deleted_at IS NULL
    ");
    $stmt->execute($params);
    $adminPriority = $stmt->fetch() ?: ['high_count'=>0,'medium_count'=>0,'low_count'=>0];

    $adminDays = [];
    for ($i=$period-1; $i>=0; $i--) {
        $month = date('Y-m', strtotime("first day of -{$i} month"));
        $adminDays[$month] = ['label'=>date('M y',strtotime($month . '-01')),'projects'=>0,'tasks'=>0,'completed'=>0];
    }
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(a.created_at, '%Y-%m') day,
          SUM(a.action='Project dibuat') projects,
          SUM(a.action='Task dibuat') tasks,
          SUM(a.action='Task verified') completed
        FROM activity_logs a
        WHERE (a.user_id = ? OR a.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?))
          AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL {$period} MONTH)
        GROUP BY DATE_FORMAT(a.created_at, '%Y-%m')
    ");
    $stmt->execute([$user['id'], $user['id']]);
    foreach ($stmt->fetchAll() as $row) if(isset($adminDays[$row['day']])) {
        $adminDays[$row['day']]['projects']=(int)$row['projects'];
        $adminDays[$row['day']]['tasks']=(int)$row['tasks'];
        $adminDays[$row['day']]['completed']=(int)$row['completed'];
    }
    include __DIR__ . '/../actions/views/admin-dashboard-view.php';
    return;
}
?>

<div class="mobile-dashboard">
    <section class="mobile-dashboard-hero">
        <div class="flex items-center justify-between">
            <button class="mobile-menu-button" type="button" aria-label="Buka menu"><span></span><span></span><span></span></button>
            <a class="flex items-center gap-3" href="index.php?page=dashboard">
                <img class="mobile-dashboard-logo" src="<?= e(app_url('assets/images/iti-logo.jpg')) ?>" alt="Logo Institut Teknologi Indonesia">
                <span class="text-[13px] font-black uppercase leading-tight tracking-wide text-white">ITI Project<br>Manager</span>
            </a>
            <a class="relative grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white" href="index.php?page=notifications">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg>
                <?php if($unreadSidebarCount): ?><span class="absolute -right-1 -top-1 grid h-6 min-w-6 place-items-center rounded-full bg-orange-500 px-1 text-[10px] font-black"><?= e($unreadSidebarCount) ?></span><?php endif; ?>
            </a>
        </div>
        <div class="mt-7"><h1 class="text-[25px] font-black text-white">Halo, <?= e(explode(' ', trim($user['name']))[0]) ?></h1><p class="mt-1.5 text-[13px] text-slate-300">Berikut ringkasan aktivitas hari ini.</p></div>
    </section>

    <div class="mobile-dashboard-content">
        <section class="mobile-dashboard-card -mt-12">
            <div class="flex items-center justify-between"><h2>Ringkasan Overview</h2><a href="index.php?page=projects">Lihat semua ›</a></div>
            <div class="mobile-overview-grid mt-5">
                <?php $mobileOverview=[
                    ['Project Aktif',$totalProjects,$projectTrend,'folder','orange'],
                    ['Task Aktif',$open+$review+$rejected,$taskTrend,'task','blue'],
                    ['Deadline Dekat',$dueNextSevenDays,$dueToday,'clock','amber'],
                    ['Progress Rata-rata',$completionRate,$completionRate,'check','green'],
                ]; foreach($mobileOverview as [$label,$value,$note,$icon,$tone]): ?>
                <div class="mobile-overview-item">
                    <span class="mobile-overview-icon tone-<?= e($tone) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?php if($icon==='folder'): ?><path d="M3 7h7l2 2h9v10H3V7Z"/><?php elseif($icon==='task'): ?><path d="M9 5H6v16h12V5h-3M9 3h6v4H9z"/><path d="m9 14 2 2 4-5"/><?php elseif($icon==='clock'): ?><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/><?php else: ?><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 6-7"/><?php endif; ?></svg></span>
                    <strong><?= $label==='Progress Rata-rata' ? e($value).'%' : e($value) ?></strong><b><?= e($label) ?></b><small class="<?= $tone==='amber'?'text-orange-500':'text-green-600' ?>">↑ <?= e(abs($note)) ?> <?= $tone==='amber'?'hari ini':'dari periode lalu' ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mobile-dashboard-card">
            <div class="flex items-center justify-between"><h2>Project Terbaru</h2><a href="index.php?page=projects">Lihat semua</a></div>
            <div class="mt-3 divide-y divide-slate-100">
                <?php foreach(array_slice($projects,0,3) as $index=>$p): $mobileProgress=(int)$p['task_count']>0?(int)round((int)$p['approved_count']/max(1,(int)$p['task_count'])*100):0; ?>
                <a class="mobile-project-row" href="actions/project-detail.php?id=<?= e($p['id']) ?>">
                    <span class="mobile-project-cover cover-<?= e($index%3) ?>"><b><?= e(strtoupper(implode(' ',array_slice(explode(' ',$p['title']),0,2)))) ?></b><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="4" y="5" width="16" height="13" rx="1"/><path d="M8 21h8m-4-3v3"/></svg></span>
                    <span class="min-w-0 flex-1"><b class="block truncate text-sm"><?= e($p['title']) ?></b><small class="mt-1 block truncate text-slate-500"><?= e($p['description'] ?: 'Project Institut Teknologi Indonesia') ?></small><small class="mt-2 flex items-center gap-2"><i class="h-2 w-2 rounded-full bg-orange-500"></i><?= e(ucfirst($p['status'])) ?></small></span>
                    <span class="w-20"><strong class="text-lg text-orange-500"><?= e($mobileProgress) ?>%</strong><i class="mt-2 block h-1.5 rounded-full bg-slate-100"><em class="block h-full rounded-full bg-orange-500" style="width:<?= e($mobileProgress) ?>%"></em></i></span><span class="text-2xl text-slate-400">›</span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mobile-dashboard-card">
            <div class="flex items-center justify-between"><h2>Task Mendatang</h2><a href="index.php?page=tasks">Lihat semua</a></div>
            <div class="mt-3 divide-y divide-slate-100">
                <?php foreach(array_slice($deadlineRows,0,3) as $d): $mobileDays=(int)ceil((strtotime($d['deadline_at'])-time())/86400); $priority=$mobileDays<=3?'High':($mobileDays<=7?'Medium':'Low'); ?>
                <a class="mobile-task-row" href="actions/task-detail.php?id=<?= e($d['id']) ?>"><span class="h-6 w-6 rounded-md border-2 border-slate-400"></span><span class="min-w-0 flex-1"><b class="block truncate text-sm"><?= e($d['title']) ?></b><small class="block truncate text-slate-500"><?= e($d['project_title']) ?></small></span><time class="<?= $priority==='High'?'text-orange-500':($priority==='Medium'?'text-blue-500':'text-green-600') ?>"><?= e(date('d M Y',strtotime($d['deadline_at']))) ?></time><span class="mobile-priority priority-<?= strtolower($priority) ?>"><?= e($priority) ?></span><span class="text-xl text-slate-400">›</span></a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <nav class="mobile-bottom-nav">
        <a class="is-active" href="index.php?page=dashboard"><svg viewBox="0 0 24 24"><path d="M3 11 12 3l9 8v10h-6v-6H9v6H3V11Z"/></svg><span>Dashboard</span></a>
        <a href="index.php?page=projects"><svg viewBox="0 0 24 24"><path d="M3 7h7l2 2h9v10H3V7Z"/></svg><span>Project</span></a>
        <a class="mobile-add-button" href="index.php?page=<?= can_create_project($user['role'])?'projects':'tasks' ?>"><b>+</b><span>Tambah</span></a>
        <a href="index.php?page=tasks"><svg viewBox="0 0 24 24"><path d="M9 5H6v16h12V5h-3M9 3h6v4H9z"/><path d="m9 14 2 2 4-5"/></svg><span>Task</span></a>
        <a href="index.php?page=profile"><svg viewBox="0 0 24 24"><path d="M20 21a8 8 0 0 0-16 0m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg><span>Akun</span></a>
    </nav>
</div>

<div class="dashboard-shell text-[12px]">
    <header class="dashboard-reveal mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between" style="--reveal-delay:0ms">
        <div>
            <h2 class="text-[27px] font-black tracking-tight text-slate-900">Dashboard Overview</h2>
            <p class="mt-0.5 text-[14px] text-slate-500">Selamat datang kembali, <strong class="text-orange-600"><?= e($user['name']) ?></strong></p>
        </div>
        <div class="flex items-center gap-3">
            <form class="dashboard-top-search hidden w-[360px] items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-slate-400 lg:flex" method="get">
                <input type="hidden" name="page" value="dashboard">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input class="w-full bg-transparent text-[11px] outline-none" name="q" value="<?= e($search) ?>" placeholder="Cari project, tugas, atau pengguna...">
                <button class="rounded-lg bg-orange-500 px-3 py-1.5 text-[10px] font-black text-white transition hover:bg-orange-600 focus:outline-none focus:ring-2 focus:ring-orange-300" type="submit">Cari</button>
            </form>
            <a class="dashboard-interactive-icon relative grid h-10 w-10 place-items-center rounded-xl border border-slate-200 bg-white" href="index.php?page=notifications">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg>
                <?php if ($unreadSidebarCount): ?><span class="absolute -right-1 -top-1 rounded-full bg-orange-500 px-1.5 text-[9px] font-black text-white"><?= e($unreadSidebarCount) ?></span><?php endif; ?>
            </a>
            <details class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-orange-100 font-black text-orange-600"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
                    <span class="hidden sm:block"><b class="block text-xs"><?= e($user['name']) ?></b><span class="text-[10px] text-slate-500"><?= e(role_label($user['role'])) ?></span></span>
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="absolute right-0 z-20 mt-2 w-44 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                    <a class="block rounded-lg px-3 py-2 font-bold hover:bg-slate-50" href="index.php?page=profile">Buka Profil</a>
                    <a class="block rounded-lg px-3 py-2 font-bold text-red-600 hover:bg-red-50" href="logout.php">Logout</a>
                </div>
            </details>
        </div>
    </header>

    <?php if ($search !== ''): ?>
        <section class="dashboard-card dashboard-reveal mb-4 p-4" style="--reveal-delay:70ms">
            <div class="mb-3 flex items-center justify-between"><h3 class="dashboard-section-title">Hasil pencarian "<?= e($search) ?>"</h3><a class="font-bold text-orange-500" href="index.php?page=dashboard">Tutup</a></div>
            <div class="grid gap-4 md:grid-cols-3">
                <div><b class="text-slate-500">Projects</b><?php foreach ($searchResults['projects'] as $result): ?><a class="mt-2 block rounded-lg bg-slate-50 p-2 font-bold hover:bg-orange-50" href="actions/project-detail.php?id=<?= e($result['id']) ?>"><?= e($result['title']) ?></a><?php endforeach; ?><?php if (!$searchResults['projects']): ?><p class="mt-2 text-slate-400">Tidak ditemukan.</p><?php endif; ?></div>
                <div><b class="text-slate-500">Tasks</b><?php foreach ($searchResults['tasks'] as $result): ?><a class="mt-2 block rounded-lg bg-slate-50 p-2 font-bold hover:bg-orange-50" href="actions/task-detail.php?id=<?= e($result['id']) ?>"><?= e($result['title']) ?><small class="block font-normal text-slate-400"><?= e($result['project_title']) ?></small></a><?php endforeach; ?><?php if (!$searchResults['tasks']): ?><p class="mt-2 text-slate-400">Tidak ditemukan.</p><?php endif; ?></div>
                <div><b class="text-slate-500">Users</b><?php foreach ($searchResults['users'] as $result): ?><a class="mt-2 block rounded-lg bg-slate-50 p-2 font-bold hover:bg-orange-50" href="index.php?page=users"><?= e($result['name']) ?><small class="block font-normal text-slate-400"><?= e($result['role']) ?></small></a><?php endforeach; ?><?php if (!$searchResults['users']): ?><p class="mt-2 text-slate-400">Tidak ditemukan atau tidak memiliki akses.</p><?php endif; ?></div>
            </div>
        </section>
    <?php endif; ?>

    <?php
    $overviewCards = [
        ['Total Projects', $totalProjects, ($projectTrend >= 0 ? '+' : '') . $projectTrend . '% dari bulan lalu', 'folder', 'index.php?page=projects'],
        ['Total Tasks', $totalTasks, ($taskTrend >= 0 ? '+' : '') . $taskTrend . '% dari bulan lalu', 'task', 'index.php?page=tasks'],
        ['Total Users', $totalUsers, ($userTrend >= 0 ? '+' : '') . $userTrend . '% dari bulan lalu', 'users', can_manage_users($user['role']) ? 'index.php?page=users' : 'index.php?page=profile'],
        ['Deadline Mendatang', $dueNextSevenDays, 'Dalam 7 hari ke depan', 'calendar', 'index.php?page=deadlines'],
    ];
    ?>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($overviewCards as $cardIndex => [$label, $value, $note, $icon, $url]): ?>
            <a class="dashboard-card dashboard-reveal flex min-h-[118px] items-center gap-5 px-5 py-4" style="--reveal-delay:<?= e(80 + ($cardIndex * 55)) ?>ms" href="<?= e($url) ?>">
                <span class="dashboard-stat-icon">
                    <?php if ($icon === 'calendar'): ?><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18"/></svg>
                    <?php elseif ($icon === 'users'): ?><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                    <?php elseif ($icon === 'task'): ?><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 2h6v4H9V2Zm0 10 2 2 4-5"/></svg>
                    <?php else: ?><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h7l2 2h9v10H3V7Zm0 0V5h7l2 2"/></svg><?php endif; ?>
                </span>
                <div><p class="text-[13px] font-medium text-slate-500"><?= e($label) ?></p><strong class="block text-[27px] leading-tight font-black text-slate-900"><?= e($value) ?></strong><span class="text-[10px] <?= $icon === 'calendar' ? 'text-orange-500' : 'text-green-600' ?>"><?= e($note) ?></span></div>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-12">
        <div class="dashboard-card dashboard-reveal p-5 xl:col-span-5" style="--reveal-delay:320ms">
            <div class="flex items-center justify-between"><h3 class="dashboard-section-title">Progress Project <span class="font-normal text-slate-400">(<?= e($period) ?> Bulan Terakhir)</span></h3><form method="get"><input type="hidden" name="page" value="dashboard"><select class="rounded-lg border border-slate-200 px-3 py-1.5 text-[10px] font-bold outline-none" name="period" onchange="this.form.submit()"><?php foreach ($allowedPeriods as $option): ?><option value="<?= e($option) ?>" <?= $period === $option ? 'selected' : '' ?>><?= e($option) ?> Bulan Terakhir</option><?php endforeach; ?></select></form></div>
            <div class="relative mt-4 h-[205px] pl-8">
                <div class="absolute bottom-0 left-8 right-0 top-0 border-b border-l border-slate-200">
                    <i class="dashboard-gridline absolute left-0 right-0 top-0"></i><i class="dashboard-gridline absolute left-0 right-0 top-1/4"></i><i class="dashboard-gridline absolute left-0 right-0 top-1/2"></i><i class="dashboard-gridline absolute left-0 right-0 top-3/4"></i>
                </div>
                <span class="absolute left-0 top-0 w-7 text-right text-[9px] text-slate-500">100%</span><span class="absolute left-0 top-1/4 w-7 text-right text-[9px] text-slate-500">75%</span><span class="absolute left-0 top-1/2 w-7 text-right text-[9px] text-slate-500">50%</span><span class="absolute left-0 top-3/4 w-7 text-right text-[9px] text-slate-500">25%</span>
                <div class="absolute bottom-0 left-8 right-0 top-0 flex items-end justify-around px-4">
                    <?php foreach ($chartRows as $index => $row): $pct = $progressSeries[$index]; ?>
                        <div class="relative h-full flex-1">
                            <span class="absolute left-1/2 -translate-x-1/2 text-[9px] font-black" style="bottom: calc(<?= e($pct) ?>% + 8px)"><?= e($pct) ?>%</span>
                            <button class="dashboard-progress-point absolute left-1/2 h-2.5 w-2.5 -translate-x-1/2 rounded-full border-2 border-orange-500 bg-white" type="button" style="bottom: <?= e($pct) ?>%" data-label="<?= e($row['label']) ?>" data-percent="<?= e($pct) ?>" aria-label="<?= e($row['label']) ?>: <?= e($pct) ?> persen"></button>
                            <span class="absolute -bottom-5 left-1/2 -translate-x-1/2 whitespace-nowrap text-[9px] font-bold text-slate-500"><?= e($row['label']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <svg class="pointer-events-none absolute left-[11%] right-[5%] top-[18%] h-[68%] w-[84%] overflow-visible" viewBox="0 0 600 140" preserveAspectRatio="none" aria-hidden="true"><polyline class="dashboard-progress-line" points="<?= e($progressPolyline) ?>" fill="none" stroke="#f15a24" stroke-width="3" vector-effect="non-scaling-stroke"/></svg>
            </div>
            <p class="mt-8 border-t border-slate-100 pt-4 text-center text-[10px]"><i class="mr-2 inline-block h-2 w-2 rounded-full border-2 border-orange-500"></i>Rata-rata Progress</p>
        </div>

        <div class="dashboard-card dashboard-reveal p-5 xl:col-span-3" style="--reveal-delay:380ms">
            <h3 class="dashboard-section-title text-center">Status Tugas</h3>
            <div class="mt-5 flex items-center justify-center gap-5">
                <div class="task-donut-wrap h-44 w-44 shrink-0" id="task-donut">
                    <svg class="h-full w-full -rotate-90" viewBox="0 0 120 120" role="img" aria-label="Grafik status tugas">
                        <circle class="task-donut-track" cx="60" cy="60" r="42"/>
                        <?php $donutOffset = 0; ?>
                        <?php foreach ($donutSegments as $index => $segment): ?>
                            <circle class="task-donut-segment"
                                cx="60" cy="60" r="42" pathLength="100"
                                stroke="<?= e($segment['color']) ?>"
                                stroke-dasharray="<?= e($segment['percent']) ?> <?= e(100 - $segment['percent']) ?>"
                                stroke-dashoffset="<?= e(-$donutOffset) ?>"
                                style="animation-delay: <?= e($index * 100) ?>ms"
                                tabindex="0"
                                data-key="<?= e($segment['key']) ?>"
                                data-label="<?= e($segment['label']) ?>"
                                data-value="<?= e($segment['value']) ?>"
                                data-percent="<?= e(round($segment['percent'])) ?>"/>
                            <?php $donutOffset += $segment['percent']; ?>
                        <?php endforeach; ?>
                    </svg>
                    <div class="task-donut-center pointer-events-none absolute inset-0 grid place-items-center text-center">
                        <span><strong class="block text-[26px] font-black text-slate-900" id="donut-center-value"><?= e($totalTasks) ?></strong><span class="text-[11px] text-slate-500" id="donut-center-label">Total Tugas</span></span>
                    </div>
                    <div class="task-donut-tooltip" id="task-donut-tooltip"></div>
                </div>
                <div class="space-y-1 text-[10px]">
                    <?php foreach ($donutSegments as $segment): ?>
                        <button class="task-donut-legend flex w-full items-center rounded-lg px-2 py-2 text-left" type="button" data-key="<?= e($segment['key']) ?>" data-label="<?= e($segment['label']) ?>" data-value="<?= e($segment['value']) ?>" data-percent="<?= e(round($segment['percent'])) ?>">
                            <i class="mr-2 inline-block h-2.5 w-2.5 rounded-full" style="background:<?= e($segment['color']) ?>"></i>
                            <span class="flex-1"><?= e($segment['label']) ?></span>
                            <b class="ml-3"><?= e($segment['value']) ?> (<?= e(round($segment['percent'])) ?>%)</b>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="mt-7 text-center">
                <p class="text-[11px] font-bold text-slate-500">Total Tugas</p>
                <strong class="mt-1 block text-[24px] font-black text-slate-900"><?= e($totalTasks) ?></strong>
            </div>
        </div>

        <div class="dashboard-card dashboard-reveal p-5 xl:col-span-4" style="--reveal-delay:440ms">
            <h3 class="dashboard-section-title">Aktivitas Terbaru</h3>
            <div class="dashboard-compact-list mt-2">
                <?php foreach (array_slice($activityRows, 0, 5) as $a): ?><a class="dashboard-list-item flex gap-3 py-3" href="<?= e(get_activity_target_url($a)) ?>"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-green-100 text-green-600"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg></span><div class="min-w-0 flex-1"><b class="block truncate text-[11px]"><?= e($a['action']) ?></b><p class="truncate text-[10px] text-slate-500"><?= e($a['detail']) ?></p></div><time class="whitespace-nowrap text-[9px] text-slate-400"><?= e(date('d M H:i', strtotime($a['created_at']))) ?></time></a><?php endforeach; ?>
                <?php if (!$activityRows): ?><p class="py-6 text-center text-slate-400">Belum ada aktivitas.</p><?php endif; ?>
            </div>
        </div>
    </section>

    <section class="mt-4 grid gap-4 xl:grid-cols-5">
        <div class="dashboard-card dashboard-reveal overflow-hidden p-4 xl:col-span-3" style="--reveal-delay:500ms">
            <h3 class="dashboard-section-title">Project Aktif</h3>
            <div class="mt-3 overflow-x-hidden"><table class="w-full table-fixed text-left text-[10px]"><thead class="border-b border-slate-200 text-slate-500"><tr><th class="w-[36%] py-2">Project</th><th class="w-[17%]">Manager</th><th class="w-[24%]">Progress</th><th class="w-[13%]">Status</th><th class="w-[10%]">Deadline</th></tr></thead><tbody class="divide-y divide-slate-100">
                <?php foreach ($projects as $index => $p): $projectProgress = ((int)$p['task_count']) > 0 ? round(((int)$p['approved_count'] / max(1, (int)$p['task_count'])) * 100) : 0; $folderColors = ['bg-blue-100 text-blue-600','bg-green-100 text-green-600','bg-purple-100 text-purple-600','bg-amber-100 text-amber-600','bg-red-100 text-red-600']; ?><tr class="dashboard-table-row"><td class="py-2.5"><a class="flex items-center gap-2 font-bold" href="actions/project-detail.php?id=<?= e($p['id']) ?>"><span class="grid h-6 w-6 place-items-center rounded <?= e($folderColors[$index % count($folderColors)]) ?>"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h7l2 2h9v10H3V7Z"/></svg></span><?= e($p['title']) ?></a></td><td><?= e($p['owner_name']) ?></td><td><div class="flex items-center gap-2"><span class="h-1.5 w-20 rounded-full bg-slate-100"><i class="dashboard-progress-fill block h-1.5 rounded-full bg-orange-500" style="width:<?= e($projectProgress) ?>%"></i></span><?= e($projectProgress) ?>%</div></td><td><?= status_badge($p['status']) ?></td><td><?= e(date('d M Y', strtotime($p['deadline_at']))) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <a class="mt-4 flex items-center justify-center gap-2 text-[10px] font-bold text-orange-500" href="index.php?page=projects">Lihat semua project <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a>
        </div>
        <div class="dashboard-card dashboard-reveal p-4 xl:col-span-2" style="--reveal-delay:560ms">
            <div class="flex justify-between"><h3 class="dashboard-section-title">Deadline Mendatang</h3><a class="text-[10px] font-bold text-orange-500" href="index.php?page=deadlines">Lihat semua</a></div>
            <div class="dashboard-compact-list mt-2"><?php foreach ($deadlineRows as $d): $days = max(0, (int)ceil((strtotime($d['deadline_at']) - time()) / 86400)); ?><a class="dashboard-list-item flex items-center gap-3 py-2" href="actions/task-detail.php?id=<?= e($d['id']) ?>"><span class="rounded-lg border border-slate-200 px-2 py-1 text-center text-[9px] font-black"><?= e(date('d', strtotime($d['deadline_at']))) ?><small class="block"><?= e(strtoupper(date('M', strtotime($d['deadline_at'])))) ?></small></span><span class="min-w-0 flex-1"><b class="block truncate text-[10px]"><?= e($d['project_title']) ?> - <?= e($d['title']) ?></b><small class="text-[9px] text-slate-500"><?= e(get_task_status_label($d['status'])) ?></small></span><span class="rounded-md bg-amber-100 px-2 py-1 text-[9px] font-bold text-orange-500"><?= e($days) ?> hari lagi</span></a><?php endforeach; ?></div>
        </div>
    </section>
</div>
<script>
(() => {
    const shell = document.querySelector('.dashboard-shell');
    if (shell) requestAnimationFrame(() => shell.classList.add('is-ready'));
    const mobileMenuButton = document.querySelector('.mobile-menu-button');
    const mobileDrawer = document.querySelector('.mobile-navigation-drawer');
    const mobileDrawerBackdrop = document.querySelector('.mobile-drawer-backdrop');
    const setMobileDrawer = open => {
        mobileDrawer?.classList.toggle('is-open', open);
        mobileDrawerBackdrop?.classList.toggle('is-open', open);
        document.body.style.overflow = open ? 'hidden' : '';
    };
    mobileMenuButton?.addEventListener('click', () => setMobileDrawer(true));
    document.querySelector('.mobile-drawer-close')?.addEventListener('click', () => setMobileDrawer(false));
    mobileDrawerBackdrop?.addEventListener('click', () => setMobileDrawer(false));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') setMobileDrawer(false); });

    const progressTooltip = document.createElement('div');
    progressTooltip.className = 'dashboard-progress-tooltip';
    document.body.appendChild(progressTooltip);
    document.querySelectorAll('.dashboard-progress-point').forEach(point => {
        const showProgressTooltip = event => {
            progressTooltip.innerHTML = `<strong>${point.dataset.label}</strong><br>${point.dataset.percent}% selesai`;
            const rect = point.getBoundingClientRect();
            progressTooltip.style.left = `${event?.clientX || rect.left + rect.width / 2}px`;
            progressTooltip.style.top = `${event?.clientY || rect.top}px`;
            progressTooltip.classList.add('is-visible');
        };
        point.addEventListener('pointerenter', showProgressTooltip);
        point.addEventListener('pointermove', showProgressTooltip);
        point.addEventListener('pointerleave', () => progressTooltip.classList.remove('is-visible'));
        point.addEventListener('focus', showProgressTooltip);
        point.addEventListener('blur', () => progressTooltip.classList.remove('is-visible'));
    });

    if (window.matchMedia('(pointer: fine)').matches && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.querySelectorAll('.dashboard-shell .dashboard-card').forEach(card => {
            card.addEventListener('pointermove', event => {
                const rect = card.getBoundingClientRect();
                const rotateX = ((event.clientY - rect.top) / rect.height - .5) * -1.2;
                const rotateY = ((event.clientX - rect.left) / rect.width - .5) * 1.2;
                card.style.transform = `translateY(-3px) perspective(900px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });
            card.addEventListener('pointerleave', () => card.style.removeProperty('transform'));
        });
    }

    const donut = document.getElementById('task-donut');
    if (!donut) return;

    const segments = [...donut.querySelectorAll('.task-donut-segment')];
    const legends = [...document.querySelectorAll('.task-donut-legend')];
    const tooltip = document.getElementById('task-donut-tooltip');
    const centerValue = document.getElementById('donut-center-value');
    const centerLabel = document.getElementById('donut-center-label');
    const total = <?= (int)$totalTasks ?>;

    const activate = (key, source, pointerEvent = null) => {
        const data = source.dataset;
        segments.forEach(segment => {
            segment.classList.toggle('is-active', segment.dataset.key === key);
            segment.classList.toggle('is-muted', segment.dataset.key !== key);
        });
        legends.forEach(legend => legend.classList.toggle('is-active', legend.dataset.key === key));
        centerValue.textContent = data.value;
        centerLabel.textContent = data.label;
        tooltip.innerHTML = `<strong>${data.label}</strong><br>${data.value} tugas · ${data.percent}%`;
        tooltip.classList.add('is-visible');

        if (pointerEvent) {
            const bounds = donut.getBoundingClientRect();
            tooltip.style.left = `${pointerEvent.clientX - bounds.left}px`;
            tooltip.style.top = `${pointerEvent.clientY - bounds.top}px`;
        } else {
            tooltip.style.left = '50%';
            tooltip.style.top = '18%';
        }
    };

    const reset = () => {
        segments.forEach(segment => segment.classList.remove('is-active', 'is-muted'));
        legends.forEach(legend => legend.classList.remove('is-active'));
        centerValue.textContent = total;
        centerLabel.textContent = 'Total Tugas';
        tooltip.classList.remove('is-visible');
    };

    segments.forEach(segment => {
        segment.addEventListener('pointerenter', event => activate(segment.dataset.key, segment, event));
        segment.addEventListener('pointermove', event => activate(segment.dataset.key, segment, event));
        segment.addEventListener('pointerleave', reset);
        segment.addEventListener('focus', () => activate(segment.dataset.key, segment));
        segment.addEventListener('blur', reset);
    });

    legends.forEach(legend => {
        legend.addEventListener('pointerenter', () => activate(legend.dataset.key, legend));
        legend.addEventListener('pointerleave', reset);
        legend.addEventListener('focus', () => activate(legend.dataset.key, legend));
        legend.addEventListener('blur', reset);
    });
})();
</script>
<?php return; ?>

<div class="dashboard-shell superadmin-dashboard">
<div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
        <h2 class="text-2xl font-black tracking-tight text-slate-900">Dashboard Overview</h2>
        <p class="mt-1 text-sm text-slate-500">Selamat datang kembali, <strong class="text-orange-600"><?= e($user['name']) ?></strong></p>
    </div>
    <div class="flex items-center gap-3">
        <label class="dashboard-top-search hidden min-w-[330px] items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-slate-400 transition xl:flex">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
            <input class="w-full border-0 bg-transparent text-xs outline-none placeholder:text-slate-400" type="search" placeholder="Cari project, tugas, atau pengguna..." aria-label="Cari">
        </label>
        <a class="relative grid h-11 w-11 place-items-center rounded-xl border border-slate-200 bg-white text-slate-700" href="index.php?page=notifications" aria-label="Notifikasi">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg>
            <?php if ($unreadSidebarCount > 0): ?><span class="absolute -right-1 -top-1 rounded-full bg-orange-500 px-1.5 py-0.5 text-[9px] font-black text-white"><?= e($unreadSidebarCount) ?></span><?php endif; ?>
        </a>
        <span class="grid h-10 w-10 place-items-center rounded-full bg-orange-100 text-sm font-black text-orange-700"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
        <b class="hidden text-xs sm:block"><?= e($user['name']) ?></b>
        <span class="text-slate-500"> • <?= e($user['unit'] ?? '-') ?></span>
    </div>
</div>

<div class="superadmin-kpi-grid grid gap-4 md:grid-cols-2 xl:grid-cols-5">
    <?php foreach ($cards as $card): ?>
        <div class="dashboard-card superadmin-kpi-card p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold text-slate-500"><?= e($card['title']) ?></p>
                    <strong class="mt-1 block text-2xl font-black text-slate-900"><?= e($card['value']) ?></strong>
                </div>
                <span class="rounded-full bg-orange-50 px-3 py-1 text-[10px] font-black text-orange-600"><?= e($card['note']) ?></span>
            </div>

            <?php if ($card['type'] === 'spark_project' || $card['type'] === 'spark_task'): ?>
                <?php $key = $card['type'] === 'spark_project' ? 'project_count' : 'task_count'; $maxSpark = $card['type'] === 'spark_project' ? $maxSparkProject : $maxSparkTask; ?>
                <div class="mt-3 flex h-10 items-end gap-1 rounded-lg bg-slate-50 px-2 py-1.5">
                    <?php foreach ($chartRows as $row): ?>
                        <?php $h = max(6, round(((int)$row[$key] / $maxSpark) * 38)); ?>
                        <div class="flex-1 rounded-t <?= e($card['color']) ?>" style="height: <?= e($h) ?>px" title="<?= e($row['label']) ?>: <?= e($row[$key]) ?>"></div>
                    <?php endforeach; ?>
                </div>
                <p class="mt-1.5 text-[10px] font-bold text-slate-400">Trend <?= e($period) ?> bulan terakhir</p>
            <?php else: ?>
                <div class="mt-3">
                    <div class="mb-2 flex items-center justify-between text-xs font-bold text-slate-500">
                        <span>Persentase dari total task visible</span>
                        <span><?= e($card['percent']) ?>%</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100">
                        <div class="h-2 rounded-full <?= e($card['color']) ?>" style="width: <?= e(min(100, (int)$card['percent'])) ?>%"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="superadmin-summary-grid mt-5 grid gap-4 md:grid-cols-4">
    <div class="dashboard-card superadmin-summary-card p-4">
        <p class="text-sm font-bold text-slate-500">Completion Rate</p>
        <strong class="mt-2 block text-3xl font-black text-green-700"><?= e($completionRate) ?>%</strong>
        <p class="text-xs font-bold text-slate-400"><?= e($done) ?> dari <?= e($totalTasks) ?> task verified</p>
    </div>
    <div class="dashboard-card superadmin-summary-card p-4">
        <p class="text-sm font-bold text-slate-500">Perlu Revisi</p>
        <strong class="mt-2 block text-3xl font-black text-red-700"><?= e($rejected) ?></strong>
        <p class="text-xs font-bold text-slate-400">Task berstatus rejected</p>
    </div>
    <div class="dashboard-card superadmin-summary-card p-4">
        <p class="text-sm font-bold text-slate-500">Deadline Hari Ini</p>
        <strong class="mt-2 block text-3xl font-black text-orange-700"><?= e($dueToday) ?></strong>
        <p class="text-xs font-bold text-slate-400">Belum approved</p>
    </div>
    <div class="dashboard-card superadmin-summary-card p-4">
        <p class="text-sm font-bold text-slate-500">3 Hari ke Depan</p>
        <strong class="mt-2 block text-3xl font-black text-blue-700"><?= e($dueSoon) ?></strong>
        <p class="text-xs font-bold text-slate-400">Butuh monitoring</p>
    </div>
</div>

<div class="superadmin-main-grid mt-6 grid gap-6 xl:grid-cols-3">
    <section class="dashboard-card superadmin-chart-card xl:col-span-2 p-5">
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-lg font-black">Grafik Aktivitas Proyek Kampus</h3>
                <p class="text-sm text-slate-500">Grouped bar chart real-data: Aktif / Review / Selesai per bulan.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ([1,3,6,12] as $p): ?>
                    <a class="rounded-full px-3 py-1 text-xs font-bold <?= $period === $p ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>" href="index.php?page=dashboard&period=<?= $p ?>"><?= $p ?> bulan</a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="overflow-x-auto rounded-3xl bg-gradient-to-b from-slate-50 to-white p-4">
            <div class="flex min-w-[720px] items-end gap-5 border-b border-slate-200 pt-6">
                <?php foreach ($chartRows as $row): ?>
                    <div class="flex flex-1 flex-col items-center gap-3">
                        <div class="flex h-64 w-full items-end justify-center gap-2 rounded-2xl border border-slate-100 bg-white/80 px-3 py-4 shadow-sm">
                            <?php
                                $bars = [
                                    ['key' => 'active_count', 'color' => 'bg-blue-600', 'label' => 'Aktif'],
                                    ['key' => 'review_count', 'color' => 'bg-amber-500', 'label' => 'Review'],
                                    ['key' => 'done_count', 'color' => 'bg-green-600', 'label' => 'Selesai'],
                                ];
                            ?>
                            <?php foreach ($bars as $bar): ?>
                                <?php $value = (int)$row[$bar['key']]; $height = $value ? max(16, round(($value / $maxChart) * 200)) : 8; ?>
                                <div class="flex flex-col items-center gap-2">
                                    <span class="text-[10px] font-black text-slate-500"><?= e($value) ?></span>
                                    <div class="w-6 rounded-t-xl <?= e($bar['color']) ?> shadow-sm" style="height: <?= e($height) ?>px" title="<?= e($row['label']) ?> • <?= e($bar['label']) ?>: <?= e($value) ?>"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <span class="text-xs font-black text-slate-500"><?= e($row['label']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-4 text-xs font-bold text-slate-600">
            <span><i class="mr-2 inline-block h-3 w-3 rounded bg-blue-600"></i>Aktif</span>
            <span><i class="mr-2 inline-block h-3 w-3 rounded bg-amber-500"></i>Review</span>
            <span><i class="mr-2 inline-block h-3 w-3 rounded bg-green-600"></i>Selesai</span>
            <span class="ml-auto text-slate-400">Max scale: <?= e($maxChart) ?> task</span>
        </div>
    </section>

    <section class="dashboard-card superadmin-deadline-card p-5">
        <h3 class="text-lg font-black">Deadline & Tindak Lanjut</h3>
        <div class="superadmin-scroll-list mt-5 space-y-3">
            <?php foreach ($deadlineRows as $d): ?>
                <a href="actions/task-detail.php?id=<?= e($d['id']) ?>" class="block rounded-2xl border border-slate-100 p-4 hover:bg-slate-50 <?= strtotime($d['deadline_at']) < time() ? 'bg-red-50 border-red-100' : '' ?>">
                    <strong class="block text-sm"><?= e($d['title']) ?></strong>
                    <small class="text-slate-500"><?= e($d['project_title']) ?> • <?= e(date('d M Y H:i', strtotime($d['deadline_at']))) ?></small>
                    <div class="mt-2"><?= status_badge($d['status']) ?></div>
                </a>
            <?php endforeach; ?>
            <?php if (!$deadlineRows): ?>
                <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">Tidak ada deadline aktif.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="superadmin-lower-grid mt-6 grid gap-6 xl:grid-cols-2">
    <section class="dashboard-card superadmin-list-card p-5">
        <h3 class="text-lg font-black">Project Terdekat</h3>
        <div class="superadmin-scroll-list mt-5 space-y-3">
            <?php foreach ($projects as $p): ?>
                <?php $projectProgress = ((int)$p['task_count']) > 0 ? round(((int)$p['approved_count'] / max(1, (int)$p['task_count'])) * 100) : 0; ?>
                <a href="actions/project-detail.php?id=<?= e($p['id']) ?>" class="block rounded-2xl border border-slate-100 p-4 hover:bg-slate-50">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <strong class="block text-sm"><?= e($p['title']) ?></strong>
                            <small class="text-slate-500">Owner: <?= e($p['owner_name']) ?> • Deadline <?= e(date('d M Y', strtotime($p['deadline_at']))) ?></small>
                        </div>
                        <span class="text-sm font-black text-orange-700"><?= e($projectProgress) ?>%</span>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-orange-600" style="width: <?= e($projectProgress) ?>%"></div></div>
                </a>
            <?php endforeach; ?>
            <?php if (!$projects): ?><p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada project visible untuk role ini.</p><?php endif; ?>
        </div>
    </section>

    <section class="dashboard-card superadmin-list-card p-5">
        <h3 class="text-lg font-black">Aktivitas Terbaru</h3>
        <div class="superadmin-scroll-list mt-5 space-y-3">
            <?php foreach ($activityRows as $a): ?>
                <div class="rounded-2xl border border-slate-100 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <strong class="block text-sm"><?= e($a['action']) ?></strong>
                            <p class="text-sm text-slate-500"><?= e($a['detail']) ?></p>
                            <small class="text-slate-400"><?= e($a['name']) ?> • <?= e($a['role']) ?></small>
                        </div>
                        <small class="text-slate-400"><?= e(date('d M H:i', strtotime($a['created_at']))) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$activityRows): ?><p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada aktivitas.</p><?php endif; ?>
        </div>
    </section>
</div>
</div>
