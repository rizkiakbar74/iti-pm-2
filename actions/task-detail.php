<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$taskId = (int)($_GET['id'] ?? 0);

if (!can_see_task($pdo, $taskId, $user)) {
    die('Akses ditolak.');
}

$stmt = $pdo->prepare("
    SELECT t.*, p.title AS project_title, p.owner_id, p.id AS project_id, u.name AS creator_name
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    JOIN users u ON u.id = t.created_by
    WHERE t.id = ?
");
$stmt->execute([$taskId]);
$task = $stmt->fetch();
if (!$task) die('Task tidak ditemukan.');

$stmt = $pdo->prepare("
    SELECT s.*, submitter.name AS submitter_name, reviewer.name AS reviewer_name
    FROM task_submissions s
    JOIN users submitter ON submitter.id = s.submitted_by
    LEFT JOIN users reviewer ON reviewer.id = s.reviewed_by
    WHERE s.task_id = ?
    ORDER BY s.created_at DESC
");
$stmt->execute([$taskId]);
$subs = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT c.*, u.name, u.role
    FROM task_comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.task_id = ? AND c.deleted_at IS NULL
    ORDER BY c.created_at DESC
");
$stmt->execute([$taskId]);
$comments = $stmt->fetchAll();


$taskError = '';
$taskSuccess = '';
$canEditTask = can_edit_task($pdo, $task, $user);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_task') {
        if (!$canEditTask) {
            die('Akses ditolak. Kamu tidak bisa edit task ini.');
        }
        if (task_has_locked_review($pdo, $taskId)) {
            $taskError = 'Task tidak bisa diedit karena sudah memiliki bukti submit yang menunggu review atau sudah approved.';
        } else {
            $newTitle = trim($_POST['title'] ?? '');
            $newDesc = trim($_POST['description'] ?? '');
            $newDeadline = $_POST['deadline_at'] ?? '';
            $selectedAssignees = array_values(array_unique(array_filter(array_map('intval', $_POST['assignee_ids'] ?? []))));

            $project = get_project_by_id($pdo, (int)$task['project_id']);
            $assignable = get_task_assignable_users($pdo, $user, (int)$task['project_id']);
            $assignableById = [];
            foreach ($assignable as $candidate) {
                $assignableById[(int)$candidate['id']] = $candidate;
            }

            $validAssignees = [];
            foreach ($selectedAssignees as $assigneeId) {
                if (isset($assignableById[$assigneeId])) {
                    $validAssignees[] = $assigneeId;
                }
            }

            if (!$newTitle) {
                $taskError = 'Judul task wajib diisi.';
            } elseif (!$newDeadline) {
                $taskError = 'Deadline task wajib diisi.';
            } elseif ($project && strtotime($newDeadline) > strtotime($project['deadline_at'])) {
                $taskError = 'Deadline task tidak boleh melewati deadline project.';
            } elseif (!$validAssignees) {
                $taskError = 'Task wajib memiliki minimal 1 penerima valid.';
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, deadline_at = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newTitle, $newDesc, $newDeadline, $taskId]);

                    $stmt = $pdo->prepare("DELETE FROM task_assignees WHERE task_id = ?");
                    $stmt->execute([$taskId]);

                    $stmt = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id, created_at) VALUES (?, ?, NOW())");
                    foreach ($validAssignees as $assigneeId) {
                        $stmt->execute([$taskId, $assigneeId]);
                        if ($assigneeId !== (int)$user['id']) {
                            notify_user($pdo, $assigneeId, 'Task diperbarui', $newTitle, (int)$task['project_id'], $taskId);
                        }
                    }

                    log_activity($pdo, $user['id'], 'Task diedit', $newTitle, (int)$task['project_id'], $taskId);
                    $pdo->commit();
                    $taskSuccess = 'Task berhasil diperbarui.';

                    $stmt = $pdo->prepare("
                        SELECT t.*, p.title AS project_title, p.owner_id, p.id AS project_id, u.name AS creator_name
                        FROM tasks t
                        JOIN projects p ON p.id = t.project_id
                        JOIN users u ON u.id = t.created_by
                        WHERE t.id = ?
                    ");
                    $stmt->execute([$taskId]);
                    $task = $stmt->fetch();
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    $taskError = 'Gagal edit task: ' . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'reopen_task') {
        if (!can_review_task($pdo, $task, ['owner_id' => $task['owner_id']], $user)) {
            die('Akses ditolak. Kamu tidak bisa membuka ulang task ini.');
        }
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            $taskError = 'Alasan buka ulang wajib diisi.';
        } elseif ($task['status'] !== 'approved') {
            $taskError = 'Hanya task approved yang bisa dibuka ulang.';
        } else {
            $stmt = $pdo->prepare("UPDATE tasks SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id'], $taskId]);
            $stmt = $pdo->prepare("
                UPDATE task_submissions
                SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
                WHERE task_id = ? AND status = 'approved'
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user['id'], 'Dibuka ulang: ' . $reason, $taskId]);
            log_activity($pdo, $user['id'], 'Task dibuka ulang', $task['title'] . ' • ' . $reason, (int)$task['project_id'], $taskId);
            $taskSuccess = 'Task berhasil dibuka ulang.';
            $stmt = $pdo->prepare("
                SELECT t.*, p.title AS project_title, p.owner_id, p.id AS project_id, u.name AS creator_name
                FROM tasks t
                JOIN projects p ON p.id = t.project_id
                JOIN users u ON u.id = t.created_by
                WHERE t.id = ?
            ");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
        }
    }
}

$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.role, u.unit
    FROM task_assignees ta
    JOIN users u ON u.id = ta.user_id
    WHERE ta.task_id = ?
    ORDER BY u.name
");
$stmt->execute([$taskId]);
$currentAssignees = $stmt->fetchAll();
$currentAssigneeIds = array_map(fn($u) => (int)$u['id'], $currentAssignees);
$editableAssignees = get_task_assignable_users($pdo, $user, (int)$task['project_id']);
$projectMembers = get_project_members($pdo, (int)$task['project_id']);
$stmt = $pdo->prepare("
    SELECT a.*, u.name
    FROM activity_logs a
    JOIN users u ON u.id = a.user_id
    WHERE a.task_id = ?
    ORDER BY a.created_at DESC
    LIMIT 6
");
$stmt->execute([$taskId]);
$taskActivities = $stmt->fetchAll();
$hasMyPendingSubmission = false;
$hasSubmittedProof = false;
foreach ($subs as $submissionCheck) {
    if ((int)$submissionCheck['submitted_by'] === (int)$user['id'] && $submissionCheck['status'] === 'submitted') $hasMyPendingSubmission = true;
    if ($submissionCheck['status'] === 'submitted') $hasSubmittedProof = true;
}
$canSubmitTask = can_submit_task($pdo, $taskId, $user) && $task['status'] !== 'approved' && !$hasMyPendingSubmission;
$canReviewTask = can_review_task($pdo, $task, ['owner_id' => $task['owner_id']], $user);
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/views/task-detail-view.php';
include __DIR__ . '/../includes/footer.php';
return;
?>
<main class="min-h-screen flex-1 p-4 lg:p-6 overflow-x-hidden">
    <a class="mb-5 inline-block text-sm font-bold text-orange-600" href="../index.php?page=tasks&project_id=<?= e($task['project_id']) ?>">← Kembali ke Tugas</a>

    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-black uppercase text-orange-600"><?= e($task['project_title']) ?></p>
                <h1 class="text-3xl font-black"><?= e($task['title']) ?></h1>
                <p class="mt-2 text-slate-500"><?= e($task['description']) ?></p>
            </div>
            <?= status_badge($task['status']) ?>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-sm font-bold text-slate-500">Pembuat</p>
                <strong><?= e($task['creator_name']) ?></strong>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-sm font-bold text-slate-500">Deadline</p>
                <strong><?= e(date('d M Y H:i', strtotime($task['deadline_at']))) ?></strong>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="text-sm font-bold text-slate-500">Status</p>
                <strong><?= e(get_task_status_label($task['status'])) ?></strong>
            </div>
        </div>
        <div class="mt-4 rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-bold text-slate-500">Penerima Task</p>
            <p class="mt-1 text-sm text-slate-700">
                <?= e(implode(', ', array_map(fn($a) => $a['name'] . ' (' . $a['role'] . ')', $currentAssignees)) ?: '-') ?>
            </p>
        </div>
    </section>

    <?php if ($taskError): ?><div class="mt-6 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700"><?= e($taskError) ?></div><?php endif; ?>
    <?php if ($taskSuccess): ?><div class="mt-6 rounded-xl bg-green-50 p-4 text-sm font-bold text-green-700"><?= e($taskSuccess) ?></div><?php endif; ?>

    <?php if ($canEditTask): ?>
    <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Edit Task</h2>
        <?php if (task_has_locked_review($pdo, $taskId)): ?>
            <p class="mt-3 rounded-xl bg-amber-50 p-4 text-sm font-bold text-amber-800">Task sudah memiliki bukti submit/approved, jadi data utama tidak bisa diedit. Gunakan review/reopen jika perlu.</p>
        <?php else: ?>
        <form method="post" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_task">
            <div class="grid gap-3 lg:grid-cols-3">
                <input class="rounded-xl border border-slate-200 px-4 py-3" name="title" required value="<?= e($task['title']) ?>">
                <input class="rounded-xl border border-slate-200 px-4 py-3" name="description" value="<?= e($task['description']) ?>">
                <input class="rounded-xl border border-slate-200 px-4 py-3" type="datetime-local" name="deadline_at" required value="<?= e(date('Y-m-d\TH:i', strtotime($task['deadline_at']))) ?>">
            </div>
            <div class="rounded-2xl bg-slate-50 p-4">
                <p class="mb-3 text-sm font-black">Penerima Task</p>
                <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($editableAssignees as $candidate): ?>
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:border-orange-300">
                            <input class="mt-1" type="checkbox" name="assignee_ids[]" value="<?= e($candidate['id']) ?>" <?= in_array((int)$candidate['id'], $currentAssigneeIds, true) ? 'checked' : '' ?>>
                            <span>
                                <strong class="block text-sm text-slate-900"><?= e($candidate['name']) ?></strong>
                                <small class="block text-xs text-slate-500"><?= e($candidate['role']) ?> • <?= e($candidate['unit']) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button class="rounded-xl bg-orange-600 px-5 py-3 font-black text-white">Simpan Task</button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Submit Bukti</h2>
            <?php
                $hasMyPendingSubmission = false;
                foreach ($subs as $submissionCheck) {
                    if ((int)$submissionCheck['submitted_by'] === (int)$user['id'] && $submissionCheck['status'] === 'submitted') {
                        $hasMyPendingSubmission = true;
                        break;
                    }
                }
            ?>
            <?php if (can_submit_task($pdo, $taskId, $user) && $task['status'] !== 'approved' && !$hasMyPendingSubmission): ?>
            <form class="mt-4 space-y-4" method="post" action="task-submit.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="task_id" value="<?= e($taskId) ?>">
                <textarea class="w-full rounded-xl border border-slate-200 p-4" name="note" rows="4" placeholder="Catatan pekerjaan..." required></textarea>
                <input class="w-full rounded-xl border border-slate-200 p-3" name="proof_file" type="file">
                <button class="rounded-xl bg-orange-600 px-5 py-3 font-black text-white">Kirim Bukti</button>
            </form>
            <?php else: ?>
                <p class="mt-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                    <?= $hasMyPendingSubmission ? 'Bukti kamu masih menunggu review. Submit ulang tersedia setelah reviewer approve/reject.' : 'Kamu tidak dapat submit task ini, atau task sudah verified checked.' ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-black">Review</h2>
            <?php
                $hasSubmittedProof = false;
                foreach ($subs as $submissionCheck) {
                    if ($submissionCheck['status'] === 'submitted') { $hasSubmittedProof = true; break; }
                }
            ?>
            <?php if (can_review_task($pdo, $task, ['owner_id' => $task['owner_id']], $user) && $hasSubmittedProof): ?>
                <form class="mt-4 space-y-4" method="post" action="task-review.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="task_id" value="<?= e($taskId) ?>">
                    <textarea class="w-full rounded-xl border border-slate-200 p-4" name="review_note" rows="3" placeholder="Catatan review / alasan reject"></textarea>
                    <div class="flex gap-3">
                        <button name="decision" value="approved" class="rounded-xl bg-green-600 px-5 py-3 font-black text-white">Verified Checked</button>
                        <button name="decision" value="rejected" class="rounded-xl bg-red-600 px-5 py-3 font-black text-white">Reject</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="mt-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                    <?= can_review_task($pdo, $task, ['owner_id' => $task['owner_id']], $user) ? 'Belum ada bukti submit yang menunggu review.' : 'Role kamu tidak punya akses review task ini.' ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <?php if (can_review_task($pdo, $task, ['owner_id' => $task['owner_id']], $user) && $task['status'] === 'approved'): ?>
    <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Buka Ulang Task</h2>
        <form method="post" class="mt-4 grid gap-3 lg:grid-cols-[1fr_auto]">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reopen_task">
            <input class="rounded-xl border border-slate-200 px-4 py-3" name="reason" placeholder="Alasan task dibuka ulang" required>
            <button class="rounded-xl bg-red-600 px-5 py-3 font-black text-white">Buka Ulang</button>
        </form>
    </section>
    <?php endif; ?>

    <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-black">Riwayat Submit & Review</h2>
                <p class="text-sm text-slate-500">Semua submit disimpan, terbaru tampil paling atas.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600"><?= e(count($subs)) ?> submit</span>
        </div>
        <div class="mt-4 space-y-3">
            <?php foreach ($subs as $s): ?>
                <div class="rounded-2xl border border-slate-100 p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <?= status_badge($s['status']) ?>
                            <strong class="ml-2 text-sm"><?= e($s['submitter_name']) ?></strong>
                        </div>
                        <small class="text-slate-500"><?= e(date('d M Y H:i', strtotime($s['created_at']))) ?></small>
                    </div>
                    <p class="mt-3 text-slate-700"><?= e($s['note']) ?></p>
                    <?php if ($s['file_path']): ?>
                        <a class="mt-2 inline-block rounded-lg bg-orange-50 px-3 py-2 text-sm font-bold text-orange-700" href="../<?= e($s['file_path']) ?>" target="_blank">Buka File Bukti</a>
                    <?php endif; ?>
                    <?php if ($s['reviewed_by']): ?>
                        <div class="mt-3 rounded-xl bg-slate-50 p-3 text-sm">
                            <p><b>Reviewer:</b> <?= e($s['reviewer_name'] ?: '-') ?> • <?= e($s['reviewed_at'] ? date('d M Y H:i', strtotime($s['reviewed_at'])) : '-') ?></p>
                            <?php if ($s['review_note']): ?>
                                <p class="mt-1"><b>Catatan Review:</b> <?= e($s['review_note']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$subs): ?>
                <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada submit bukti.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-black">Komentar Task</h2>
                <p class="text-sm text-slate-500">Diskusi task antar pembuat, reviewer, dan penerima task.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600"><?= e(count($comments)) ?> komentar</span>
        </div>

        <form method="post" action="task-comment.php" class="mb-5 grid gap-3 lg:grid-cols-[1fr_auto]">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_comment">
            <input type="hidden" name="task_id" value="<?= e($taskId) ?>">
            <input class="rounded-xl border border-slate-200 px-4 py-3" name="body" placeholder="Tulis komentar..." required>
            <button class="rounded-xl bg-slate-900 px-5 py-3 font-black text-white">Kirim Komentar</button>
        </form>

        <div class="space-y-3">
            <?php foreach ($comments as $comment): ?>
                <div class="rounded-2xl border border-slate-100 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <strong><?= e($comment['name']) ?></strong>
                            <small class="ml-2 text-slate-400"><?= e($comment['role']) ?> • <?= e(date('d M Y H:i', strtotime($comment['created_at']))) ?></small>
                            <p class="mt-2 text-slate-700"><?= e($comment['body']) ?></p>
                        </div>
                        <?php
                            $canDeleteComment = (int)$comment['user_id'] === (int)$user['id'] || $user['role'] === 'SUPERADMIN' || can_review_task($pdo, $task, ['owner_id' => $task['owner_id']], $user);
                        ?>
                        <?php if ($canDeleteComment): ?>
                            <form method="post" action="task-comment.php" onsubmit="return confirm('Hapus komentar ini?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_comment">
                                <input type="hidden" name="task_id" value="<?= e($taskId) ?>">
                                <input type="hidden" name="comment_id" value="<?= e($comment['id']) ?>">
                                <button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-700">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$comments): ?>
                <p class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-500">Belum ada komentar.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
