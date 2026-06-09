<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
verify_csrf();

$user = current_user();
$action = $_POST['action'] ?? 'add_comment';
$taskId = (int)($_POST['task_id'] ?? 0);

if (!$taskId || !can_see_task($pdo, $taskId, $user)) {
    die('Akses ditolak.');
}

if ($action === 'add_comment') {
    $body = trim($_POST['body'] ?? '');
    if ($body === '') {
        die('Komentar tidak boleh kosong.');
    }
    $stmt = $pdo->prepare("INSERT INTO task_comments (task_id, user_id, body, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$taskId, $user['id'], $body]);

    $stmt = $pdo->prepare("SELECT t.title, t.project_id, t.created_by, p.owner_id FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    log_activity($pdo, $user['id'], 'Komentar task ditambahkan', $task['title'], $task['project_id'], $taskId);

    $stmt = $pdo->prepare("
        SELECT DISTINCT user_id FROM (
            SELECT created_by AS user_id FROM tasks WHERE id = ?
            UNION
            SELECT owner_id AS user_id FROM projects WHERE id = ?
            UNION
            SELECT user_id FROM task_assignees WHERE task_id = ?
        ) x
    ");
    $stmt->execute([$taskId, (int)$task['project_id'], $taskId]);
    $targets = array_map('intval', array_column($stmt->fetchAll(), 'user_id'));
    foreach ($targets as $targetId) {
        if ($targetId !== (int)$user['id']) {
            notify_user($pdo, $targetId, 'Komentar task baru', $task['title'], $task['project_id'], $taskId);
        }
    }
}

if ($action === 'delete_comment') {
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM task_comments WHERE id = ? AND task_id = ? AND deleted_at IS NULL");
    $stmt->execute([$commentId, $taskId]);
    $comment = $stmt->fetch();
    if (!$comment) {
        die('Komentar tidak ditemukan.');
    }

    $stmt = $pdo->prepare("SELECT t.*, p.owner_id FROM tasks t JOIN projects p ON p.id = t.project_id WHERE t.id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    $canDelete = (int)$comment['user_id'] === (int)$user['id'] || $user['role'] === 'SUPERADMIN' || can_review_task($pdo, $task, ['owner_id' => $task['owner_id']], $user);
    if (!$canDelete) {
        die('Akses ditolak. Kamu tidak boleh menghapus komentar ini.');
    }

    $stmt = $pdo->prepare("UPDATE task_comments SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$commentId]);
    log_activity($pdo, $user['id'], 'Komentar task dihapus', $task['title'], $task['project_id'], $taskId);
}

redirect('task-detail.php?id=' . $taskId);
