<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['SUPERADMIN', 'ADMIN'], true)) { http_response_code(403); die('Akses ditolak.'); }
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="iti-project-backup-' . date('Y-m-d-His') . '.json"');
$backup=['generated_at'=>date(DATE_ATOM),'generated_by'=>$user['email'],'tables'=>[]];
foreach(['users','projects','project_members','tasks','task_assignees','task_submissions','task_comments','notifications','activity_logs'] as $table) {
    $query = $table === 'users'
        ? "SELECT id, name, email, role, unit, status, created_by, created_at, updated_at, deleted_at FROM users"
        : "SELECT * FROM {$table}";
    $backup['tables'][$table]=$pdo->query($query)->fetchAll();
}
echo json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
