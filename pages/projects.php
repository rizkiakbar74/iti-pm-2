<?php
$user = current_user();
$canCreateProject = can_create_project($user['role']);
$assignableUsers = $canCreateProject ? get_assignable_project_users($pdo, $user) : [];
$assignableById = [];
foreach ($assignableUsers as $candidate) {
    $assignableById[(int)$candidate['id']] = $candidate;
}

$formError = '';
$oldInput = ['title' => '', 'description' => '', 'deadline_at' => date('Y-m-d', strtotime('+30 days')), 'member_ids' => []];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canCreateProject) {
    verify_csrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline = $_POST['deadline_at'] ?? date('Y-m-d', strtotime('+30 days'));
    $selectedMemberIds = array_values(array_unique(array_filter(array_map('intval', $_POST['member_ids'] ?? []))));
    $oldInput = compact('title', 'description', 'deadline');
    $oldInput['deadline_at'] = $deadline;
    $oldInput['member_ids'] = $selectedMemberIds;

    $validExternalMemberIds = [];
    foreach ($selectedMemberIds as $memberId) {
        if ($memberId !== (int)$user['id'] && isset($assignableById[$memberId]) && can_assign_project_member($user, $assignableById[$memberId])) {
            $validExternalMemberIds[] = $memberId;
        }
    }
    $validExternalMemberIds = array_values(array_unique($validExternalMemberIds));
    $validMemberIds = array_values(array_unique(array_merge([(int)$user['id']], $validExternalMemberIds)));

    if ($title === '') {
        $formError = 'Nama project wajib diisi.';
    } elseif (!$validExternalMemberIds) {
        $formError = 'Project wajib memiliki minimal 1 anggota selain pembuat project.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO projects (title, description, owner_id, status, deadline_at, created_at) VALUES (?, ?, ?, 'active', ?, NOW())");
            $stmt->execute([$title, $description, $user['id'], $deadline]);
            $projectId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO project_members (project_id, user_id, role_in_project, created_at) VALUES (?, ?, ?, NOW())");
            foreach ($validMemberIds as $memberId) {
                $stmt->execute([$projectId, $memberId, $memberId === (int)$user['id'] ? 'owner' : 'member']);
                if ($memberId !== (int)$user['id']) notify_user($pdo, $memberId, 'Project baru ditugaskan', $title, $projectId);
            }
            log_activity($pdo, $user['id'], 'Project dibuat', $title . ' - anggota: ' . count($validMemberIds), $projectId);
            $pdo->commit();
            redirect('index.php?page=projects&created=1');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $formError = 'Gagal membuat project: ' . $e->getMessage();
        }
    }
}

$visibilitySql = $user['role'] === 'SUPERADMIN'
    ? '1=1'
    : 'EXISTS (SELECT 1 FROM project_members visible_pm WHERE visible_pm.project_id = p.id AND visible_pm.user_id = ?)';
$visibilityParams = $user['role'] === 'SUPERADMIN' ? [] : [(int)$user['id']];

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$ownerFilter = (int)($_GET['owner'] ?? 0);
$sort = $_GET['sort'] ?? 'newest';
$allowedStatuses = ['draft', 'active', 'review', 'completed', 'archived'];
$allowedSorts = ['newest', 'oldest', 'deadline_asc', 'deadline_desc', 'progress_desc'];
if (!in_array($statusFilter, $allowedStatuses, true)) $statusFilter = '';
if (!in_array($sort, $allowedSorts, true)) $sort = 'newest';
$perPage = (int)($_GET['per_page'] ?? 10);
if (!in_array($perPage, [5, 10, 20, 50], true)) $perPage = 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));

$baseWhere = ["{$visibilitySql}", 'p.deleted_at IS NULL'];
$baseParams = $visibilityParams;
$filterWhere = $baseWhere;
$filterParams = $baseParams;
if ($search !== '') {
    $filterWhere[] = '(p.title LIKE ? OR p.description LIKE ? OR u.name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($filterParams, $like, $like, $like);
}
if ($statusFilter !== '') {
    $filterWhere[] = 'p.status = ?';
    $filterParams[] = $statusFilter;
}
if ($ownerFilter > 0) {
    $filterWhere[] = 'p.owner_id = ?';
    $filterParams[] = $ownerFilter;
}

$baseWhereSql = implode(' AND ', $baseWhere);
$filterWhereSql = implode(' AND ', $filterWhere);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE {$baseWhereSql}");
$stmt->execute($baseParams);
$totalProjects = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT p.status, COUNT(*) total FROM projects p WHERE {$baseWhereSql} GROUP BY p.status");
$stmt->execute($baseParams);
$statusCounts = array_fill_keys($allowedStatuses, 0);
foreach ($stmt->fetchAll() as $row) $statusCounts[$row['status']] = (int)$row['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE {$baseWhereSql} AND p.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$stmt->execute($baseParams);
$projectsBeforeMonth = (int)$stmt->fetchColumn();
$projectTrend = $projectsBeforeMonth > 0 ? (int)round((($totalProjects - $projectsBeforeMonth) / $projectsBeforeMonth) * 100) : ($totalProjects ? 100 : 0);

$stmt = $pdo->prepare("SELECT DISTINCT u.id, u.name FROM projects p JOIN users u ON u.id = p.owner_id WHERE {$baseWhereSql} ORDER BY u.name");
$stmt->execute($baseParams);
$ownerOptions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM projects p JOIN users u ON u.id = p.owner_id WHERE {$filterWhereSql}");
$stmt->execute($filterParams);
$filteredCount = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($filteredCount / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

$orderMap = [
    'newest' => 'p.created_at DESC',
    'oldest' => 'p.created_at ASC',
    'deadline_asc' => 'p.deadline_at ASC',
    'deadline_desc' => 'p.deadline_at DESC',
    'progress_desc' => 'progress_percent DESC',
];
$orderBy = $orderMap[$sort];

$stmt = $pdo->prepare("
    SELECT p.*, u.name AS owner_name,
      (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS task_count,
      (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL AND t.status = 'approved') AS approved_count,
      (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS member_count,
      CASE WHEN (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) = 0 THEN 0
           ELSE ROUND((SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL AND t.status = 'approved') /
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) * 100) END AS progress_percent
    FROM projects p
    JOIN users u ON u.id = p.owner_id
    WHERE {$filterWhereSql}
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($filterParams);
$projects = $stmt->fetchAll();

function project_query_url($overrides = []) {
    $query = array_merge($_GET, $overrides);
    $query['page'] = 'projects';
    foreach ($query as $key => $value) if ($value === '' || $value === null) unset($query[$key]);
    return 'index.php?' . http_build_query($query);
}
$activeFilters = [];
if ($search !== '') $activeFilters[] = ['label' => 'Kata kunci: ' . $search, 'clear' => ['q' => null, 'p' => null]];
if ($statusFilter !== '') $activeFilters[] = ['label' => 'Status: ' . ucfirst($statusFilter), 'clear' => ['status' => null, 'p' => null]];
if ($ownerFilter > 0) {
    $ownerName = '';
    foreach ($ownerOptions as $owner) if ((int)$owner['id'] === $ownerFilter) $ownerName = $owner['name'];
    $activeFilters[] = ['label' => 'Owner: ' . ($ownerName ?: $ownerFilter), 'clear' => ['owner' => null, 'p' => null]];
}
if ($sort !== 'newest') {
    $sortLabels = ['oldest' => 'Terlama', 'deadline_asc' => 'Deadline Terdekat', 'deadline_desc' => 'Deadline Terjauh', 'progress_desc' => 'Progress Tertinggi'];
    $activeFilters[] = ['label' => 'Urutan: ' . ($sortLabels[$sort] ?? $sort), 'clear' => ['sort' => null, 'p' => null]];
}
$hasActiveFilters = count($activeFilters) > 0;
?>

<div class="projects-page">
    <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h2 class="text-[28px] font-black tracking-tight text-slate-900">Projects</h2>
            <p class="mt-1 text-sm text-slate-500">Kelola semua project institusi yang dapat Anda akses</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form class="hidden min-w-[340px] items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 lg:flex" method="get">
                <input type="hidden" name="page" value="projects">
                <button class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-orange-50 hover:text-orange-500" type="submit" aria-label="Cari project"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
                <input class="w-full border-0 bg-transparent text-xs outline-none" name="q" value="<?= e($search) ?>" placeholder="Cari project, manager, atau kata kunci...">
            </form>
            <a class="dashboard-interactive-icon relative grid h-11 w-11 place-items-center rounded-xl border border-slate-200 bg-white" href="index.php?page=notifications">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg>
                <?php if ($unreadSidebarCount): ?><span class="absolute -right-1 -top-1 rounded-full bg-orange-500 px-1.5 text-[9px] font-black text-white"><?= e($unreadSidebarCount) ?></span><?php endif; ?>
            </a>
            <details class="relative hidden sm:block">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl px-2 py-1 hover:bg-white">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-orange-100 font-black text-orange-600"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
                    <span><b class="block text-xs"><?= e($user['name']) ?></b><small class="text-slate-500"><?= e(role_label($user['role'])) ?></small></span>
                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <div class="absolute right-0 z-30 mt-2 w-40 rounded-xl border border-slate-200 bg-white p-2 shadow-xl"><a class="block rounded-lg px-3 py-2 hover:bg-slate-50" href="index.php?page=profile">Profil</a><a class="block rounded-lg px-3 py-2 text-red-600 hover:bg-red-50" href="logout.php">Logout</a></div>
            </details>
            <?php if ($canCreateProject): ?><button class="open-project-modal flex items-center gap-2 rounded-xl bg-orange-600 px-5 py-3 text-sm font-black text-white" type="button"><span class="text-xl leading-none">+</span> Buat Project</button><?php endif; ?>
        </div>
    </header>

    <?php if (isset($_GET['created'])): ?><div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-700">Project berhasil dibuat.</div><?php endif; ?>

    <?php
    $kpis = [
        ['Total Projects', $totalProjects, ($projectTrend >= 0 ? '+' : '') . $projectTrend . '% dari bulan lalu', 'orange', 'folder'],
        ['Aktif', $statusCounts['active'], $totalProjects ? round($statusCounts['active'] / $totalProjects * 100) . '% dari total project' : '0%', 'green', 'users'],
        ['Dalam Review', $statusCounts['review'], $totalProjects ? round($statusCounts['review'] / $totalProjects * 100) . '% dari total project' : '0%', 'orange', 'pulse'],
        ['Selesai', $statusCounts['completed'], $totalProjects ? round($statusCounts['completed'] / $totalProjects * 100) . '% dari total project' : '0%', 'green', 'check'],
    ];
    ?>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($kpis as $index => [$label, $value, $note, $tone, $icon]): ?>
            <article class="project-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="--project-delay:<?= e($index * 60) ?>ms">
                <div class="flex items-center gap-5"><span class="grid h-16 w-16 shrink-0 place-items-center rounded-full <?= $tone === 'green' ? 'bg-green-100 text-green-600' : 'bg-orange-100 text-orange-600' ?>">
                    <?php if ($icon === 'check'): ?><svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 6-7"/></svg>
                    <?php elseif ($icon === 'pulse'): ?><svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12h4l2-7 4 14 2-7h6"/></svg>
                    <?php elseif ($icon === 'users'): ?><svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21a8 8 0 0 0-16 0m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                    <?php else: ?><svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 7h7l2 2h9v10H3V7Zm0 0V5h7l2 2"/></svg><?php endif; ?>
                </span><div><p class="text-sm text-slate-500"><?= e($label) ?></p><strong class="block text-3xl font-black"><?= e($value) ?></strong><span class="text-[11px] text-green-600"><?= e($note) ?></span></div></div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="project-filter-panel mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm <?= $hasActiveFilters ? 'is-active' : '' ?>">
        <form class="grid gap-3 lg:grid-cols-[1.45fr_1fr_1fr_1fr_auto_auto]" method="get">
            <input type="hidden" name="page" value="projects">
            <label class="project-search-field flex items-center gap-2 rounded-xl border border-slate-200 px-4">
                <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
                <input class="w-full border-0 bg-transparent py-3 text-sm outline-none" name="q" value="<?= e($search) ?>" placeholder="Cari project...">
                <?php if ($search !== ''): ?><a class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-lg text-slate-500 hover:bg-slate-100" href="<?= e(project_query_url(['q' => null, 'p' => null])) ?>" aria-label="Hapus pencarian">×</a><?php endif; ?>
            </label>
            <select class="rounded-xl border border-slate-200 px-4 py-3" name="status"><option value="">Semua Status</option><?php foreach ($allowedStatuses as $status): ?><option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select>
            <select class="rounded-xl border border-slate-200 px-4 py-3" name="owner"><option value="0">Semua Owner</option><?php foreach ($ownerOptions as $owner): ?><option value="<?= e($owner['id']) ?>" <?= $ownerFilter === (int)$owner['id'] ? 'selected' : '' ?>><?= e($owner['name']) ?></option><?php endforeach; ?></select>
            <select class="rounded-xl border border-slate-200 px-4 py-3" name="sort"><option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option><option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Terlama</option><option value="deadline_asc" <?= $sort === 'deadline_asc' ? 'selected' : '' ?>>Deadline Terdekat</option><option value="progress_desc" <?= $sort === 'progress_desc' ? 'selected' : '' ?>>Progress Tertinggi</option></select>
            <a class="flex items-center justify-center rounded-xl border border-slate-200 px-4 py-3 font-bold text-slate-600 hover:bg-slate-50" href="index.php?page=projects">Reset Filter</a>
            <button class="rounded-xl bg-orange-600 px-6 py-3 font-black text-white">Filter</button>
        </form>
        <?php if ($hasActiveFilters): ?>
            <div class="project-filter-chips mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                <?php foreach ($activeFilters as $filter): ?>
                    <a class="project-filter-chip inline-flex items-center gap-3 rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-700" href="<?= e(project_query_url($filter['clear'])) ?>">
                        <?= e($filter['label']) ?><span class="text-base leading-none text-slate-500">×</span>
                    </a>
                <?php endforeach; ?>
                <a class="ml-2 text-xs font-black text-orange-600 hover:text-orange-700" href="index.php?page=projects">Hapus Semua</a>
            </div>
        <?php endif; ?>
    </section>

    <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="data-table-scroll">
            <table class="w-full table-fixed text-left text-xs">
                <thead><tr><th class="w-[26%] px-5 py-4">Project</th><th class="w-[14%] px-3">Manager</th><th class="w-[17%] px-3">Progress</th><th class="w-[10%] px-3">Status</th><th class="w-[11%] px-3">Deadline</th><th class="w-[8%] px-3">Members</th><th class="w-[14%] px-3 text-center">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php $folderColors = ['bg-blue-100 text-blue-600','bg-green-100 text-green-600','bg-purple-100 text-purple-600','bg-amber-100 text-amber-600','bg-red-100 text-red-600']; ?>
                    <?php foreach ($projects as $index => $project): ?>
                        <tr class="project-row">
                            <td class="px-5 py-4"><a class="flex items-center gap-3 font-black" href="actions/project-detail.php?id=<?= e($project['id']) ?>"><span class="grid h-8 w-8 place-items-center rounded-lg <?= e($folderColors[$index % count($folderColors)]) ?>"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h7l2 2h9v10H3V7Z"/></svg></span><span><?= e($project['title']) ?><small class="block max-w-[260px] truncate font-normal text-slate-400"><?= e($project['description']) ?></small></span></a></td>
                            <td class="px-4"><span class="flex items-center gap-2"><i class="grid h-7 w-7 place-items-center rounded-full bg-orange-100 font-black not-italic text-orange-600"><?= e(strtoupper(substr($project['owner_name'], 0, 1))) ?></i><?= e($project['owner_name']) ?></span></td>
                            <td class="px-4"><div class="flex items-center gap-3"><span class="h-2 w-28 rounded-full bg-slate-100"><i class="project-progress block h-2 rounded-full bg-orange-500" style="width:<?= e($project['progress_percent']) ?>%"></i></span><b><?= e($project['progress_percent']) ?>%</b></div></td>
                            <td class="px-4"><?= status_badge($project['status']) ?></td>
                            <td class="px-4 whitespace-nowrap"><?= e(date('d M Y', strtotime($project['deadline_at']))) ?></td>
                            <td class="px-4"><?= e($project['member_count']) ?> anggota</td>
                            <td class="px-3"><div class="project-direct-actions flex items-center justify-center gap-1.5"><a class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-2 font-bold hover:border-orange-200 hover:bg-orange-50 hover:text-orange-600" href="actions/project-detail.php?id=<?= e($project['id']) ?>" title="Detail Project"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/><circle cx="12" cy="12" r="2.5"/></svg><span>Detail</span></a><a class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-2 font-bold hover:border-orange-200 hover:bg-orange-50 hover:text-orange-600" href="index.php?page=tasks&project_id=<?= e($project['id']) ?>" title="Lihat Tasks"><svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H6v16h12V5h-3M9 3h6v4H9z"/><path d="m9 14 2 2 4-5"/></svg><span>Tasks</span></a></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$projects): ?><p class="p-8 text-center text-slate-500">Tidak ada project yang cocok dengan filter.</p><?php endif; ?>
        <footer class="flex flex-col gap-4 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-slate-500">Menampilkan <?= $filteredCount ? e($offset + 1) : 0 ?> - <?= e(min($offset + $perPage, $filteredCount)) ?> dari <?= e($filteredCount) ?> project</p>
            <nav class="flex items-center gap-1"><?php for ($pageNumber = max(1, $currentPage - 2); $pageNumber <= min($totalPages, $currentPage + 2); $pageNumber++): ?><a class="grid h-9 w-9 place-items-center rounded-lg border <?= $pageNumber === $currentPage ? 'border-orange-500 bg-orange-500 text-white' : 'border-slate-200 hover:bg-orange-50' ?>" href="<?= e(project_query_url(['p' => $pageNumber])) ?>"><?= e($pageNumber) ?></a><?php endfor; ?></nav>
            <form class="flex items-center gap-2 text-xs text-slate-500" method="get"><input type="hidden" name="page" value="projects"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><input type="hidden" name="owner" value="<?= e($ownerFilter) ?>"><input type="hidden" name="sort" value="<?= e($sort) ?>"><span>Tampilkan</span><select class="rounded-lg border border-slate-200 px-3 py-2" name="per_page" onchange="this.form.submit()"><?php foreach ([5,10,20,50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select><span>per halaman</span></form>
        </footer>
    </section>
</div>

<?php if ($canCreateProject): ?>
<div class="project-modal fixed inset-0 z-50 <?= $formError ? 'flex' : 'hidden' ?> items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm">
    <form method="post" class="project-modal-panel project-create-form">
        <?= csrf_field() ?>
        <header class="project-create-header"><div><p>Project / Create Project</p><h3>Create Project</h3><span>Buat project baru untuk tim dan kelola dengan lebih terstruktur.</span></div><div><button class="close-project-modal project-create-back" type="button">Kembali</button><button class="project-create-save" type="submit">Simpan Project</button></div></header>
        <?php if ($formError): ?><div class="project-create-error"><?= e($formError) ?></div><?php endif; ?>
        <div class="project-create-layout"><div class="project-create-main">
            <section class="project-create-card"><h4>Informasi Dasar</h4><label><span>Nama Project <b>*</b></span><input name="title" required value="<?= e($oldInput['title']) ?>" placeholder="Contoh: Sistem Informasi Akademik ITI"></label><label><span>Deskripsi Project</span><textarea name="description" rows="5" placeholder="Jelaskan tujuan, ruang lingkup, dan manfaat project ini..."><?= e($oldInput['description']) ?></textarea><small>Gunakan deskripsi yang jelas agar seluruh anggota memahami tujuan project.</small></label></section>
            <section class="project-create-card"><h4>Team & Ownership</h4><div class="project-owner-field"><span>Project Manager</span><strong><i><?= e(strtoupper(substr($user['name'], 0, 1))) ?></i><span><b><?= e($user['name']) ?></b><small><?= e(role_label($user['role'])) ?> - <?= e($user['unit']) ?></small></span><em>Anda</em></strong></div><div class="project-member-section"><div class="project-member-heading"><div><p class="project-field-label">Team Member <b>*</b></p><small>Anggota tampil otomatis. Ketik lalu tekan Enter/Cari untuk mempersempit hasil.</small></div><strong><span data-member-selected-count>0</span> dipilih</strong></div><p class="project-member-validation" data-member-validation hidden>Pilih minimal satu anggota project.</p><div class="project-member-tools"><label><span>⌕</span><input data-member-search type="search" placeholder="Cari nama, role, atau unit..."><button data-member-search-button type="button">Cari</button></label><select data-member-role><option value="">Semua Role</option><?php foreach (array_values(array_unique(array_map(fn($candidate) => role_label($candidate['role']), $assignableUsers))) as $roleOption): ?><option value="<?= e(strtolower($roleOption)) ?>"><?= e($roleOption) ?></option><?php endforeach; ?></select><select data-member-unit><option value="">Semua Unit</option><?php foreach (array_values(array_unique(array_filter(array_map(fn($candidate) => $candidate['unit'], $assignableUsers)))) as $unitOption): ?><option value="<?= e(strtolower($unitOption)) ?>"><?= e($unitOption) ?></option><?php endforeach; ?></select></div><div class="project-member-tabs"><button class="is-active" data-member-tab="all" type="button">Semua Anggota</button><button data-member-tab="selected" type="button">Terpilih</button><button data-member-select-visible type="button">Pilih Hasil Filter</button><button data-member-clear type="button">Hapus Pilihan</button></div><div class="project-member-grid"><?php foreach ($assignableUsers as $candidate): if ((int)$candidate['id'] === (int)$user['id']) continue; $candidateRole=role_label($candidate['role']); ?><label class="member-option" data-member-name="<?= e(strtolower($candidate['name'])) ?>" data-member-role-value="<?= e(strtolower($candidateRole)) ?>" data-member-unit-value="<?= e(strtolower($candidate['unit'])) ?>"><input type="checkbox" name="member_ids[]" value="<?= e($candidate['id']) ?>" <?= in_array((int)$candidate['id'], $oldInput['member_ids'], true) ? 'checked' : '' ?>><span class="member-check"></span><i><?= e(strtoupper(substr($candidate['name'], 0, 1))) ?></i><span class="member-copy"><b><?= e($candidate['name']) ?></b><small><?= e($candidateRole) ?> - <?= e($candidate['unit']) ?></small></span><span class="member-selected">Dipilih</span></label><?php endforeach; ?><p class="project-member-empty" data-member-empty>Tidak ada anggota yang cocok.</p></div><div class="project-member-pagination"><button data-member-prev type="button">Sebelumnya</button><span data-member-page-info>Halaman 1 dari 1</span><button data-member-next type="button">Berikutnya</button></div></div></section>
            <section class="project-create-card"><h4>Timeline</h4><label><span>Target Selesai <b>*</b></span><input name="deadline_at" type="date" required value="<?= e($oldInput['deadline_at']) ?>"></label><p class="project-timeline-note">Project akan langsung berstatus aktif setelah disimpan.</p></section>
        </div><aside class="project-create-aside"><section class="project-create-card project-create-tips"><h4>Tips Membuat Project</h4><div><i>1</i><p><b>Gunakan nama yang jelas</b><span>Nama project harus mudah dipahami oleh seluruh tim.</span></p></div><div><i>2</i><p><b>Lengkapi informasi</b><span>Deskripsi lengkap membantu perencanaan yang lebih baik.</span></p></div><div><i>3</i><p><b>Atur timeline realistis</b><span>Pastikan target selesai sesuai estimasi pekerjaan.</span></p></div></section><section class="project-create-card project-create-summary"><h4>Ringkasan Project</h4><p><span>Nama Project</span><b data-summary-title>-</b></p><p><span>Project Manager</span><b><?= e($user['name']) ?></b></p><p><span>Team Member</span><b data-summary-members>0</b></p><p><span>Target Selesai</span><b data-summary-deadline>-</b></p><footer><span>Status Project</span><b><i></i>Akan Aktif</b></footer></section></aside></div>
    </form>
</div>
<?php endif; ?>

<script>
(() => {
    const modal = document.querySelector('.project-modal');
    document.querySelectorAll('.open-project-modal').forEach(button => button.addEventListener('click', () => { modal?.classList.remove('hidden'); modal?.classList.add('flex'); }));
    document.querySelectorAll('.close-project-modal').forEach(button => button.addEventListener('click', () => { modal?.classList.add('hidden'); modal?.classList.remove('flex'); }));
    modal?.addEventListener('click', event => { if (event.target === modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); } });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { modal?.classList.add('hidden'); modal?.classList.remove('flex'); } });
    requestAnimationFrame(() => document.querySelectorAll('.project-progress').forEach(bar => bar.classList.add('is-ready')));
    const createForm = document.querySelector('.project-create-form');
    const updateSummary = () => {
        if (!createForm) return;
        createForm.querySelector('[data-summary-title]').textContent = createForm.querySelector('[name="title"]')?.value.trim() || '-';
        createForm.querySelector('[data-summary-deadline]').textContent = createForm.querySelector('[name="deadline_at"]')?.value || '-';
        createForm.querySelector('[data-summary-members]').textContent = createForm.querySelectorAll('[name="member_ids[]"]:checked').length;
    };
    createForm?.addEventListener('input', updateSummary);
    createForm?.addEventListener('change', updateSummary);
    updateSummary();
    const memberOptions = [...(createForm?.querySelectorAll('.member-option') || [])];
    const memberSearch = createForm?.querySelector('[data-member-search]');
    const memberRole = createForm?.querySelector('[data-member-role]');
    const memberUnit = createForm?.querySelector('[data-member-unit]');
    const memberEmpty = createForm?.querySelector('[data-member-empty]');
    const memberPageInfo = createForm?.querySelector('[data-member-page-info]');
    const memberCount = createForm?.querySelector('[data-member-selected-count]');
    const memberPerPage = 8;
    let memberPage = 1;
    let memberTab = 'all';
    let memberQuery = '';
    const filteredMembers = () => {
        const role = memberRole?.value || '';
        const unit = memberUnit?.value || '';
        return memberOptions.filter(option => {
            const checked = option.querySelector('input').checked;
            const matchesQuery = !memberQuery || `${option.dataset.memberName} ${option.dataset.memberRoleValue} ${option.dataset.memberUnitValue}`.includes(memberQuery);
            return matchesQuery && (!role || option.dataset.memberRoleValue === role) && (!unit || option.dataset.memberUnitValue === unit) && (memberTab !== 'selected' || checked);
        });
    };
    const renderMembers = () => {
        const visible = filteredMembers();
        const pages = Math.max(1, Math.ceil(visible.length / memberPerPage));
        memberPage = Math.min(memberPage, pages);
        memberOptions.forEach(option => option.hidden = true);
        visible.slice((memberPage - 1) * memberPerPage, memberPage * memberPerPage).forEach(option => option.hidden = false);
        if (memberEmpty) memberEmpty.hidden = visible.length > 0;
        if (memberPageInfo) memberPageInfo.textContent = `Halaman ${memberPage} dari ${pages}`;
        const selectedCount = memberOptions.filter(option => option.querySelector('input').checked).length;
        if (memberCount) memberCount.textContent = selectedCount;
        const validation = createForm?.querySelector('[data-member-validation]');
        if (validation && selectedCount > 0) validation.hidden = true;
        const previousButton = createForm?.querySelector('[data-member-prev]');
        const nextButton = createForm?.querySelector('[data-member-next]');
        if (previousButton) previousButton.disabled = memberPage <= 1;
        if (nextButton) nextButton.disabled = memberPage >= pages;
        updateSummary();
    };
    const submitMemberSearch=()=>{memberQuery=memberSearch?.value.trim().toLowerCase()||'';memberPage=1;renderMembers()};
    memberSearch?.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();submitMemberSearch()}});
    createForm?.querySelector('[data-member-search-button]')?.addEventListener('click',submitMemberSearch);
    [memberRole,memberUnit].forEach(control => control?.addEventListener('change',()=>{memberPage=1;renderMembers()}));
    createForm?.querySelectorAll('[data-member-tab]').forEach(button=>button.addEventListener('click',()=>{memberTab=button.dataset.memberTab;memberPage=1;createForm.querySelectorAll('[data-member-tab]').forEach(item=>item.classList.toggle('is-active',item===button));renderMembers()}));
    createForm?.querySelector('[data-member-prev]')?.addEventListener('click',()=>{memberPage--;renderMembers()});
    createForm?.querySelector('[data-member-next]')?.addEventListener('click',()=>{memberPage++;renderMembers()});
    createForm?.querySelector('[data-member-select-visible]')?.addEventListener('click',()=>{filteredMembers().forEach(option=>option.querySelector('input').checked=true);renderMembers()});
    createForm?.querySelector('[data-member-clear]')?.addEventListener('click',()=>{memberOptions.forEach(option=>option.querySelector('input').checked=false);renderMembers()});
    memberOptions.forEach(option=>option.querySelector('input').addEventListener('change',renderMembers));
    createForm?.addEventListener('submit',event=>{
        const validation=createForm.querySelector('[data-member-validation]');
        if(!memberOptions.some(option=>option.querySelector('input').checked)){
            event.preventDefault();
            if(validation)validation.hidden=false;
            createForm.querySelector('.project-member-section')?.scrollIntoView({behavior:'smooth',block:'center'});
            setTimeout(()=>createForm.classList.remove('is-submitting'),0);
            return;
        }
        if(validation)validation.hidden=true;
    });
    renderMembers();
    if (new URLSearchParams(location.search).get('create') === '1') document.querySelector('.open-project-modal')?.click();
})();
</script>
