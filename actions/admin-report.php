<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['SUPERADMIN', 'ADMIN'], true)) { http_response_code(403); die('Akses ditolak.'); }
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="iti-project-report-' . date('Y-m-d') . '.csv"');
$out=fopen('php://output','w');
fputcsv($out,['Project','Manager','Status','Deadline','Total Task','Task Selesai']);
$where = $user['role'] === 'SUPERADMIN' ? '1=1' : 'p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)';
$stmt = $pdo->prepare("SELECT p.title,u.name,p.status,p.deadline_at,(SELECT COUNT(*) FROM tasks t WHERE t.project_id=p.id AND t.deleted_at IS NULL) total_tasks,(SELECT COUNT(*) FROM tasks t WHERE t.project_id=p.id AND t.deleted_at IS NULL AND t.status='approved') done_tasks FROM projects p JOIN users u ON u.id=p.owner_id WHERE {$where} AND p.deleted_at IS NULL ORDER BY p.created_at DESC");
$stmt->execute($user['role'] === 'SUPERADMIN' ? [] : [$user['id']]);
$rows=$stmt->fetchAll();
foreach($rows as $row) fputcsv($out,$row);
fclose($out);
