<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'projects', 'tasks', 'deadlines', 'calendar', 'reports', 'templates', 'notifications', 'activity', 'users', 'roles', 'profile', 'settings', 'alerts', 'design-system'];

if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}
if ($page === 'users' && !can_manage_users(current_user()['role'])) {
    $page = 'dashboard';
}
if ($page === 'roles' && current_user()['role'] !== 'SUPERADMIN') {
    $page = 'dashboard';
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="flex-1 overflow-x-hidden p-4 lg:px-7 lg:py-6 iti-mobile-pad">
    <?php if (isset($_GET['loading'])): ?>
        <?php include __DIR__ . '/pages/loading-state.php'; ?>
    <?php elseif (isset($_GET['error_state'])): ?>
        <?php include __DIR__ . '/pages/error-state.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . "/pages/{$page}.php"; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
