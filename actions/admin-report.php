<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['SUPERADMIN', 'ADMIN'], true)) { http_response_code(403); die('Akses ditolak.'); }
$from=preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['from']??'')?$_GET['from']:date('Y-m-01',strtotime('-5 months'));$to=preg_match('/^\d{4}-\d{2}-\d{2}$/',$_GET['to']??'')?$_GET['to']:date('Y-m-d');if($from>$to){[$from,$to]=[$to,$from];}$fromDate=$from.' 00:00:00';$toDate=$to.' 23:59:59';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="iti-project-report-' . $from . '-to-' . $to . '.csv"');
$out=fopen('php://output','w');
fputcsv($out,['Project','Manager','Status','Deadline','Total Task','Task Selesai']);
$where = $user['role'] === 'SUPERADMIN' ? '1=1' : 'p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)';
$stmt = $pdo->prepare("SELECT p.title,u.name,p.status,p.deadline_at,(SELECT COUNT(*) FROM tasks t WHERE t.project_id=p.id AND t.deleted_at IS NULL AND t.created_at BETWEEN ? AND ?) total_tasks,(SELECT COUNT(*) FROM tasks t WHERE t.project_id=p.id AND t.deleted_at IS NULL AND t.status='approved' AND t.created_at BETWEEN ? AND ?) done_tasks FROM projects p JOIN users u ON u.id=p.owner_id WHERE {$where} AND p.deleted_at IS NULL ORDER BY p.created_at DESC");
$params=[$fromDate,$toDate,$fromDate,$toDate];if($user['role']!=='SUPERADMIN')$params[]=$user['id'];$stmt->execute($params);
$rows=$stmt->fetchAll();
foreach($rows as $row) fputcsv($out,$row);
fclose($out);
