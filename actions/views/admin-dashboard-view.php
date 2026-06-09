<?php
$projectTotalForDonut = max(1, array_sum($adminProjectStatus));
$priorityTotal = max(1, (int)$adminPriority['high_count'] + (int)$adminPriority['medium_count'] + (int)$adminPriority['low_count']);
$adminChartMax = max(array_merge([1], array_values(array_map(fn($day) => max($day['projects'], $day['tasks'], $day['completed']), $adminDays))));
$adminKpis = [
    ['Total Project', $totalProjects, $projectTrend, 'folder', 'orange', 'index.php?page=projects'],
    ['Total Task', $totalTasks, $taskTrend, 'task', 'blue', 'index.php?page=tasks'],
    ['Deadline Dekat', $dueNextSevenDays, $dueToday, 'clock', 'amber', 'index.php?page=deadlines'],
    ['Task Selesai', $completionRate, $done, 'check', 'green', 'index.php?page=tasks&status=approved'],
    ['Total User', $totalUsers, $userTrend, 'users', 'purple', 'index.php?page=users'],
];
$projectSegments = [
    ['key'=>'project-completed','label'=>'Selesai','value'=>$adminProjectStatus['completed'],'color'=>'#22c55e'],
    ['key'=>'project-active','label'=>'On Progress','value'=>$adminProjectStatus['active'],'color'=>'#3b82f6'],
    ['key'=>'project-review','label'=>'Tertunda','value'=>$adminProjectStatus['review'],'color'=>'#f97316'],
    ['key'=>'project-draft','label'=>'Belum Mulai','value'=>$adminProjectStatus['draft'],'color'=>'#94a3b8'],
];
$prioritySegments = [
    ['key'=>'priority-high','label'=>'High','value'=>(int)$adminPriority['high_count'],'color'=>'#ef4444'],
    ['key'=>'priority-medium','label'=>'Medium','value'=>(int)$adminPriority['medium_count'],'color'=>'#f59e0b'],
    ['key'=>'priority-low','label'=>'Low','value'=>(int)$adminPriority['low_count'],'color'=>'#22c55e'],
];
$chartSeries = [
    ['key'=>'projects','label'=>'Project Created','color'=>'#f97316'],
    ['key'=>'tasks','label'=>'Task Created','color'=>'#3b82f6'],
    ['key'=>'completed','label'=>'Task Completed','color'=>'#22c55e'],
];
$chartPoints = [];
foreach ($chartSeries as $series) {
    $points = [];
    foreach (array_values($adminDays) as $index => $day) {
        $x = count($adminDays) > 1 ? 30 + ($index * (540 / (count($adminDays) - 1))) : 300;
        $y = 170 - (((int)$day[$series['key']] / $adminChartMax) * 135);
        $points[] = round($x, 2) . ',' . round($y, 2);
    }
    $chartPoints[$series['key']] = implode(' ', $points);
}
?>
<div class="admin-dense-dashboard">
 <header class="admin-dense-header admin-reveal" style="--delay:0ms">
  <div><h1>Admin Dashboard</h1><p>Selamat datang kembali, <b><?= e($user['name']) ?></b> <span>Admin</span></p></div>
  <div class="admin-header-actions"><form class="admin-search" method="get"><input type="hidden" name="page" value="dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input name="q" value="<?= e($search) ?>" placeholder="Cari project, tugas, atau pengguna..."><button type="submit">Cari</button></form><a class="admin-export" href="<?= e(app_url('actions/admin-report.php')) ?>">Export</a><a class="admin-notification" href="index.php?page=notifications" aria-label="Notifikasi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg><?php if ($unreadSidebarCount): ?><b><?= e($unreadSidebarCount) ?></b><?php endif; ?></a><a class="admin-avatar" href="index.php?page=profile"><?= e(strtoupper(substr($user['name'], 0, 2))) ?></a></div>
 </header>

 <?php if ($search !== ''): ?><section class="admin-card admin-search-results admin-reveal" style="--delay:30ms"><div class="admin-card-title"><h2>Hasil pencarian "<?= e($search) ?>"</h2><a href="index.php?page=dashboard">Tutup</a></div><div><?php foreach ($searchResults['projects'] as $result): ?><a href="actions/project-detail.php?id=<?= e($result['id']) ?>"><b>Project</b><?= e($result['title']) ?></a><?php endforeach; ?><?php foreach ($searchResults['tasks'] as $result): ?><a href="actions/task-detail.php?id=<?= e($result['id']) ?>"><b>Task</b><?= e($result['title']) ?></a><?php endforeach; ?><?php foreach ($searchResults['users'] as $result): ?><a href="index.php?page=users"><b>User</b><?= e($result['name']) ?></a><?php endforeach; ?><?php if (!$searchResults['projects'] && !$searchResults['tasks'] && !$searchResults['users']): ?><p>Tidak ada data yang cocok.</p><?php endif; ?></div></section><?php endif; ?>

 <section class="admin-kpi-grid"><?php foreach ($adminKpis as $i => [$label,$value,$trend,$icon,$tone,$url]): ?><a class="admin-card admin-kpi admin-reveal" href="<?= e($url) ?>" style="--delay:<?= e(50 + $i * 45) ?>ms"><span class="admin-kpi-icon tone-<?= e($tone) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?php if ($icon === 'folder'): ?><path d="M3 7h7l2 2h9v10H3V7Z"/><?php elseif ($icon === 'task'): ?><path d="M9 5H6v16h12V5h-3M9 3h6v4H9z"/><?php elseif ($icon === 'clock'): ?><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/><?php elseif ($icon === 'users'): ?><path d="M20 21a8 8 0 0 0-16 0m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/><?php else: ?><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 6-7"/><?php endif; ?></svg></span><span><small><?= e($label) ?></small><strong><?= $label === 'Task Selesai' ? e($value) . '%' : e($value) ?></strong><em><?= $trend >= 0 ? '+' : '-' ?><?= e(abs($trend)) ?> dari periode lalu</em></span></a><?php endforeach; ?></section>

 <section class="admin-chart-grid">
  <article class="admin-card admin-activity admin-reveal" style="--delay:300ms">
   <div class="admin-card-title"><h2>Activity Overview</h2><form method="get"><input type="hidden" name="page" value="dashboard"><?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?><select name="period" onchange="this.form.submit()"><?php foreach ($allowedPeriods as $option): ?><option value="<?= e($option) ?>" <?= $period === $option ? 'selected' : '' ?>><?= e($option) ?> Bulan Terakhir</option><?php endforeach; ?></select></form></div>
   <div class="admin-legend"><?php foreach ($chartSeries as $series): ?><button type="button" data-series="<?= e($series['key']) ?>"><i style="background:<?= e($series['color']) ?>"></i><?= e($series['label']) ?></button><?php endforeach; ?></div>
   <div class="admin-activity-chart" data-max="<?= e($adminChartMax) ?>">
    <div class="admin-chart-axis"><span><?= e($adminChartMax) ?></span><span><?= e((int)round($adminChartMax / 2)) ?></span><span>0</span></div>
    <svg viewBox="0 0 600 190" preserveAspectRatio="none" role="img" aria-label="Activity Overview chart">
     <path class="admin-chart-gridline" d="M30 35H570M30 102H570M30 170H570"/>
     <?php foreach ($chartSeries as $series): ?><polyline class="admin-chart-line" data-series="<?= e($series['key']) ?>" points="<?= e($chartPoints[$series['key']]) ?>" fill="none" stroke="<?= e($series['color']) ?>" stroke-width="3" vector-effect="non-scaling-stroke"/><?php endforeach; ?>
     <?php foreach (array_values($adminDays) as $index => $day): $x = count($adminDays) > 1 ? 30 + ($index * (540 / (count($adminDays) - 1))) : 300; ?>
      <?php foreach ($chartSeries as $series): $y = 170 - (((int)$day[$series['key']] / $adminChartMax) * 135); ?><circle class="admin-chart-point" tabindex="0" data-series="<?= e($series['key']) ?>" data-label="<?= e($day['label']) ?>" data-value="<?= e($day[$series['key']]) ?>" data-name="<?= e($series['label']) ?>" cx="<?= e(round($x, 2)) ?>" cy="<?= e(round($y, 2)) ?>" r="4" fill="white" stroke="<?= e($series['color']) ?>" stroke-width="2"/><?php endforeach; ?>
     <?php endforeach; ?>
    </svg>
    <div class="admin-chart-labels"><?php foreach ($adminDays as $day): ?><span><?= e($day['label']) ?></span><?php endforeach; ?></div>
    <div class="admin-chart-tooltip"></div>
   </div>
  </article>

  <article class="admin-card admin-donut-card admin-reveal" style="--delay:360ms"><h2>Project Progress Summary</h2><div class="admin-donut-layout"><div class="admin-interactive-donut" data-total="<?= e($totalProjects) ?>" data-label="Total Project"><svg viewBox="0 0 120 120" class="-rotate-90" role="img" aria-label="Project Progress Summary"><circle class="admin-donut-track" cx="60" cy="60" r="42"/><?php $offset=0; foreach ($projectSegments as $segment): $percent=$segment['value']/$projectTotalForDonut*100; ?><circle class="admin-donut-segment" tabindex="0" data-key="<?= e($segment['key']) ?>" data-label="<?= e($segment['label']) ?>" data-value="<?= e($segment['value']) ?>" data-percent="<?= e(round($percent)) ?>" cx="60" cy="60" r="42" pathLength="100" stroke="<?= e($segment['color']) ?>" stroke-dasharray="<?= e($percent) ?> <?= e(100-$percent) ?>" stroke-dashoffset="<?= e(-$offset) ?>"/><?php $offset += $percent; endforeach; ?></svg><span class="admin-donut-center"><b><?= e($totalProjects) ?></b><small>Total Project</small></span><div class="admin-donut-tooltip"></div></div><div class="admin-donut-list"><?php foreach ($projectSegments as $segment): $percent=$segment['value']/$projectTotalForDonut*100; ?><button type="button" data-key="<?= e($segment['key']) ?>" data-label="<?= e($segment['label']) ?>" data-value="<?= e($segment['value']) ?>" data-percent="<?= e(round($percent)) ?>"><i style="background:<?= e($segment['color']) ?>"></i><span><?= e($segment['label']) ?></span><b><?= e($segment['value']) ?> (<?= e(round($percent)) ?>%)</b></button><?php endforeach; ?></div></div></article>
  <article class="admin-card admin-pie-card admin-reveal" style="--delay:420ms"><h2>Task Priority</h2><div class="admin-donut-layout"><div class="admin-interactive-pie"><svg viewBox="0 0 120 120" class="-rotate-90" role="img" aria-label="Task Priority Pie Chart"><?php $offset=0; foreach ($prioritySegments as $segment): $percent=$segment['value']/$priorityTotal*100; ?><circle class="admin-pie-segment" tabindex="0" data-key="<?= e($segment['key']) ?>" data-label="<?= e($segment['label']) ?>" data-value="<?= e($segment['value']) ?>" data-percent="<?= e(round($percent)) ?>" cx="60" cy="60" r="21" pathLength="100" fill="none" stroke="<?= e($segment['color']) ?>" stroke-width="42" stroke-dasharray="<?= e($percent) ?> <?= e(100-$percent) ?>" stroke-dashoffset="<?= e(-$offset) ?>"/><?php $offset += $percent; endforeach; ?></svg><div class="admin-donut-tooltip"></div></div><div class="admin-donut-list"><?php foreach ($prioritySegments as $segment): $percent=$segment['value']/$priorityTotal*100; ?><button type="button" data-key="<?= e($segment['key']) ?>" data-label="<?= e($segment['label']) ?>" data-value="<?= e($segment['value']) ?>" data-percent="<?= e(round($percent)) ?>"><i style="background:<?= e($segment['color']) ?>"></i><span><?= e($segment['label']) ?></span><b><?= e($segment['value']) ?> (<?= e(round($percent)) ?>%)</b></button><?php endforeach; ?><p class="admin-pie-total"><b><?= e($totalTasks) ?></b><span>Total Task</span></p></div></div></article>
 </section>

 <section class="admin-lower-grid">
  <article class="admin-card admin-project-table admin-reveal" style="--delay:480ms"><div class="admin-card-title"><h2>Project Terbaru</h2><a href="index.php?page=projects">Lihat semua</a></div><table><thead><tr><th>#</th><th>Project</th><th>Manager</th><th>Progress</th><th>Deadline</th><th>Status</th></tr></thead><tbody><?php foreach (array_slice($projects,0,5) as $i=>$p): $pct=(int)$p['task_count']?round((int)$p['approved_count']/(int)$p['task_count']*100):0; ?><tr><td><?= e($i+1) ?></td><td><a href="actions/project-detail.php?id=<?= e($p['id']) ?>"><b><?= e($p['title']) ?></b><small><?= e($p['description']) ?></small></a></td><td><?= e($p['owner_name']) ?></td><td><b><?= e($pct) ?>%</b><i><em class="admin-progress-fill" style="--progress:<?= e($pct) ?>%"></em></i></td><td><?= e(date('d M Y',strtotime($p['deadline_at']))) ?></td><td><?= status_badge($p['status']) ?></td></tr><?php endforeach; ?></tbody></table></article>
  <article class="admin-card admin-upcoming admin-reveal" style="--delay:540ms"><div class="admin-card-title"><h2>Task Mendatang</h2><a href="index.php?page=tasks">Lihat semua</a></div><?php foreach (array_slice($deadlineRows,0,5) as $d): $days=(int)ceil((strtotime($d['deadline_at'])-time())/86400); $priority=$days<=3?'High':($days<=7?'Medium':'Low'); ?><a href="actions/task-detail.php?id=<?= e($d['id']) ?>"><span>T</span><p><b><?= e($d['title']) ?></b><small><?= e($d['project_title']) ?></small></p><time><?= e(date('d M Y',strtotime($d['deadline_at']))) ?></time><em class="<?= strtolower($priority) ?>"><?= e($priority) ?></em></a><?php endforeach; ?></article>
 </section>
 <section class="admin-card admin-quick admin-reveal" style="--delay:600ms"><h2>Quick Actions</h2><div><?php foreach ([['Buat Project Baru','index.php?page=projects&create=1','+'],['Tambah User','index.php?page=users&create=1','U'],['Assign Task','index.php?page=tasks&create=1','T'],['Generate Report',app_url('actions/admin-report.php'),'R'],['System Backup',app_url('actions/admin-backup.php'),'B']] as [$label,$url,$icon]): ?><a href="<?= e($url) ?>"><span><?= e($icon) ?></span><b><?= e($label) ?></b></a><?php endforeach; ?></div></section>
</div>
<script>
(() => {
 const root=document.querySelector('.admin-dense-dashboard'); if(!root)return;
 requestAnimationFrame(()=>root.classList.add('is-ready'));
 root.querySelectorAll('.admin-progress-fill').forEach(fill=>requestAnimationFrame(()=>fill.style.width=fill.style.getPropertyValue('--progress')));
 const chart=root.querySelector('.admin-activity-chart'), chartTip=chart?.querySelector('.admin-chart-tooltip');
 const setSeries=(key,on)=>{root.querySelectorAll(`[data-series="${key}"]`).forEach(el=>el.classList.toggle('is-muted',!on))};
 root.querySelectorAll('.admin-legend button').forEach(button=>{button.addEventListener('mouseenter',()=>root.querySelectorAll('.admin-legend button').forEach(item=>setSeries(item.dataset.series,item===button)));button.addEventListener('mouseleave',()=>root.querySelectorAll('.admin-legend button').forEach(item=>setSeries(item.dataset.series,true)))});
 root.querySelectorAll('.admin-chart-point').forEach(point=>{const show=()=>{chartTip.innerHTML=`<b>${point.dataset.name}</b><span>${point.dataset.label}: ${point.dataset.value}</span>`;chartTip.style.left=`${point.getAttribute('cx')/6}%`;chartTip.style.top=`${point.getAttribute('cy')/1.9}%`;chartTip.classList.add('is-visible')};point.addEventListener('mouseenter',show);point.addEventListener('focus',show);point.addEventListener('mouseleave',()=>chartTip.classList.remove('is-visible'));point.addEventListener('blur',()=>chartTip.classList.remove('is-visible'))});
 root.querySelectorAll('.admin-donut-card').forEach(card=>{const donut=card.querySelector('.admin-interactive-donut'),segments=[...card.querySelectorAll('.admin-donut-segment')],legends=[...card.querySelectorAll('.admin-donut-list button')],centerValue=card.querySelector('.admin-donut-center b'),centerLabel=card.querySelector('.admin-donut-center small'),tip=card.querySelector('.admin-donut-tooltip');const activate=(key,label,value,percent)=>{segments.forEach(s=>{s.classList.toggle('is-active',s.dataset.key===key);s.classList.toggle('is-muted',s.dataset.key!==key)});legends.forEach(l=>l.classList.toggle('is-active',l.dataset.key===key));centerValue.textContent=value;centerLabel.textContent=label;tip.innerHTML=`<b>${label}</b><span>${value} (${percent}%)</span>`;tip.classList.add('is-visible')};const reset=()=>{segments.forEach(s=>s.classList.remove('is-active','is-muted'));legends.forEach(l=>l.classList.remove('is-active'));centerValue.textContent=donut.dataset.total;centerLabel.textContent=donut.dataset.label;tip.classList.remove('is-visible')};[...segments,...legends].forEach(item=>{const show=()=>activate(item.dataset.key,item.dataset.label,item.dataset.value,item.dataset.percent);item.addEventListener('mouseenter',show);item.addEventListener('focus',show);item.addEventListener('mouseleave',reset);item.addEventListener('blur',reset)})});
 root.querySelectorAll('.admin-pie-card').forEach(card=>{const segments=[...card.querySelectorAll('.admin-pie-segment')],legends=[...card.querySelectorAll('.admin-donut-list button')],tip=card.querySelector('.admin-donut-tooltip');const activate=item=>{segments.forEach(s=>{s.classList.toggle('is-active',s.dataset.key===item.dataset.key);s.classList.toggle('is-muted',s.dataset.key!==item.dataset.key)});legends.forEach(l=>l.classList.toggle('is-active',l.dataset.key===item.dataset.key));tip.innerHTML=`<b>${item.dataset.label}</b><span>${item.dataset.value} (${item.dataset.percent}%)</span>`;tip.classList.add('is-visible')};const reset=()=>{segments.forEach(s=>s.classList.remove('is-active','is-muted'));legends.forEach(l=>l.classList.remove('is-active'));tip.classList.remove('is-visible')};[...segments,...legends].forEach(item=>{item.addEventListener('mouseenter',()=>activate(item));item.addEventListener('focus',()=>activate(item));item.addEventListener('mouseleave',reset);item.addEventListener('blur',reset)})});
})();
</script>
