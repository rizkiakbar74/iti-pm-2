<?php
$active = $_GET['page'] ?? 'dashboard';
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($currentScript === 'project-detail.php') $active = 'projects';
if ($currentScript === 'task-detail.php') $active = 'tasks';
$unreadSidebarCount = isset($pdo, $user['id']) ? get_unread_notification_count($pdo, (int)$user['id']) : 0;
$menu = [
    'dashboard' => ['Dashboard', '<path d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z"/>'],
    'projects' => ['Projects', '<path d="M3 7h7l2 2h9v10H3V7Zm0 0V5h7l2 2"/>'],
    'tasks' => ['Tasks', '<path d="M9 5h10v16H5V5h2m2-2h6v4H9V3Zm0 11 2 2 4-5"/>'],
    'deadlines' => ['Deadlines', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18m-13 4h.01m4 0h.01m4 0h.01m-8 4h.01m4 0h.01"/>'],
    'calendar' => ['Calendar', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18m5 4h14m-5 4h14"/>'],
    'reports' => ['Report & Analytics', '<path d="M4 19V9m6 10V5m6 14v-7m4 7H2"/>'],
    'templates' => ['Template Management', '<path d="M5 3h14v18H5zM8 7h8m-8 4h8m-8 4h5"/>'],
    'notifications' => ['Notifications', '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Zm-8 13h4"/>'],
    'activity' => ['Activity Log', '<path d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4-1v5l3 2"/>'],
    'users' => ['Users', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87m-2-12a4 4 0 0 1 0 7.75"/>'],
    'roles' => ['Role & Permission', '<circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="9"/><path d="M12 3v3m0 12v3M3 12h3m12 0h3"/>'],
    'profile' => ['Profile', '<path d="M20 21a8 8 0 0 0-16 0m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/>'],
    'settings' => ['Settings', '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21h-4v-.09A1.7 1.7 0 0 0 8.6 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.2 15a1.7 1.7 0 0 0-.6-1A1.7 1.7 0 0 0 2.5 13.6H2v-4h.5A1.7 1.7 0 0 0 4.2 8.6a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 8.6 4.2a1.7 1.7 0 0 0 1-.6A1.7 1.7 0 0 0 10 2.5V2h4v.5A1.7 1.7 0 0 0 15 4.2a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 8.6a1.7 1.7 0 0 0 .6 1 1.7 1.7 0 0 0 1.1.4h.9v4h-.9a1.7 1.7 0 0 0-1.7 1Z"/>'],
];
?>
<div class="mobile-drawer-backdrop"></div>
<aside class="mobile-navigation-drawer">
    <button class="mobile-drawer-close" type="button" aria-label="Tutup menu">×</button>
    <div class="mobile-drawer-brand">
        <img src="<?= e(app_url('assets/images/iti-logo.jpg')) ?>" alt="Logo ITI">
        <strong>ITI Project<br>Manager</strong>
    </div>
    <div class="mobile-drawer-profile">
        <span><?= e(strtoupper(substr($user['name'] ?? '-', 0, 1))) ?></span>
        <div><b><?= e($user['name'] ?? '-') ?></b><small><?= e(role_label($user['role'] ?? '-')) ?></small></div>
    </div>
    <p class="mobile-drawer-label">Menu Utama</p>
    <nav class="mobile-drawer-nav">
        <?php foreach (['dashboard','projects','tasks','calendar','reports','templates','notifications'] as $key): [$label,$icon]=$menu[$key]; $mobileLabel=['projects'=>'Project','tasks'=>'Task','calendar'=>'Kalender','reports'=>'Report','templates'=>'Template','notifications'=>'Notifikasi'][$key]??$label; ?>
        <a class="<?= $active===$key?'is-active':'' ?>" href="<?= e(app_url('index.php?page='.$key)) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $icon ?></svg><span><?= e($mobileLabel) ?></span><?php if($key==='notifications'&&$unreadSidebarCount): ?><b><?= e($unreadSidebarCount) ?></b><?php elseif($key!=='dashboard'): ?><i>›</i><?php endif; ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="mobile-drawer-divider"></div>
    <p class="mobile-drawer-label">Akun</p>
    <nav class="mobile-drawer-nav">
        <a href="<?= e(app_url('index.php?page=profile')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $menu['profile'][1] ?></svg><span>Profil Saya</span></a>
        <a href="<?= e(app_url('index.php?page=settings')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $menu['settings'][1] ?></svg><span>Pengaturan</span></a>
        <a href="<?= e(app_url('index.php?page=activity')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $menu['activity'][1] ?></svg><span>Activity Log</span></a>
        <a href="<?= e(app_url('logout.php')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 17l5-5-5-5m5 5H3m12-9h5v18h-5"/></svg><span>Keluar</span></a>
    </nav>
    <a class="mobile-drawer-info" href="<?= e(app_url('index.php?page=projects')) ?>"><span>🎓</span><div><b>Sistem Informasi Akademik ITI</b><small>Kelola tugas dan proyek akademik dengan mudah.</small></div><i>›</i></a>
    <footer><p>ITI Project Manager v1.0.0</p><p>© <?= e(date('Y')) ?> ITI</p></footer>
</aside>
<header class="global-mobile-header">
    <button class="mobile-menu-button" type="button" aria-label="Buka menu"><span></span><span></span><span></span></button>
    <a href="<?= e(app_url('index.php?page=dashboard')) ?>"><img src="<?= e(app_url('assets/images/iti-logo.jpg')) ?>" alt="Logo ITI"><span><b>ITI Project Manager</b><small>Institut Teknologi Indonesia</small></span></a>
    <a class="global-mobile-bell" href="<?= e(app_url('index.php?page=notifications')) ?>">♧<?php if($unreadSidebarCount): ?><b><?= e($unreadSidebarCount) ?></b><?php endif; ?></a>
</header>
<nav class="global-mobile-bottom">
 <?php foreach(['dashboard'=>'Dashboard','projects'=>'Project','tasks'=>'Task','calendar'=>'Kalender','profile'=>'Profil'] as $key=>$label): ?><a class="<?= $active===$key?'active':'' ?>" href="<?= e(app_url('index.php?page='.$key)) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><?= $menu[$key][1] ?></svg><span><?= e($label) ?></span></a><?php endforeach; ?>
</nav>
<aside class="iti-sidebar dashboard-sidebar text-white lg:sticky lg:top-0 lg:h-screen lg:w-[270px] lg:min-h-screen p-4 lg:px-4 lg:py-5">
    <div class="flex items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            <img class="iti-side-logo h-14 w-14 shrink-0 object-cover" src="<?= e(app_url('assets/images/iti-logo.jpg')) ?>" alt="Logo ITI">
            <div class="min-w-0">
                <h1 class="truncate text-[16px] font-black">ITI Project Manager</h1>
                <p class="truncate text-[11px] text-slate-300">Institut Teknologi Indonesia</p>
            </div>
        </div>
        <div class="lg:hidden rounded-xl bg-white/10 px-3 py-2 text-right text-xs">
            <p class="font-black"><?= e($user['name'] ?? '-') ?></p>
            <p class="text-slate-300"><?= e(role_label($user['role'] ?? '-')) ?></p>
        </div>
    </div>

    <nav class="iti-scrollbar mt-7 flex gap-2 overflow-x-auto pb-2 lg:block lg:space-y-1.5 lg:overflow-visible lg:pb-0">
        <?php foreach ($menu as $key => [$label, $icon]): ?>
            <?php if ($key === 'users' && !can_manage_users($user['role'])) continue; ?>
            <?php if ($key === 'roles' && $user['role'] !== 'SUPERADMIN') continue; ?>
            <?php $is = $active === $key; ?>
            <a class="flex shrink-0 items-center gap-3 rounded-lg px-3.5 py-2.5 text-[13px] font-bold transition <?= $is ? 'bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-lg shadow-orange-950/20' : 'text-slate-200 hover:bg-white/10' ?>"
               href="<?= e(app_url('index.php?page=' . $key)) ?>">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg>
                <span><?= e($label) ?></span>
                <?php if ($key === 'notifications' && $unreadSidebarCount > 0): ?>
                    <span class="ml-auto rounded-full bg-orange-500 px-2 py-0.5 text-[10px] font-black text-white"><?= e($unreadSidebarCount) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>

        <a class="flex shrink-0 items-center gap-3 rounded-lg px-3.5 py-2.5 text-[13px] font-bold text-slate-200 transition hover:bg-white/10" href="<?= e(app_url('logout.php')) ?>">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5m5 5H3m12-9h5v18h-5"/></svg>
            <span>Logout</span>
        </a>
    </nav>
</aside>
