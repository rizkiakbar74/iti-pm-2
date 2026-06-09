<main class="project-detail-page min-h-screen flex-1 overflow-x-hidden p-4 lg:p-6">
    <header class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-[28px] font-black tracking-tight">Project Detail</h1>
            <p class="mt-1 text-sm text-slate-500"><a href="<?= e(app_url('index.php?page=projects')) ?>">Projects</a><span class="mx-2">/</span><strong class="text-orange-600"><?= e($project['title']) ?></strong></p>
        </div>
        <div class="flex items-center gap-3">
            <a class="dashboard-interactive-icon grid h-11 w-11 place-items-center rounded-xl border border-slate-200 bg-white" href="<?= e(app_url('index.php?page=notifications')) ?>"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/></svg></a>
            <span class="grid h-10 w-10 place-items-center rounded-full bg-orange-100 font-black text-orange-600"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
            <span class="hidden sm:block"><b class="block text-xs"><?= e($user['name']) ?></b><small class="text-slate-500"><?= e(role_label($user['role'])) ?></small></span>
        </div>
    </header>

    <?php if ($projectError || $memberError): ?><div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700"><?= e($projectError ?: $memberError) ?></div><?php endif; ?>
    <?php if ($projectSuccess || $memberSuccess): ?><div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-700"><?= e($projectSuccess ?: $memberSuccess) ?></div><?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-6 xl:grid-cols-[1.4fr_.8fr]">
            <div class="flex gap-5">
                <span class="grid h-20 w-20 shrink-0 place-items-center rounded-2xl bg-blue-100 text-blue-600"><svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 7h7l2 2h9v10H3V7Zm0 0V5h7l2 2"/></svg></span>
                <div class="min-w-0">
                    <h2 class="text-2xl font-black"><?= e($project['title']) ?></h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500"><?= e($project['description'] ?: 'Tidak ada deskripsi project.') ?></p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-4">
                        <div><span class="block text-xs text-slate-500">Status</span><div class="mt-2"><?= status_badge($project['status']) ?></div></div>
                        <div><span class="block text-xs text-slate-500">Deadline</span><b class="mt-2 block text-sm"><?= e(date('d M Y', strtotime($project['deadline_at']))) ?></b></div>
                        <div><span class="block text-xs text-slate-500">Manager</span><b class="mt-2 block text-sm"><?= e($project['owner_name']) ?></b></div>
                        <div><span class="block text-xs text-slate-500">Tim</span><div class="mt-2 flex -space-x-2"><?php foreach (array_slice($members, 0, 5) as $member): ?><span class="grid h-8 w-8 place-items-center rounded-full border-2 border-white bg-orange-100 text-[10px] font-black text-orange-600" title="<?= e($member['name']) ?>"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></span><?php endforeach; ?><?php if (count($members) > 5): ?><span class="grid h-8 w-8 place-items-center rounded-full border-2 border-white bg-slate-100 text-[10px] font-black">+<?= e(count($members)-5) ?></span><?php endif; ?></div></div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col justify-between">
                <div><div class="flex justify-between text-sm"><span>Progress Project</span><b><?= e($progress) ?>%</b></div><div class="mt-3 h-3 rounded-full bg-slate-100"><i class="project-detail-progress block h-3 rounded-full bg-orange-500" style="width:<?= e($progress) ?>%"></i></div></div>
                <div class="mt-6 flex flex-wrap justify-end gap-3"><?php if ($canEditProject): ?><button class="open-edit-project rounded-xl bg-orange-600 px-5 py-3 font-black text-white" type="button">Edit Project</button><?php endif; ?><a class="rounded-xl border border-slate-200 px-5 py-3 font-black" href="<?= e(app_url('index.php?page=tasks&project_id=' . $projectId)) ?>">Tambah / Kelola Task</a></div>
            </div>
        </div>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-lg font-black">Progress & Timeline</h3>
            <div class="mt-5 grid gap-5 md:grid-cols-[190px_1fr]">
                <div class="space-y-4"><?php foreach (array_slice($timelineTasks, 0, 6) as $task): ?><a class="timeline-item flex gap-3" href="<?= e(app_url('actions/task-detail.php?id=' . $task['id'])) ?>"><span class="mt-1 h-3 w-3 shrink-0 rounded-full border-2 <?= $task['status']==='approved'?'border-green-500 bg-green-100':'border-orange-500 bg-white' ?>"></span><span><b class="block text-xs"><?= e($task['title']) ?></b><small class="text-slate-400"><?= e(date('d M Y', strtotime($task['deadline_at']))) ?></small></span></a><?php endforeach; ?><?php if (!$timelineTasks): ?><p class="text-sm text-slate-500">Belum ada timeline task.</p><?php endif; ?></div>
                <div class="grid place-items-center rounded-2xl bg-slate-50 p-6 text-center"><div><span class="grid h-36 w-36 place-items-center rounded-full" style="background:conic-gradient(#f97316 <?= e($progress) ?>%,#e2e8f0 0)"><span class="grid h-28 w-28 place-items-center rounded-full bg-white"><strong class="text-3xl"><?= e($progress) ?>%</strong></span></span><p class="mt-4 text-sm font-bold text-slate-500"><?= e($doneTasks) ?> dari <?= e($totalTasks) ?> task selesai</p></div></div>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between"><h3 class="text-lg font-black">Project Tasks</h3><a class="text-xs font-bold text-orange-600" href="<?= e(app_url('index.php?page=tasks&project_id=' . $projectId)) ?>">Lihat semua tasks</a></div>
            <div class="mt-4 divide-y divide-slate-100"><?php foreach (array_slice($tasks, 0, 6) as $task): ?><a class="project-task-row grid gap-3 py-3 text-xs md:grid-cols-[1.4fr_1fr_auto_auto]" href="<?= e(app_url('actions/task-detail.php?id=' . $task['id'])) ?>"><span><b class="block"><?= e($task['title']) ?></b><small class="text-slate-400"><?= e($task['assignee_names'] ?: '-') ?></small></span><span><?= status_badge($task['status']) ?></span><span class="font-black"><?= $task['status']==='approved'?'100%':'0%' ?></span><span class="whitespace-nowrap"><?= e(date('d M Y', strtotime($task['deadline_at']))) ?></span></a><?php endforeach; ?><?php if (!$tasks): ?><p class="py-8 text-center text-slate-500">Belum ada task.</p><?php endif; ?></div>
        </div>
    </section>

    <section class="mt-5 grid gap-5 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-black">Team Members</h3>
                <?php if ($canManageMembers): ?><button class="open-team-modal rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold" type="button">Kelola Tim</button><?php endif; ?>
            </div>
            <div class="mt-4">
                <div class="grid grid-cols-[minmax(0,1.25fr)_minmax(0,.9fr)_auto] gap-3 border-b border-slate-100 pb-2 text-[10px] font-bold text-slate-500">
                    <span>Anggota</span>
                    <span>Peran</span>
                    <span>Bergabung</span>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php foreach (array_slice($members, 0, 6) as $member): ?>
                        <div class="team-member-row grid grid-cols-[minmax(0,1.25fr)_minmax(0,.9fr)_auto] items-center gap-3 py-2.5">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-orange-100 text-[10px] font-black text-orange-600"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></span>
                                <b class="truncate text-[11px]"><?= e($member['name']) ?></b>
                            </span>
                            <small class="truncate text-[10px] font-medium text-slate-600"><?= e(role_label($member['role_in_project'])) ?></small>
                            <small class="whitespace-nowrap text-[10px] text-slate-500"><?= !empty($member['created_at']) ? e(date('d M Y', strtotime($member['created_at']))) : '-' ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$members): ?><p class="py-8 text-center text-xs text-slate-500">Belum ada anggota project.</p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-lg font-black">Aktivitas Terbaru</h3><div class="mt-4 divide-y divide-slate-100"><?php foreach ($projectActivities as $activity): ?><a class="activity-row flex gap-3 py-3" href="<?= e(app_url(get_activity_target_url($activity))) ?>"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-green-100 text-green-600">✓</span><span class="min-w-0 flex-1"><b class="block truncate text-xs"><?= e($activity['action']) ?></b><small class="block truncate text-slate-400"><?= e($activity['detail']) ?></small></span><small class="whitespace-nowrap text-slate-400"><?= e(date('d M H:i',strtotime($activity['created_at']))) ?></small></a><?php endforeach; ?><?php if (!$projectActivities): ?><p class="py-8 text-center text-slate-500">Belum ada aktivitas.</p><?php endif; ?></div></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-lg font-black">Statistik Project</h3><div class="mt-4 grid gap-3 sm:grid-cols-2"><div class="rounded-xl border border-slate-100 p-4"><span class="text-xs text-slate-500">Total Tasks</span><strong class="block text-2xl"><?= e($totalTasks) ?></strong></div><div class="rounded-xl border border-slate-100 p-4"><span class="text-xs text-slate-500">Selesai</span><strong class="block text-2xl text-green-600"><?= e($doneTasks) ?></strong></div><div class="rounded-xl border border-slate-100 p-4"><span class="text-xs text-slate-500">Berjalan</span><strong class="block text-2xl text-orange-600"><?= e($openTasks + $reviewTasks) ?></strong></div><div class="rounded-xl border border-slate-100 p-4"><span class="text-xs text-slate-500">Deadline</span><strong class="block text-lg"><?= e(date('d M Y',strtotime($project['deadline_at']))) ?></strong><small class="<?= $daysRemaining < 0 ? 'text-red-600':'text-orange-600' ?>"><?= $daysRemaining < 0 ? e(abs($daysRemaining)).' hari terlambat' : e($daysRemaining).' hari lagi' ?></small></div></div></div>
    </section>
</main>

<?php if ($canEditProject): ?><div class="edit-project-modal fixed inset-0 z-50 <?= $projectError ? 'flex':'hidden' ?> items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"><form method="post" class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl"><?= csrf_field() ?><div class="flex justify-between"><h3 class="text-2xl font-black">Edit Project</h3><button class="close-edit-project text-2xl" type="button">×</button></div><div class="mt-5 grid gap-4 md:grid-cols-2"><input class="rounded-xl border border-slate-200 px-4 py-3" name="title" required value="<?= e($project['title']) ?>"><input class="rounded-xl border border-slate-200 px-4 py-3" type="datetime-local" name="deadline_at" required value="<?= e(date('Y-m-d\TH:i',strtotime($project['deadline_at']))) ?>"></div><textarea class="mt-4 w-full rounded-xl border border-slate-200 p-4" name="description" rows="4"><?= e($project['description']) ?></textarea><select class="mt-4 w-full rounded-xl border border-slate-200 px-4 py-3" name="status"><?php foreach (['draft'=>'Draft','active'=>'Aktif','review'=>'Dalam Review','completed'=>'Selesai','archived'=>'Archived'] as $key=>$label): if($key==='archived'&&$user['role']!=='SUPERADMIN')continue; ?><option value="<?= e($key) ?>" <?= $project['status']===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><div class="mt-5 flex flex-wrap items-center justify-between gap-3"><button class="rounded-xl bg-red-50 px-5 py-3 font-black text-red-700" name="action" value="archive_project" formnovalidate onclick="return confirm('Arsipkan project ini?')">Arsipkan Project</button><div class="flex gap-3"><button class="close-edit-project rounded-xl border border-slate-200 px-5 py-3" type="button">Batal</button><button class="rounded-xl bg-orange-600 px-5 py-3 font-black text-white" name="action" value="update_project">Simpan Project</button></div></div></form></div><?php endif; ?>

<?php if ($canManageMembers): ?><div class="team-modal fixed inset-0 z-50 <?= $memberError ? 'flex':'hidden' ?> items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"><div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-6 shadow-2xl"><div class="flex justify-between"><h3 class="text-2xl font-black">Kelola Tim</h3><button class="close-team-modal text-2xl" type="button">×</button></div><div class="mt-5 space-y-2"><?php foreach ($members as $member): ?><div class="flex items-center gap-3 rounded-xl border border-slate-100 p-3"><span class="grid h-9 w-9 place-items-center rounded-full bg-orange-100 font-black text-orange-600"><?= e(strtoupper(substr($member['name'],0,1))) ?></span><span class="flex-1"><b class="block"><?= e($member['name']) ?></b><small class="text-slate-500"><?= e($member['role_in_project']) ?></small></span><?php if($member['role_in_project']!=='owner'): ?><form method="post" onsubmit="return confirm('Hapus anggota ini?')"><?= csrf_field() ?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="member_id" value="<?= e($member['id']) ?>"><button class="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-700">Hapus</button></form><?php endif; ?></div><?php endforeach; ?></div><?php if($assignable): ?><form method="post" class="mt-5 rounded-2xl bg-slate-50 p-4"><?= csrf_field() ?><input type="hidden" name="action" value="add_member"><select name="member_id" class="w-full rounded-xl border border-slate-200 px-3 py-3"><?php foreach($assignable as $candidate): ?><option value="<?= e($candidate['id']) ?>"><?= e($candidate['name']) ?> - <?= e($candidate['role']) ?></option><?php endforeach; ?></select><select name="role_in_project" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-3"><option value="member">Member</option><option value="manager">Manager</option></select><button class="mt-3 w-full rounded-xl bg-orange-600 px-4 py-3 font-black text-white">Tambah Anggota</button></form><?php endif; ?></div></div><?php endif; ?>

<script>
(() => {
    const bind = (open, modal, close) => {
        const panel = document.querySelector(modal);
        document.querySelectorAll(open).forEach(button => button.addEventListener('click', () => { panel?.classList.remove('hidden'); panel?.classList.add('flex'); }));
        document.querySelectorAll(close).forEach(button => button.addEventListener('click', () => { panel?.classList.add('hidden'); panel?.classList.remove('flex'); }));
        panel?.addEventListener('click', event => { if (event.target === panel) { panel.classList.add('hidden'); panel.classList.remove('flex'); } });
    };
    bind('.open-edit-project','.edit-project-modal','.close-edit-project');
    bind('.open-team-modal','.team-modal','.close-team-modal');
    requestAnimationFrame(() => document.querySelectorAll('.project-detail-progress').forEach(bar => bar.classList.add('is-ready')));
})();
</script>
