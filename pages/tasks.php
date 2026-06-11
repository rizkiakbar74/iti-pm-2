<?php
$user = current_user();
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$formError = '';
$oldInput = [
    'project_id' => $projectId ?: '',
    'title' => '',
    'description' => '',
    'deadline_at' => '',
    'assignee_ids' => [],
];

function load_visible_project_options($pdo, $user) {
    if ($user['role'] === 'SUPERADMIN') {
        $stmt = $pdo->query("SELECT id, title, deadline_at FROM projects WHERE deleted_at IS NULL ORDER BY title");
        return $stmt->fetchAll();
    }
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.deadline_at
        FROM projects p
        JOIN project_members pm ON pm.project_id = p.id
        WHERE pm.user_id = ? AND p.deleted_at IS NULL
        ORDER BY p.title
    ");
    $stmt->execute([$user['id']]);
    return $stmt->fetchAll();
}

$projectOptions = $user['role'] !== 'USER' ? load_visible_project_options($pdo, $user) : [];
$projectOptionsById = [];
foreach ($projectOptions as $p) {
    $projectOptionsById[(int)$p['id']] = $p;
}

$projectMembersByProject = [];
foreach ($projectOptions as $p) {
    $projectMembersByProject[(int)$p['id']] = get_task_assignable_users($pdo, $user, (int)$p['id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    verify_csrf();

    $projectIdPost = (int)($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $deadline = $_POST['deadline_at'] ?? '';
    $selectedAssigneeIds = array_values(array_unique(array_filter(array_map('intval', $_POST['assignee_ids'] ?? []))));

    $oldInput = [
        'project_id' => $projectIdPost,
        'title' => $title,
        'description' => $desc,
        'deadline_at' => $deadline,
        'assignee_ids' => $selectedAssigneeIds,
    ];

    if (!can_create_task($user['role'])) {
        $formError = 'Role kamu tidak boleh membuat task.';
    } elseif (!$projectIdPost || !isset($projectOptionsById[$projectIdPost]) || !can_see_project($pdo, $projectIdPost, $user)) {
        $formError = 'Project tidak valid atau tidak bisa kamu akses.';
    } elseif (!$title) {
        $formError = 'Judul task wajib diisi.';
    } elseif (!$deadline) {
        $formError = 'Deadline task wajib diisi.';
    } else {
        $projectDeadline = strtotime($projectOptionsById[$projectIdPost]['deadline_at']);
        $taskDeadline = strtotime($deadline);
        if ($projectDeadline && $taskDeadline && $taskDeadline > $projectDeadline) {
            $formError = 'Deadline task tidak boleh melewati deadline project.';
        }
    }

    $validAssignees = [];
    if (!$formError) {
        $assignableForProject = $projectMembersByProject[$projectIdPost] ?? get_task_assignable_users($pdo, $user, $projectIdPost);
        $assignableById = [];
        foreach ($assignableForProject as $candidate) {
            $assignableById[(int)$candidate['id']] = $candidate;
        }

        foreach ($selectedAssigneeIds as $assigneeId) {
            if (isset($assignableById[$assigneeId]) && can_assign_project_member($user, $assignableById[$assigneeId])) {
                $validAssignees[] = $assigneeId;
            }
        }

        $validAssignees = array_values(array_unique($validAssignees));
        if (!$validAssignees) {
            $formError = 'Task wajib memiliki minimal 1 penerima yang merupakan anggota project.';
        }
    }

    if (!$formError) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tasks (project_id, title, description, created_by, status, deadline_at, created_at)
                VALUES (?, ?, ?, ?, 'open', ?, NOW())
            ");
            $stmt->execute([$projectIdPost, $title, $desc, $user['id'], $deadline]);
            $taskId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, created_at) VALUES (?, ?, NOW())");
            foreach ($validAssignees as $assigneeId) {
                $stmt->execute([$taskId, $assigneeId]);
                if ($assigneeId !== (int)$user['id']) {
                    notify_user($pdo, $assigneeId, 'Task baru ditugaskan', $title, $projectIdPost, $taskId);
                }
            }

            log_activity($pdo, $user['id'], 'Task dibuat', $title . ' • penerima: ' . count($validAssignees), $projectIdPost, $taskId);
            $pdo->commit();
            redirect('index.php?page=tasks&project_id=' . $projectIdPost);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $formError = 'Gagal membuat task: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../actions/views/tasks-list-view.php';
return;

$where = [];
$params = [];

if ($user['role'] !== 'SUPERADMIN') {
    $where[] = "(pm.user_id = ? OR ta.user_id = ?)";
    $params[] = $user['id'];
    $params[] = $user['id'];
}
if ($projectId) {
    $where[] = "p.id = ?";
    $params[] = $projectId;
}
$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("
    SELECT DISTINCT t.*, p.title AS project_title, p.owner_id, u.name AS creator_name,
        (SELECT GROUP_CONCAT(u2.name ORDER BY u2.name SEPARATOR ', ')
         FROM task_assignees ta2
         JOIN users u2 ON u2.id = ta2.user_id
         WHERE ta2.task_id = t.id) AS assignee_names
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    JOIN users u ON u.id = t.created_by
    LEFT JOIN project_members pm ON pm.project_id = p.id
    LEFT JOIN task_assignees ta ON ta.task_id = t.id
    {$whereSql}
    ORDER BY t.created_at DESC
    LIMIT 150
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();
?>

<div class="mb-6">
    <p class="text-sm font-black uppercase tracking-wide text-orange-600">Tugas</p>
    <h2 class="text-3xl font-black">Tugas Saya</h2>
    <p class="text-slate-500">Task visible berdasarkan role, project member, dan penerima task.</p>
</div>

<?php if ($user['role'] !== 'USER' && $projectOptions): ?>
<form method="post" class="mb-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
    <input type="hidden" name="create_task" value="1">
    <?= csrf_field() ?>

    <?php if ($formError): ?>
        <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">
            <?= e($formError) ?>
        </div>
    <?php endif; ?>

    <div class="grid gap-3 lg:grid-cols-5">
        <select class="rounded-xl border border-slate-200 px-4 py-3" name="project_id" id="taskProjectSelect" required>
            <?php foreach ($projectOptions as $p): ?>
                <option value="<?= e($p['id']) ?>" <?= (int)$oldInput['project_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                    <?= e($p['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="title" placeholder="Judul task" required value="<?= e($oldInput['title']) ?>">
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="description" placeholder="Deskripsi" value="<?= e($oldInput['description']) ?>">
        <input class="rounded-xl border border-slate-200 px-4 py-3" name="deadline_at" type="datetime-local" required value="<?= e($oldInput['deadline_at']) ?>">
        <button class="rounded-xl bg-orange-600 px-4 py-3 font-black text-white">Tambah Task</button>
    </div>

    <div class="mt-5 rounded-2xl bg-slate-50 p-4">
        <div class="mb-3">
            <p class="text-sm font-black text-slate-800">Penerima Task</p>
            <p class="text-xs text-slate-500">Pilih minimal 1 penerima. Penerima harus sudah menjadi anggota project yang dipilih.</p>
        </div>

        <?php foreach ($projectOptions as $p): ?>
            <?php $members = $projectMembersByProject[(int)$p['id']] ?? []; ?>
            <div class="task-assignee-group grid gap-2 md:grid-cols-2 xl:grid-cols-3" data-project-id="<?= e($p['id']) ?>">
                <?php foreach ($members as $member): ?>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:border-orange-300">
                        <input class="mt-1" type="checkbox" name="assignee_ids[]" value="<?= e($member['id']) ?>" <?= in_array((int)$member['id'], $oldInput['assignee_ids'], true) ? 'checked' : '' ?>>
                        <span>
                            <strong class="block text-sm text-slate-900"><?= e($member['name']) ?></strong>
                            <small class="block text-xs text-slate-500"><?= e($member['role']) ?> • <?= e($member['unit']) ?> • <?= e($member['role_in_project']) ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
                <?php if (!$members): ?>
                    <p class="rounded-xl bg-red-50 p-3 text-sm font-bold text-red-700">Project ini belum punya anggota yang valid untuk menerima task.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</form>

<script>
(function() {
    const select = document.getElementById('taskProjectSelect');
    const groups = document.querySelectorAll('.task-assignee-group');
    function refreshGroups() {
        const selected = select ? select.value : '';
        groups.forEach((group) => {
            const active = group.dataset.projectId === selected;
            group.style.display = active ? 'grid' : 'none';
            group.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                if (!active) input.checked = false;
            });
        });
    }
    if (select) {
        select.addEventListener('change', refreshGroups);
        refreshGroups();
    }
})();
</script>
<?php elseif ($user['role'] !== 'USER'): ?>
    <div class="mb-6 rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm font-bold text-amber-800">
        Kamu belum memiliki project yang bisa dipilih untuk membuat task.
    </div>
<?php endif; ?>

<section class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm iti-scrollbar">
    <div class="grid min-w-[1040px] grid-cols-8 gap-3 bg-slate-50 px-5 py-3 text-xs font-black uppercase text-slate-500">
        <span class="col-span-2">Task</span>
        <span>Project</span>
        <span>Pembuat</span>
        <span>Penerima</span>
        <span>Deadline</span>
        <span>Status</span>
        <span>Aksi</span>
    </div>
    <?php foreach ($tasks as $task): ?>
        <div class="grid min-w-[1040px] grid-cols-8 gap-3 border-t border-slate-100 px-5 py-4 text-sm">
            <div class="col-span-2">
                <strong><?= e($task['title']) ?></strong>
                <p class="text-slate-500"><?= e($task['description']) ?></p>
            </div>
            <span><?= e($task['project_title']) ?></span>
            <span><?= e($task['creator_name']) ?></span>
            <span class="text-slate-600"><?= e($task['assignee_names'] ?: '-') ?></span>
            <span class="<?= strtotime($task['deadline_at']) < time() && $task['status'] !== 'approved' ? 'font-black text-red-600' : '' ?>"><?= e(date('d M Y', strtotime($task['deadline_at']))) ?></span>
            <span><?= status_badge($task['status']) ?></span>
            <span>
                <a class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-black text-white" href="actions/task-detail.php?id=<?= e($task['id']) ?>">Detail</a>
            </span>
        </div>
    <?php endforeach; ?>
    <?php if (!$tasks): ?>
        <div class="task-empty-state border-t border-slate-100 p-6 text-sm text-slate-500"><h3>Data Tidak Ditemukan</h3><p>Belum ada task yang sesuai dengan filter atau pencarian saat ini.</p><a href="<?= e(app_url('index.php?page=tasks')) ?>">Reset Filter</a><?php if(can_create_task($user['role'])):?><a href="<?= e(app_url('index.php?page=tasks&create=1')) ?>">Tambah Data Baru</a><?php endif;?></div>
    <?php endif; ?>
</section>
