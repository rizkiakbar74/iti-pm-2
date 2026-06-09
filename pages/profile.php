<?php $user = current_user(); ?>
<div class="mb-6">
    <p class="text-sm font-black uppercase tracking-wide text-orange-600">Profil</p>
    <h2 class="text-3xl font-black">Profil Saya</h2>
</div>

<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center gap-5">
        <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-slate-900 text-3xl font-black text-white">
            <?= e(substr($user['name'], 0, 1)) ?>
        </div>
        <div>
            <h3 class="text-2xl font-black"><?= e($user['name']) ?></h3>
            <p class="text-slate-500"><?= e($user['email']) ?></p>
            <p class="mt-2"><?= status_badge($user['role']) ?></p>
        </div>
    </div>

    <dl class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl bg-slate-50 p-4">
            <dt class="text-sm font-bold text-slate-500">Unit</dt>
            <dd class="font-black"><?= e($user['unit']) ?></dd>
        </div>
        <div class="rounded-2xl bg-slate-50 p-4">
            <dt class="text-sm font-bold text-slate-500">Mode</dt>
            <dd class="font-black">PHP Session</dd>
        </div>
        <div class="rounded-2xl bg-slate-50 p-4">
            <dt class="text-sm font-bold text-slate-500">Database</dt>
            <dd class="font-black">MySQL/phpMyAdmin</dd>
        </div>
    </dl>
</section>
