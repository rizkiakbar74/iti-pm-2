<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user = current_user();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$id, $user['id']]);
$n = $stmt->fetch();

if (!$n) {
    redirect('../index.php?page=notifications');
}

$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user['id']]);

if (!empty($n['task_id'])) {
    redirect('task-detail.php?id=' . (int)$n['task_id']);
}
if (!empty($n['project_id'])) {
    redirect('project-detail.php?id=' . (int)$n['project_id']);
}
redirect('../index.php?page=notifications');
