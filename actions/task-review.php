<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
verify_csrf();

$user = current_user();
$taskId = (int)($_POST['task_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
$reviewNote = trim($_POST['review_note'] ?? '');

if (!in_array($decision, ['approved', 'rejected'], true)) {
    die('Keputusan review tidak valid.');
}

$stmt = $pdo->prepare("
    SELECT t.*, p.owner_id, p.id AS project_id
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE t.id = ?
");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

if (!$task || !can_review_task($pdo, $task, ['owner_id' => $task['owner_id']], $user)) {
    die('Akses ditolak. Role kamu tidak bisa review task ini.');
}

$stmt = $pdo->prepare("SELECT * FROM task_submissions WHERE task_id = ? AND status = 'submitted' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$taskId]);
$latestSubmission = $stmt->fetch();
if (!$latestSubmission) {
    die('Belum ada bukti submit yang menunggu review.');
}

if ($decision === 'rejected' && $reviewNote === '') {
    die('Reject wajib mencantumkan alasan.');
}

$status = $decision === 'approved' ? 'approved' : 'rejected';

$stmt = $pdo->prepare("
    UPDATE task_submissions
    SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = ?
    WHERE id = ?
");
$stmt->execute([$status, $user['id'], $reviewNote, $latestSubmission['id']]);

$stmt = $pdo->prepare("UPDATE tasks SET status = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() WHERE id = ?");
$stmt->execute([$status, $user['id'], $taskId]);

log_activity($pdo, $user['id'], $status === 'approved' ? 'Task verified' : 'Task ditolak', $task['title'], $task['project_id'], $taskId);
notify_user($pdo, (int)$latestSubmission['submitted_by'], $status === 'approved' ? 'Task verified' : 'Task ditolak', $task['title'], $task['project_id'], $taskId);

sync_project_status($pdo, (int)$task['project_id']);

redirect('task-detail.php?id=' . $taskId);
