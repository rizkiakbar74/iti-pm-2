<?php
$visibilitySql = $user['role'] === 'SUPERADMIN'
    ? '1=1'
    : '(EXISTS (SELECT 1 FROM project_members vpm WHERE vpm.project_id = p.id AND vpm.user_id = ?) OR EXISTS (SELECT 1 FROM task_assignees vta WHERE vta.task_id = t.id AND vta.user_id = ?))';
$visibilityParams = $user['role'] === 'SUPERADMIN' ? [] : [(int)$user['id'], (int)$user['id']];
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$projectFilter = (int)($_GET['project_id'] ?? 0);
$assigneeFilter = (int)($_GET['assignee'] ?? 0);
$priorityFilter = $_GET['priority'] ?? '';
$allowedStatuses = ['open', 'submitted', 'approved', 'rejected', 'overdue'];
if (!in_array($statusFilter, $allowedStatuses, true)) $statusFilter = '';
if (!in_array($priorityFilter, ['high', 'medium', 'low'], true)) $priorityFilter = '';
$perPage = (int)($_GET['per_page'] ?? 5);
if (!in_array($perPage, [5, 10, 20, 50], true)) $perPage = 5;
$currentPage = max(1, (int)($_GET['p'] ?? 1));

$baseWhere = [$visibilitySql, 't.deleted_at IS NULL', 'p.deleted_at IS NULL'];
$baseParams = $visibilityParams;
$where = $baseWhere;
$params = $baseParams;
if ($search !== '') {
    $where[] = "(t.title LIKE ? OR t.description LIKE ? OR p.title LIKE ? OR EXISTS (SELECT 1 FROM task_assignees sta JOIN users su ON su.id = sta.user_id WHERE sta.task_id = t.id AND su.name LIKE ?))";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($projectFilter > 0) { $where[] = 'p.id = ?'; $params[] = $projectFilter; }
if ($assigneeFilter > 0) { $where[] = 'EXISTS (SELECT 1 FROM task_assignees fta WHERE fta.task_id = t.id AND fta.user_id = ?)'; $params[] = $assigneeFilter; }
if ($statusFilter === 'overdue') $where[] = "t.deadline_at < NOW() AND t.status <> 'approved'";
elseif ($statusFilter !== '') { $where[] = 't.status = ?'; $params[] = $statusFilter; }
if ($priorityFilter === 'high') $where[] = "t.status <> 'approved' AND t.deadline_at <= DATE_ADD(NOW(), INTERVAL 3 DAY)";
if ($priorityFilter === 'medium') $where[] = "t.status <> 'approved' AND t.deadline_at > DATE_ADD(NOW(), INTERVAL 3 DAY) AND t.deadline_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)";
if ($priorityFilter === 'low') $where[] = "(t.status = 'approved' OR t.deadline_at > DATE_ADD(NOW(), INTERVAL 7 DAY))";
$baseSql = implode(' AND ', $baseWhere);
$whereSql = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE {$baseSql}");
$stmt->execute($baseParams);
$totalTasks = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT t.status, COUNT(*) total FROM tasks t JOIN projects p ON p.id=t.project_id WHERE {$baseSql} GROUP BY t.status");
$stmt->execute($baseParams);
$counts = array_fill_keys(['open','submitted','approved','rejected'], 0);
foreach ($stmt->fetchAll() as $row) $counts[$row['status']] = (int)$row['total'];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE {$baseSql} AND t.deadline_at<NOW() AND t.status<>'approved'");
$stmt->execute($baseParams);
$overdueCount = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE {$baseSql} AND t.created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$stmt->execute($baseParams);
$beforeMonth = (int)$stmt->fetchColumn();
$trend = $beforeMonth ? (int)round((($totalTasks - $beforeMonth) / $beforeMonth) * 100) : ($totalTasks ? 100 : 0);

$stmt = $pdo->prepare("SELECT DISTINCT p.id,p.title FROM tasks t JOIN projects p ON p.id=t.project_id WHERE {$baseSql} ORDER BY p.title");
$stmt->execute($baseParams);
$filterProjects = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT DISTINCT u.id,u.name FROM tasks t JOIN projects p ON p.id=t.project_id JOIN task_assignees ta ON ta.task_id=t.id JOIN users u ON u.id=ta.user_id WHERE {$baseSql} ORDER BY u.name");
$stmt->execute($baseParams);
$filterAssignees = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t JOIN projects p ON p.id=t.project_id WHERE {$whereSql}");
$stmt->execute($params);
$filteredCount = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($filteredCount / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;
$stmt = $pdo->prepare("
 SELECT t.*,p.title project_title,
 (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ', ') FROM task_assignees ta JOIN users u ON u.id=ta.user_id WHERE ta.task_id=t.id) assignee_names
 FROM tasks t JOIN projects p ON p.id=t.project_id
 WHERE {$whereSql}
 ORDER BY CASE WHEN t.status<>'approved' AND t.deadline_at<NOW() THEN 0 ELSE 1 END,t.deadline_at
 LIMIT {$perPage} OFFSET {$offset}");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

function tasks_url($overrides = []) {
    $query = array_merge($_GET, $overrides, ['page' => 'tasks']);
    foreach ($query as $key => $value) if ($value === '' || $value === null || $value === 0 || $value === '0') unset($query[$key]);
    return 'index.php?' . http_build_query($query);
}
function task_list_meta($task) {
    $overdue = $task['status'] !== 'approved' && strtotime($task['deadline_at']) < time();
    $map = [
        'open'=>['Berjalan','bg-amber-50 text-orange-600',25],
        'submitted'=>['Review','bg-blue-50 text-blue-600',75],
        'approved'=>['Selesai','bg-green-50 text-green-600',100],
        'rejected'=>['Revisi','bg-red-50 text-red-600',45],
    ];
    [$label,$tone,$progress] = $map[$task['status']] ?? [$task['status'],'bg-slate-100 text-slate-600',0];
    if ($overdue) [$label,$tone] = ['Terlambat','bg-red-50 text-red-600'];
    $days = (int)ceil((strtotime($task['deadline_at']) - time()) / 86400);
    if ($task['status'] === 'approved' || $days > 7) [$priority,$priorityTone] = ['Low','bg-blue-50 text-blue-600'];
    elseif ($days > 3) [$priority,$priorityTone] = ['Medium','bg-amber-50 text-amber-600'];
    else [$priority,$priorityTone] = ['High','bg-red-50 text-red-600'];
    return compact('overdue','label','tone','progress','days','priority','priorityTone');
}
$statusLabels = ['open'=>'Berjalan','submitted'=>'Review','approved'=>'Selesai','rejected'=>'Revisi','overdue'=>'Terlambat'];
$activeCount = $counts['open'] + $counts['submitted'] + $counts['rejected'];
?>
<div class="tasks-page">
 <header class="task-management-header">
  <div><p>Dashboard / Task</p><h2>Task Management</h2><span>Kelola dan pantau semua task dalam project.</span></div>
  <div class="flex flex-wrap items-center gap-3">
   <?php if (in_array($user['role'],['SUPERADMIN','ADMIN'],true)): ?><a class="task-export-button" href="<?= e(app_url('actions/admin-report.php')) ?>">Export</a><?php endif; ?>
   <a class="dashboard-interactive-icon relative grid h-11 w-11 place-items-center rounded-xl border border-slate-200 bg-white" href="index.php?page=notifications"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg><?php if ($unreadSidebarCount): ?><span class="absolute -right-1 -top-1 rounded-full bg-orange-500 px-1.5 text-[9px] font-black text-white"><?= e($unreadSidebarCount) ?></span><?php endif; ?></a>
   <span class="grid h-10 w-10 place-items-center rounded-full bg-orange-100 font-black text-orange-600"><?= e(strtoupper(substr($user['name'],0,1))) ?></span><span class="hidden sm:block"><b class="block text-xs"><?= e($user['name']) ?></b><small class="text-slate-500"><?= e(role_label($user['role'])) ?></small></span>
   <?php if ($user['role'] !== 'USER' && $projectOptions): ?><button class="open-task-modal task-create-button" type="button"><span>+</span> Buat Task Baru</button><?php endif; ?>
  </div>
 </header>
 <?php if ($formError): ?><div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700"><?= e($formError) ?></div><?php endif; ?>
 <?php $kpis=[['Total Task',$totalTasks,($trend>=0?'+':'').$trend.'% dari bulan lalu','orange'],['On Progress',$activeCount,$totalTasks?round($activeCount/$totalTasks*100).'% dari total task':'0%','blue'],['Overdue',$overdueCount,$totalTasks?round($overdueCount/$totalTasks*100).'% dari total task':'0%','amber'],['Selesai',$counts['approved'],$totalTasks?round($counts['approved']/$totalTasks*100).'% dari total task':'0%','green'],['Belum Mulai',$counts['open'],$totalTasks?round($counts['open']/$totalTasks*100).'% dari total task':'0%','purple']]; ?>
 <section class="task-management-kpis"><?php foreach($kpis as $i=>[$label,$value,$note,$tone]): ?><article class="task-kpi task-management-kpi tone-<?= e($tone) ?>" style="--task-delay:<?= e($i*60) ?>ms"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5H6v16h12V5h-3M9 3h6v4H9z"/><path d="m9 14 2 2 4-5"/></svg></span><div><p><?= e($label) ?></p><strong><?= e($value) ?></strong><small><?= e($note) ?></small></div></article><?php endforeach; ?></section>
 <section class="task-management-filter"><form method="get"><input type="hidden" name="page" value="tasks"><label class="task-management-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="q" value="<?= e($search) ?>" placeholder="Cari task atau keyword..."></label><select name="project_id"><option value="0">Semua Project</option><?php foreach($filterProjects as $p): ?><option value="<?= e($p['id']) ?>" <?= $projectFilter===(int)$p['id']?'selected':'' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select><select name="priority"><option value="">Semua Priority</option><?php foreach(['high'=>'High','medium'=>'Medium','low'=>'Low'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $priorityFilter===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><select name="status"><option value="">Semua Status</option><?php foreach($statusLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $statusFilter===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><select name="assignee"><option value="0">Semua Assignee</option><?php foreach($filterAssignees as $a): ?><option value="<?= e($a['id']) ?>" <?= $assigneeFilter===(int)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select><button type="submit">Filter</button><a href="index.php?page=tasks">Reset</a></form></section>
 <section class="task-management-table"><div class="data-table-scroll"><table><thead><tr><th>Task</th><th>Project</th><th>Assignee</th><th>Priority</th><th>Deadline</th><th>Status</th><th>Progress</th><th>Aksi</th></tr></thead><tbody>
 <?php foreach($tasks as $task): $meta=task_list_meta($task); ?><tr class="task-row"><td><a class="task-title-cell" href="actions/task-detail.php?id=<?= e($task['id']) ?>"><b><?= e($task['title']) ?></b><small><?= e($task['description']?:'Tanpa deskripsi') ?></small></a></td><td><a class="task-project-cell" href="actions/project-detail.php?id=<?= e($task['project_id']) ?>"><b><?= e($task['project_title']) ?></b><small>Project aktif</small></a></td><td><span class="task-assignee-cell"><i><?= e(strtoupper(substr($task['assignee_names']?:'-',0,1))) ?></i><span><?= e($task['assignee_names']?:'-') ?></span></span></td><td><span class="task-priority <?= e($meta['priorityTone']) ?>"><?= e($meta['priority']) ?></span></td><td><span class="task-deadline <?= $meta['overdue']?'is-overdue':'' ?>"><b><?= e(date('d M Y',strtotime($task['deadline_at']))) ?></b><small><?= $task['status']==='approved'?'Selesai':($meta['overdue']?e(abs($meta['days'])).' hari terlambat':e(max(0,$meta['days'])).' hari lagi') ?></small></span></td><td><span class="task-status <?= e($meta['tone']) ?>"><?= e($meta['label']) ?></span></td><td><div class="task-progress-cell"><span><i class="task-progress <?= $meta['progress']===100?'is-complete':'' ?>" style="width:<?= e($meta['progress']) ?>%"></i></span><b><?= e($meta['progress']) ?>%</b></div></td><td><a class="task-view-action" href="actions/task-detail.php?id=<?= e($task['id']) ?>" aria-label="Lihat task"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/><circle cx="12" cy="12" r="2.5"/></svg></a></td></tr><?php endforeach; ?></tbody></table></div>
 <?php if(!$tasks): ?><p class="task-empty-state">Tidak ada task yang cocok dengan filter.</p><?php endif; ?><footer class="task-table-footer"><p>Menampilkan <?= $filteredCount?e($offset+1):0 ?> - <?= e(min($offset+$perPage,$filteredCount)) ?> dari <?= e($filteredCount) ?> task</p><nav><?php for($n=max(1,$currentPage-2);$n<=min($totalPages,$currentPage+2);$n++): ?><a class="<?= $n===$currentPage?'is-active':'' ?>" href="<?= e(tasks_url(['p'=>$n])) ?>"><?= e($n) ?></a><?php endfor; ?></nav><form method="get"><input type="hidden" name="page" value="tasks"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><input type="hidden" name="project_id" value="<?= e($projectFilter) ?>"><input type="hidden" name="assignee" value="<?= e($assigneeFilter) ?>"><input type="hidden" name="priority" value="<?= e($priorityFilter) ?>"><select name="per_page" onchange="this.form.submit()"><?php foreach([5,10,20,50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage===$size?'selected':'' ?>><?= e($size) ?> / halaman</option><?php endforeach; ?></select></form></footer></section>
</div>
<?php if($user['role']!=='USER' && $projectOptions): ?><div class="task-modal fixed inset-0 z-50 <?= $formError?'flex':'hidden' ?> items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"><form method="post" class="task-modal-panel max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl"><input type="hidden" name="create_task" value="1"><?= csrf_field() ?><div class="flex justify-between"><div><h3 class="text-2xl font-black">Buat Task Baru</h3><p class="text-sm text-slate-500">Tambahkan task dan pilih penerima project.</p></div><button class="close-task-modal text-2xl" type="button">×</button></div><div class="mt-5 grid gap-4 md:grid-cols-2"><select class="rounded-xl border border-slate-200 px-4 py-3" name="project_id" id="taskProjectSelect" required><?php foreach($projectOptions as $p): ?><option value="<?= e($p['id']) ?>" <?= (int)$oldInput['project_id']===(int)$p['id']?'selected':'' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select><input class="rounded-xl border border-slate-200 px-4 py-3" name="deadline_at" type="datetime-local" required value="<?= e($oldInput['deadline_at']) ?>"></div><input class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-3" name="title" placeholder="Judul task" required value="<?= e($oldInput['title']) ?>"><textarea class="mt-4 w-full rounded-xl border border-slate-200 p-4" name="description" rows="3" placeholder="Deskripsi"><?= e($oldInput['description']) ?></textarea><div class="mt-5"><p class="text-sm font-black">Penerima Task</p><div class="mt-3"><?php foreach($projectOptions as $p): $members=$projectMembersByProject[(int)$p['id']]??[]; ?><div class="task-assignee-group grid gap-2 md:grid-cols-2" data-project-id="<?= e($p['id']) ?>"><?php foreach($members as $member): ?><label class="task-assignee-option flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3"><input class="mt-1" type="checkbox" name="assignee_ids[]" value="<?= e($member['id']) ?>" <?= in_array((int)$member['id'],$oldInput['assignee_ids'],true)?'checked':'' ?>><span><b class="block text-sm"><?= e($member['name']) ?></b><small class="text-slate-500"><?= e($member['role']) ?> - <?= e($member['unit']) ?></small></span></label><?php endforeach; ?></div><?php endforeach; ?></div></div><div class="mt-6 flex justify-end gap-3"><button class="close-task-modal rounded-xl border border-slate-200 px-5 py-3" type="button">Batal</button><button class="rounded-xl bg-orange-600 px-6 py-3 font-black text-white">Buat Task</button></div></form></div><?php endif; ?>
<script>(()=>{const modal=document.querySelector('.task-modal'),select=document.getElementById('taskProjectSelect'),groups=document.querySelectorAll('.task-assignee-group'),refresh=()=>groups.forEach(g=>{const active=g.dataset.projectId===select?.value;g.style.display=active?'grid':'none';if(!active)g.querySelectorAll('input').forEach(i=>i.checked=false)});document.querySelectorAll('.open-task-modal').forEach(b=>b.addEventListener('click',()=>{modal?.classList.remove('hidden');modal?.classList.add('flex')}));document.querySelectorAll('.close-task-modal').forEach(b=>b.addEventListener('click',()=>{modal?.classList.add('hidden');modal?.classList.remove('flex')}));modal?.addEventListener('click',e=>{if(e.target===modal){modal.classList.add('hidden');modal.classList.remove('flex')}});select?.addEventListener('change',refresh);refresh();requestAnimationFrame(()=>document.querySelectorAll('.task-progress').forEach(b=>b.classList.add('is-ready')));if(new URLSearchParams(location.search).get('create')==='1')document.querySelector('.open-task-modal')?.click()})();</script>
