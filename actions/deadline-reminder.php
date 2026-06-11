<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    die('Method tidak diizinkan.');
}

verify_csrf();

$user = current_user();
if (!in_array($user['role'], ['SUPERADMIN','ADMIN','MODERATOR'], true)) {
    die('Akses ditolak.');
}

if ($user['role'] === 'SUPERADMIN') {
    $projectWhere = "1=1";
    $params = [];
} else {
    $projectWhere = "p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)";
    $params = [$user['id']];
}

$stmt = $pdo->prepare("
    SELECT t.id, t.title, t.deadline_at, t.project_id, p.title AS project_title
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    WHERE {$projectWhere}
      AND t.deleted_at IS NULL
      AND t.status <> 'approved'
      AND (
        t.deadline_at < NOW()
        OR DATE(t.deadline_at) = CURDATE()
        OR t.deadline_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)
      )
    LIMIT 100
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

$notificationsCreated = 0;
foreach ($tasks as $task) {
    $stmt = $pdo->prepare("SELECT user_id FROM task_assignees WHERE task_id = ?");
    $stmt->execute([$task['id']]);
    $assignees = array_map('intval', array_column($stmt->fetchAll(), 'user_id'));

    foreach ($assignees as $assigneeId) {
        $title = 'Reminder deadline task';
        $message = $task['title'] . ' - ' . $task['project_title'] . ' - deadline ' . date('d M Y H:i', strtotime($task['deadline_at']));
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = ? AND task_id = ? AND title = ? AND created_at >= CURDATE()
        ");
        $stmt->execute([$assigneeId, $task['id'], $title]);
        if ((int)$stmt->fetchColumn() > 0) {
            continue;
        }
        notify_user($pdo, $assigneeId, $title, $message, $task['project_id'], $task['id']);
        $notificationsCreated++;
    }
}

if ($notificationsCreated === 0) {
    notify_user(
        $pdo,
        $user['id'],
        'Reminder deadline',
        'Tidak ada task deadline yang perlu dikirim reminder saat ini.',
        null,
        null
    );
    $notificationsCreated = 1;
} else {
    notify_user(
        $pdo,
        $user['id'],
        'Reminder deadline dibuat',
        count($tasks) . ' task deadline diproses dan ' . $notificationsCreated . ' notifikasi dikirim.',
        null,
        null
    );
    $notificationsCreated++;
}

log_activity($pdo, $user['id'], 'Reminder deadline dibuat', count($tasks) . ' task deadline diproses, ' . $notificationsCreated . ' notifikasi dibuat');
redirect('../index.php?page=notifications&filter=unread');
