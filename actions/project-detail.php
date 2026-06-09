<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$projectId = (int)($_GET['id'] ?? 0);

if (!$projectId || !can_see_project($pdo, $projectId, $user)) {
    die('Akses ditolak atau project tidak ditemukan.');
}

$project = get_project_by_id($pdo, $projectId);
if (!$project) {
    die('Project tidak ditemukan.');
}

$canManageMembers = can_manage_project_members($pdo, $projectId, $user);
$canEditProject = can_edit_project($pdo, $projectId, $user);
$memberError = '';
$memberSuccess = '';
$projectError = '';
$projectSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_project') {
        if (!$canEditProject) {
            die('Akses ditolak. Kamu tidak bisa edit project ini.');
        }
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $deadlineAt = $_POST['deadline_at'] ?? '';
        $status = $_POST['status'] ?? $project['status'];

        $allowedStatuses = ['draft', 'active', 'review', 'completed'];
        if ($user['role'] === 'SUPERADMIN') {
            $allowedStatuses[] = 'archived';
        }

        if (!$title) {
            $projectError = 'Nama project wajib diisi.';
        } elseif (!$deadlineAt) {
            $projectError = 'Deadline project wajib diisi.';
        } elseif (!in_array($status, $allowedStatuses, true)) {
            $projectError = 'Status project tidak valid.';
        } else {
            $stmt = $pdo->prepare("
                UPDATE projects
                SET title = ?, description = ?, deadline_at = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $description, $deadlineAt, $status, $projectId]);
            log_activity($pdo, $user['id'], 'Project diedit', $title, $projectId);
            $projectSuccess = 'Project berhasil diperbarui.';
            $project = get_project_by_id($pdo, $projectId);
        }
    }

    if ($action === 'archive_project') {
        if (!$canEditProject) {
            die('Akses ditolak. Kamu tidak bisa arsipkan project ini.');
        }

        $progress = get_project_progress_percent($pdo, $projectId);
        if ($progress < 100 && $user['role'] !== 'SUPERADMIN') {
            $projectError = 'Project hanya boleh diarsipkan jika semua task sudah verified checked.';
        } else {
            $stmt = $pdo->prepare("UPDATE projects SET status = 'archived', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$projectId]);
            log_activity($pdo, $user['id'], 'Project diarsipkan', $project['title'], $projectId);
            $projectSuccess = 'Project berhasil diarsipkan.';
            $project = get_project_by_id($pdo, $projectId);
        }
    }

    if (in_array($action, ['add_member', 'remove_member'], true) && !$canManageMembers) {
        die('Akses ditolak. Kamu tidak bisa mengelola anggota project ini.');
    }

    if ($action === 'add_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);
        $roleInProject = $_POST['role_in_project'] ?? 'member';
        if (!in_array($roleInProject, ['manager', 'member'], true)) {
            $roleInProject = 'member';
        }

        $stmt = $pdo->prepare("SELECT id, name, email, role, unit FROM users WHERE id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$memberId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            $memberError = 'User tidak ditemukan atau tidak aktif.';
        } elseif ((int)$targetUser['id'] === (int)$project['owner_id']) {
            $memberError = 'Owner project sudah otomatis menjadi anggota.';
        } elseif (!can_assign_project_member($user, $targetUser)) {
            $memberError = 'Role kamu tidak boleh menambahkan user tersebut ke project.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO project_members (project_id, user_id, role_in_project, created_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE role_in_project = VALUES(role_in_project)
                ");
                $stmt->execute([$projectId, $memberId, $roleInProject]);
                notify_user($pdo, $memberId, 'Project baru ditugaskan', $project['title'], $projectId, null);
                log_activity($pdo, $user['id'], 'Anggota project ditambahkan', $targetUser['name'] . ' ke ' . $project['title'], $projectId);
                $memberSuccess = 'Anggota berhasil ditambahkan.';
            } catch (Throwable $e) {
                $memberError = 'Gagal menambahkan anggota: ' . $e->getMessage();
            }
        }
    }

    if ($action === 'remove_member') {
        $memberId = (int)($_POST['member_id'] ?? 0);

        if ($memberId === (int)$project['owner_id']) {
            $memberError = 'Owner project tidak bisa dihapus dari anggota.';
        } elseif ($memberId === (int)$user['id'] && $user['role'] !== 'SUPERADMIN') {
            $memberError = 'Kamu tidak bisa menghapus diri sendiri dari project.';
        } else {
            $activeTaskCount = get_project_active_task_count_for_user($pdo, $projectId, $memberId);
            if ($activeTaskCount > 0) {
                $memberError = 'Anggota tidak bisa dihapus karena masih punya task aktif di project ini. Pindahkan/selesaikan task dulu.';
            } else {
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$memberId]);
                $memberName = $stmt->fetchColumn() ?: 'User';

                $stmt = $pdo->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ? AND role_in_project <> 'owner'");
                $stmt->execute([$projectId, $memberId]);

                log_activity($pdo, $user['id'], 'Anggota project dihapus', $memberName . ' dari ' . $project['title'], $projectId);
                $memberSuccess = 'Anggota berhasil dihapus.';
            }
        }
    }
}

sync_project_status($pdo, $projectId);
$project = get_project_by_id($pdo, $projectId);
$canManageMembers = can_manage_project_members($pdo, $projectId, $user);
$canEditProject = can_edit_project($pdo, $projectId, $user);

$members = get_project_members($pdo, $projectId);
$memberIds = array_map(fn($m) => (int)$m['id'], $members);

$assignable = get_assignable_project_users($pdo, $user);
$assignable = array_values(array_filter($assignable, fn($candidate) => !in_array((int)$candidate['id'], $memberIds, true)));

$stmt = $pdo->prepare("
    SELECT t.*, u.name AS creator_name,
        (SELECT GROUP_CONCAT(u2.name ORDER BY u2.name SEPARATOR ', ')
         FROM task_assignees ta2
         JOIN users u2 ON u2.id = ta2.user_id
         WHERE ta2.task_id = t.id) AS assignee_names
    FROM tasks t
    JOIN users u ON u.id = t.created_by
    WHERE t.project_id = ? AND t.deleted_at IS NULL
    ORDER BY FIELD(t.status,'submitted','rejected','open','approved'), t.deadline_at ASC
");
$stmt->execute([$projectId]);
$tasks = $stmt->fetchAll();

$progress = get_project_progress_percent($pdo, $projectId);
$totalTasks = count($tasks);
$reviewTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'submitted'));
$doneTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'approved'));
$openTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'open'));
$lateTasks = count(array_filter($tasks, fn($t) => strtotime($t['deadline_at']) < time() && $t['status'] !== 'approved'));

$stmt = $pdo->prepare("
    SELECT a.*, u.name, u.role
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    WHERE a.project_id = ?
    ORDER BY a.created_at DESC
    LIMIT 6
");
$stmt->execute([$projectId]);
$projectActivities = $stmt->fetchAll();
$timelineTasks = $tasks;
usort($timelineTasks, fn($a, $b) => strtotime($a['deadline_at']) <=> strtotime($b['deadline_at']));
$daysRemaining = (int)ceil((strtotime($project['deadline_at']) - time()) / 86400);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/views/project-detail-view.php';
include __DIR__ . '/../includes/footer.php';
return;
?>
<main class="min-h-screen flex-1 p-4 lg:p-6 overflow-x-hidden">
    <a class="mb-5 inline-block text-sm font-bold text-orange-600" href="../index.php?page=projects">← Kembali ke Project</a>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-black uppercase text-orange-600">Detail Project</p>
                <h1 class="text-3xl font-black"><?= e($project['title']) ?></h1>
                <p class="mt-2 max-w-3xl text-slate-500"><?= e($project['description']) ?></p>
            </div>
            <div class="text-left lg:text-right">
                <?= status_badge($project['status']) ?>
                <p class="mt-2 text-sm text-slate-500">Deadline <?= e(date('d M Y', strtotime($project['deadline_at']))) ?></p>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-5">
            <div class="rounded-2xl bg-slate-50 p-4"><p class="text-sm font-bold text-slate-500">Owner</p><strong><?= e($project['owner_name']) ?></strong></div>
            <div class="rounded-2xl bg-slate-50 p-4"><p class="text-sm font-bold text-slate-500">Anggota</p><strong><?= e(count($members)) ?></strong></div>
            <div class="rounded-2xl bg-slate-50 p-4"><p class="text-sm font-bold text-slate-500">Task</p><strong><?= e($totalTasks) ?></strong></div>
            <div class="rounded-2xl bg-slate-50 p-4"><p class="text-sm font-bold text-slate-500">Review</p><strong><?= e($reviewTasks) ?></strong></div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-sm font-bold text-slate-500">Progress</p>
                <strong><?= e($progress) ?>%</strong>
                <div class="mt-2 h-2 rounded-full bg-slate-200"><div class="h-2 rounded-full bg-orange-600" style="width: <?= e($progress) ?>%"></div></div>
            </div>
        </div>
    </section>

    <?php if ($canEditProject): ?>
    <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Edit Project</h2>
        <?php if ($projectError): ?><div class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700"><?= e($projectError) ?></div><?php endif; ?>
        <?php if ($projectSuccess): ?><div class="mt-4 rounded-xl bg-green-50 p-3 text-sm font-bold text-green-700"><?= e($projectSuccess) ?></div><?php endif; ?>
        <form method="post" class="mt-4 grid gap-3 lg:grid-cols-5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_project">
            <input class="rounded-xl border border-slate-200 px-4 py-3" name="title" required value="<?= e($project['title']) ?>">
            <input class="rounded-xl border border-slate-200 px-4 py-3" name="description" value="<?= e($project['description']) ?>">
            <input class="rounded-xl border border-slate-200 px-4 py-3" type="datetime-local" name="deadline_at" required value="<?= e(date('Y-m-d\TH:i', strtotime($project['deadline_at']))) ?>">
            <select class="rounded-xl border border-slate-200 px-4 py-3" name="status">
                <?php foreach (['draft'=>'Draft','active'=>'Aktif','review'=>'Dalam Review','completed'=>'Selesai'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $project['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
                <?php if ($user['role'] === 'SUPERADMIN'): ?>
                    <option value="archived" <?= $project['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                <?php endif; ?>
            </select>
            <button class="rounded-xl bg-orange-600 px-4 py-3 font-black text-white">Simpan Project</button>
        </form>

        <form method="post" class="mt-3" onsubmit="return confirm('Arsipkan project ini?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="archive_project">
            <button class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700">Arsipkan Project</button>
            <small class="ml-3 text-slate-500">Non-SUPERADMIN hanya bisa arsip jika progress 100%.</small>
        </form>
    </section>
    <?php endif; ?>

    <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-black">Task Project</h2>
                    <p class="text-sm text-slate-500"><?= e($openTasks) ?> aktif • <?= e($reviewTasks) ?> review • <?= e($doneTasks) ?> selesai • <?= e($lateTasks) ?> terlambat</p>
                </div>
                <a class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white" href="../index.php?page=tasks&project_id=<?= e($projectId) ?>">Kelola Task</a>
            </div>

            <div class="space-y-3">
                <?php foreach ($tasks as $task): ?>
                    <a href="task-detail.php?id=<?= e($task['id']) ?>" class="grid gap-3 rounded-2xl border border-slate-100 p-4 hover:bg-slate-50 lg:grid-cols-[1.5fr_1fr_120px_120px]">
                        <span>
                            <b class="block"><?= e($task['title']) ?></b>
                            <small class="text-slate-500"><?= e($task['description']) ?></small>
                        </span>
                        <span class="text-sm text-slate-600">Penerima: <?= e($task['assignee_names'] ?: '-') ?></span>
                        <span class="<?= strtotime($task['deadline_at']) < time() && $task['status'] !== 'approved' ? 'font-black text-red-600' : 'text-sm text-slate-600' ?>"><?= e(date('d M Y', strtotime($task['deadline_at']))) ?></span>
                        <span><?= status_badge($task['status']) ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$tasks): ?><p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada task pada project ini.</p><?php endif; ?>
            </div>
        </div>

        <aside class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Anggota Project</h2>

            <?php if ($memberError): ?><div class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700"><?= e($memberError) ?></div><?php endif; ?>
            <?php if ($memberSuccess): ?><div class="mt-4 rounded-xl bg-green-50 p-3 text-sm font-bold text-green-700"><?= e($memberSuccess) ?></div><?php endif; ?>

            <div class="mt-4 space-y-3">
                <?php foreach ($members as $member): ?>
                    <div class="rounded-2xl border border-slate-100 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <strong class="block"><?= e($member['name']) ?></strong>
                                <small class="block text-slate-500"><?= e($member['role']) ?> • <?= e($member['unit']) ?></small>
                                <small class="block font-bold text-orange-600"><?= e($member['role_in_project']) ?></small>
                            </div>
                            <?php if ($canManageMembers && $member['role_in_project'] !== 'owner'): ?>
                                <form method="post" onsubmit="return confirm('Hapus anggota ini dari project?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_member">
                                    <input type="hidden" name="member_id" value="<?= e($member['id']) ?>">
                                    <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-700">Hapus</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($canManageMembers): ?>
                <form method="post" class="mt-5 rounded-2xl bg-slate-50 p-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_member">
                    <p class="mb-3 text-sm font-black">Tambah Anggota</p>
                    <?php if ($assignable): ?>
                        <select name="member_id" class="mb-3 w-full rounded-xl border border-slate-200 px-3 py-3" required>
                            <?php foreach ($assignable as $candidate): ?>
                                <option value="<?= e($candidate['id']) ?>"><?= e($candidate['name']) ?> — <?= e($candidate['role']) ?> / <?= e($candidate['unit']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="role_in_project" class="mb-3 w-full rounded-xl border border-slate-200 px-3 py-3">
                            <option value="member">Member</option>
                            <option value="manager">Manager</option>
                        </select>
                        <button class="w-full rounded-xl bg-orange-600 px-4 py-3 font-black text-white">Tambah Anggota</button>
                    <?php else: ?>
                        <p class="rounded-xl bg-amber-50 p-3 text-sm font-bold text-amber-800">Tidak ada user valid yang bisa ditambahkan.</p>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </aside>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
