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
 <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
  <div><h2 class="text-[28px] font-black tracking-tight">Tasks</h2><p class="mt-1 text-sm text-slate-500">Kelola semua tugas project</p></div>
  <div class="flex flex-wrap items-center gap-3">
   <form class="hidden min-w-[340px] items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 lg:flex" method="get"><input type="hidden" name="page" value="tasks"><button type="submit" class="text-slate-400"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button><input class="w-full border-0 bg-transparent text-xs outline-none" name="q" value="<?= e($search) ?>" placeholder="Cari task, project, atau assignee..."></form>
   <a class="dashboard-interactive-icon relative grid h-11 w-11 place-items-center rounded-xl border border-slate-200 bg-white" href="index.php?page=notifications"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg><?php if ($unreadSidebarCount): ?><span class="absolute -right-1 -top-1 rounded-full bg-orange-500 px-1.5 text-[9px] font-black text-white"><?= e($unreadSidebarCount) ?></span><?php endif; ?></a>
   <span class="grid h-10 w-10 place-items-center rounded-full bg-orange-100 font-black text-orange-600"><?= e(strtoupper(substr($user['name'],0,1))) ?></span><span class="hidden sm:block"><b class="block text-xs"><?= e($user['name']) ?></b><small class="text-slate-500"><?= e(role_label($user['role'])) ?></small></span>
   <?php if ($user['role'] !== 'USER' && $projectOptions): ?><button class="open-task-modal flex items-center gap-2 rounded-xl bg-orange-600 px-5 py-3 text-sm font-black text-white" type="button"><span class="text-xl">+</span> Buat Task</button><?php endif; ?>
  </div>
 </header>
 <?php if ($formError): ?><div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700"><?= e($formError) ?></div><?php endif; ?>
 <?php $kpis=[['Total Tasks',$totalTasks,($trend>=0?'+':'').$trend.'% dari bulan lalu','orange'],['Selesai',$counts['approved'],$totalTasks?round($counts['approved']/$totalTasks*100).'% dari total tasks':'0%','green'],['Berjalan',$activeCount,$totalTasks?round($activeCount/$totalTasks*100).'% dari total tasks':'0%','amber'],['Terlambat',$overdueCount,$totalTasks?round($overdueCount/$totalTasks*100).'% dari total tasks':'0%','red']]; ?>
 <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><?php foreach($kpis as $i=>[$label,$value,$note,$tone]): ?><article class="task-kpi rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" style="--task-delay:<?= e($i*60) ?>ms"><div class="flex items-center gap-5"><span class="grid h-16 w-16 shrink-0 place-items-center rounded-full <?= $tone==='green'?'bg-green-100 text-green-600':($tone==='red'?'bg-red-100 text-red-600':($tone==='amber'?'bg-amber-100 text-amber-600':'bg-orange-100 text-orange-600')) ?>"><svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 5H6v16h12V5h-3M9 3h6v4H9z"/><path d="m9 14 2 2 4-5"/></svg></span><div><p class="text-sm text-slate-500"><?= e($label) ?></p><strong class="block text-3xl font-black"><?= e($value) ?></strong><span class="text-[11px] <?= $tone==='red'?'text-red-600':'text-green-600' ?>"><?= e($note) ?></span></div></div></article><?php endforeach; ?></section>
 <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><form class="grid gap-3 lg:grid-cols-[1.35fr_.9fr_.9fr_.9fr_.9fr_auto]" method="get"><input type="hidden" name="page" value="tasks"><label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4"><svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input class="w-full border-0 bg-transparent py-3 text-sm outline-none" name="q" value="<?= e($search) ?>" placeholder="Cari task..."></label><select class="rounded-xl border border-slate-200 px-4 py-3" name="status"><option value="">Semua Status</option><?php foreach($statusLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= $statusFilter===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><select class="rounded-xl border border-slate-200 px-4 py-3" name="project_id"><option value="0">Semua Project</option><?php foreach($filterProjects as $p): ?><option value="<?= e($p['id']) ?>" <?= $projectFilter===(int)$p['id']?'selected':'' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select><select class="rounded-xl border border-slate-200 px-4 py-3" name="assignee"><option value="0">Semua Assignee</option><?php foreach($filterAssignees as $a): ?><option value="<?= e($a['id']) ?>" <?= $assigneeFilter===(int)$a['id']?'selected':'' ?>><?= e($a['name']) ?></option><?php endforeach; ?></select><select class="rounded-xl border border-slate-200 px-4 py-3" name="priority"><option value="">Semua Priority</option><?php foreach(['high'=>'High','medium'=>'Medium','low'=>'Low'] as $key=>$label): ?><option value="<?= e($key) ?>" <?= $priorityFilter===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><div class="flex gap-2"><a class="flex items-center rounded-xl border border-slate-200 px-4 font-bold" href="index.php?page=tasks">Reset</a><button class="rounded-xl bg-orange-600 px-5 py-3 font-black text-white">Filter</button></div></form></section>
 <section class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="data-table-scroll"><table class="w-full table-fixed text-left text-xs"><thead><tr><th class="w-[23%] px-5 py-4">Task</th><th class="w-[15%] px-4">Project</th><th class="w-[14%] px-4">Assignee</th><th class="w-[11%] px-4">Status</th><th class="w-[13%] px-4">Progress</th><th class="w-[8%] px-4">Priority</th><th class="w-[11%] px-4">Deadline</th><th class="w-[5%] px-4 text-center">Actions</th></tr></thead><tbody class="divide-y divide-slate-100">
 <?php $colors=['bg-blue-100 text-blue-600','bg-green-100 text-green-600','bg-purple-100 text-purple-600','bg-amber-100 text-amber-600','bg-red-100 text-red-600']; foreach($tasks as $i=>$task): $meta=task_list_meta($task); ?><tr class="task-row"><td class="px-5 py-4"><a class="flex items-center gap-3" href="actions/task-detail.php?id=<?= e($task['id']) ?>"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg <?= e($colors[$i%5]) ?>"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H6v16h12V5h-3M9 3h6v4H9z"/></svg></span><span><b class="block max-w-[220px] truncate"><?= e($task['title']) ?></b><small class="block max-w-[220px] truncate text-slate-400"><?= e($task['description']?:'Tanpa deskripsi') ?></small></span></a></td><td class="px-4"><a class="font-bold" href="actions/project-detail.php?id=<?= e($task['project_id']) ?>"><?= e($task['project_title']) ?></a></td><td class="px-4"><span class="flex items-center gap-2"><i class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-orange-100 font-black not-italic text-orange-600"><?= e(strtoupper(substr($task['assignee_names']?:'-',0,1))) ?></i><span class="max-w-[145px] truncate"><?= e($task['assignee_names']?:'-') ?></span></span></td><td class="px-4"><span class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 font-bold <?= e($meta['tone']) ?>"><i class="h-2 w-2 rounded-full bg-current"></i><?= e($meta['label']) ?></span></td><td class="px-4"><div class="min-w-[140px]"><b><?= e($meta['progress']) ?>%</b><span class="mt-1 block h-1.5 rounded-full bg-slate-100"><i class="task-progress block h-1.5 rounded-full <?= $meta['progress']===100?'bg-green-500':'bg-orange-500' ?>" style="width:<?= e($meta['progress']) ?>%"></i></span></div></td><td class="px-4"><span class="rounded-lg px-3 py-1.5 font-bold <?= e($meta['priorityTone']) ?>"><?= e($meta['priority']) ?></span></td><td class="px-4 whitespace-nowrap"><b class="block font-medium"><?= e(date('d M Y',strtotime($task['deadline_at']))) ?></b><small class="<?= $meta['overdue']?'text-red-600':'text-orange-600' ?>"><?= $task['status']==='approved'?'Selesai':($meta['overdue']?e(abs($meta['days'])).' hari terlambat':e(max(0,$meta['days'])).' hari lagi') ?></small></td><td class="px-4 text-center"><a class="inline-grid h-9 w-9 place-items-center rounded-lg border border-slate-200 hover:bg-orange-50" href="actions/task-detail.php?id=<?= e($task['id']) ?>"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z"/><circle cx="12" cy="12" r="2.5"/></svg></a></td></tr><?php endforeach; ?></tbody></table></div>
 <?php if(!$tasks): ?><p class="p-10 text-center text-sm text-slate-500">Tidak ada task yang cocok dengan filter.</p><?php endif; ?><footer class="flex flex-col gap-4 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-slate-500">Menampilkan <?= $filteredCount?e($offset+1):0 ?> - <?= e(min($offset+$perPage,$filteredCount)) ?> dari <?= e($filteredCount) ?> tasks</p><nav class="flex gap-1"><?php for($n=max(1,$currentPage-2);$n<=min($totalPages,$currentPage+2);$n++): ?><a class="grid h-9 w-9 place-items-center rounded-lg border <?= $n===$currentPage?'border-orange-500 bg-orange-500 text-white':'border-slate-200' ?>" href="<?= e(tasks_url(['p'=>$n])) ?>"><?= e($n) ?></a><?php endfor; ?></nav><form method="get"><input type="hidden" name="page" value="tasks"><input type="hidden" name="q" value="<?= e($search) ?>"><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><input type="hidden" name="project_id" value="<?= e($projectFilter) ?>"><input type="hidden" name="assignee" value="<?= e($assigneeFilter) ?>"><input type="hidden" name="priority" value="<?= e($priorityFilter) ?>"><select class="rounded-lg border border-slate-200 px-3 py-2 text-xs" name="per_page" onchange="this.form.submit()"><?php foreach([5,10,20,50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage===$size?'selected':'' ?>><?= e($size) ?> per halaman</option><?php endforeach; ?></select></form></footer></section>
</div>
<?php if($user['role']!=='USER' && $projectOptions): ?><div class="task-modal fixed inset-0 z-50 <?= $formError?'flex':'hidden' ?> items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"><form method="post" class="task-modal-panel max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl"><input type="hidden" name="create_task" value="1"><?= csrf_field() ?><div class="flex justify-between"><div><h3 class="text-2xl font-black">Buat Task Baru</h3><p class="text-sm text-slate-500">Tambahkan task dan pilih penerima project.</p></div><button class="close-task-modal text-2xl" type="button">×</button></div><div class="mt-5 grid gap-4 md:grid-cols-2"><select class="rounded-xl border border-slate-200 px-4 py-3" name="project_id" id="taskProjectSelect" required><?php foreach($projectOptions as $p): ?><option value="<?= e($p['id']) ?>" <?= (int)$oldInput['project_id']===(int)$p['id']?'selected':'' ?>><?= e($p['title']) ?></option><?php endforeach; ?></select><input class="rounded-xl border border-slate-200 px-4 py-3" name="deadline_at" type="datetime-local" required value="<?= e($oldInput['deadline_at']) ?>"></div><input class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-3" name="title" placeholder="Judul task" required value="<?= e($oldInput['title']) ?>"><textarea class="mt-4 w-full rounded-xl border border-slate-200 p-4" name="description" rows="3" placeholder="Deskripsi"><?= e($oldInput['description']) ?></textarea><div class="mt-5"><p class="text-sm font-black">Penerima Task</p><div class="mt-3"><?php foreach($projectOptions as $p): $members=$projectMembersByProject[(int)$p['id']]??[]; ?><div class="task-assignee-group grid gap-2 md:grid-cols-2" data-project-id="<?= e($p['id']) ?>"><?php foreach($members as $member): ?><label class="task-assignee-option flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3"><input class="mt-1" type="checkbox" name="assignee_ids[]" value="<?= e($member['id']) ?>" <?= in_array((int)$member['id'],$oldInput['assignee_ids'],true)?'checked':'' ?>><span><b class="block text-sm"><?= e($member['name']) ?></b><small class="text-slate-500"><?= e($member['role']) ?> - <?= e($member['unit']) ?></small></span></label><?php endforeach; ?></div><?php endforeach; ?></div></div><div class="mt-6 flex justify-end gap-3"><button class="close-task-modal rounded-xl border border-slate-200 px-5 py-3" type="button">Batal</button><button class="rounded-xl bg-orange-600 px-6 py-3 font-black text-white">Buat Task</button></div></form></div><?php endif; ?>
<script>(()=>{const modal=document.querySelector('.task-modal'),select=document.getElementById('taskProjectSelect'),groups=document.querySelectorAll('.task-assignee-group'),refresh=()=>groups.forEach(g=>{const active=g.dataset.projectId===select?.value;g.style.display=active?'grid':'none';if(!active)g.querySelectorAll('input').forEach(i=>i.checked=false)});document.querySelectorAll('.open-task-modal').forEach(b=>b.addEventListener('click',()=>{modal?.classList.remove('hidden');modal?.classList.add('flex')}));document.querySelectorAll('.close-task-modal').forEach(b=>b.addEventListener('click',()=>{modal?.classList.add('hidden');modal?.classList.remove('flex')}));modal?.addEventListener('click',e=>{if(e.target===modal){modal.classList.add('hidden');modal.classList.remove('flex')}});select?.addEventListener('change',refresh);refresh();requestAnimationFrame(()=>document.querySelectorAll('.task-progress').forEach(b=>b.classList.add('is-ready')));if(new URLSearchParams(location.search).get('create')==='1')document.querySelector('.open-task-modal')?.click()})();</script>
