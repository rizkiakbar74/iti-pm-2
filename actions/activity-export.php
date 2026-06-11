<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
$stmt = $pdo->query("SELECT a.user_id,a.created_at,u.name,u.role,a.action,a.detail,a.project_id,a.task_id FROM activity_logs a JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 5000");
$rows = $stmt->fetchAll();
if ($user['role'] !== 'SUPERADMIN') {
    $rows = array_values(array_filter($rows, fn($row) => role_rank($row['role']) < role_rank($user['role']) || (int)$row['user_id'] === (int)$user['id']));
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="iti-activity-log-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$out = fopen('php://output','w');
fputcsv($out,['Waktu','User','Role','Aksi','Detail','Project ID','Task ID']);
foreach($rows as $row) {
    unset($row['user_id']);
    fputcsv($out,array_map('safe_csv_cell',$row));
}
fclose($out);
