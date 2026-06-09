<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
verify_csrf();

$user = current_user();
$taskId = (int)($_POST['task_id'] ?? 0);
$note = trim($_POST['note'] ?? '');

if (!$taskId || !can_see_task($pdo, $taskId, $user) || !can_submit_task($pdo, $taskId, $user)) {
    die('Akses ditolak. Hanya penerima task yang bisa submit bukti.');
}

$stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
$stmt->execute([$taskId]);
$currentStatus = $stmt->fetchColumn();
if ($currentStatus === 'approved') {
    die('Task sudah verified checked. Submit ulang tidak tersedia.');
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM task_submissions WHERE task_id = ? AND submitted_by = ? AND status = 'submitted'");
$stmt->execute([$taskId, $user['id']]);
if ((int)$stmt->fetchColumn() > 0) {
    die('Bukti kamu masih menunggu review. Tidak bisa submit ulang sebelum direview/reject.');
}

$filePath = safe_upload_file($_FILES['proof_file'] ?? [], __DIR__ . '/../uploads');

$stmt = $pdo->prepare("
    INSERT INTO task_submissions (task_id, submitted_by, note, file_path, status, created_at)
    VALUES (?, ?, ?, ?, 'submitted', NOW())
");
$stmt->execute([$taskId, $user['id'], $note, $filePath]);

$stmt = $pdo->prepare("UPDATE tasks SET status = 'submitted', updated_at = NOW() WHERE id = ?");
$stmt->execute([$taskId]);

$stmt = $pdo->prepare("SELECT t.title, t.project_id, t.created_by, p.owner_id FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = ?");
$stmt->execute([$taskId]);
$task = $stmt->fetch();

log_activity($pdo, $user['id'], 'Bukti task dikirim', $task['title'], $task['project_id'], $taskId);

$stmt = $pdo->prepare("SELECT user_id FROM project_members WHERE project_id = ? AND role_in_project IN ('owner','manager')");
$stmt->execute([(int)$task['project_id']]);
$managerIds = array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
$targets = array_unique(array_filter(array_merge([(int)$task['created_by'], (int)$task['owner_id']], $managerIds)));
foreach ($targets as $targetId) {
    if ($targetId !== (int)$user['id']) {
        notify_user($pdo, $targetId, 'Bukti task dikirim', $task['title'], $task['project_id'], $taskId);
    }
}

sync_project_status($pdo, (int)$task['project_id']);

redirect('task-detail.php?id=' . $taskId);
