<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user=current_user();$month=max(1,min(12,(int)($_GET['month']??date('n'))));$year=max(2020,min(2100,(int)($_GET['year']??date('Y'))));$requestedFilter=$_GET['filter']??'all';$filter=in_array($requestedFilter,['all','open','submitted','approved','rejected'],true)?$requestedFilter:'all';
$first=sprintf('%04d-%02d-01',$year,$month);$last=date('Y-m-t',strtotime($first));$scope=$user['role']==='SUPERADMIN'?'1=1':'(pm.user_id=? OR ta.user_id=?)';$params=$user['role']==='SUPERADMIN'?[]:[$user['id'],$user['id']];$statusSql=$filter==='all'?'':' AND t.status=?';if($filter!=='all')$params[]=$filter;
$stmt=$pdo->prepare("SELECT DISTINCT t.id,t.title,t.description,t.deadline_at,p.title project_title FROM tasks t JOIN projects p ON p.id=t.project_id LEFT JOIN project_members pm ON pm.project_id=p.id LEFT JOIN task_assignees ta ON ta.task_id=t.id WHERE {$scope}{$statusSql} AND t.deleted_at IS NULL AND DATE(t.deadline_at) BETWEEN ? AND ? ORDER BY t.deadline_at");$stmt->execute(array_merge($params,[$first,$last]));
header('Content-Type: text/calendar; charset=utf-8');header('Content-Disposition: attachment; filename="iti-calendar-'.$year.'-'.$month.'.ics"');
echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//ITI//Project Manager//ID\r\n";
foreach($stmt->fetchAll() as $event){$escape=fn($v)=>str_replace(["\\",",",";","\r","\n"],["\\\\","\\,","\\;","","\\n"],(string)$v);echo "BEGIN:VEVENT\r\nUID:task-{$event['id']}@itipm\r\nDTSTAMP:".gmdate('Ymd\THis\Z')."\r\nDTSTART:".date('Ymd\THis',strtotime($event['deadline_at']))."\r\nSUMMARY:".$escape($event['title'])."\r\nDESCRIPTION:".$escape($event['project_title'].' - '.$event['description'])."\r\nEND:VEVENT\r\n";}
echo "END:VCALENDAR\r\n";
