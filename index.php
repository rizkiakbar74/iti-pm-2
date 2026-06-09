<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'projects', 'tasks', 'deadlines', 'notifications', 'activity', 'users', 'profile', 'settings'];

if (!in_array($page, $allowed, true)) {
    $page = 'dashboard';
}
if ($page === 'users' && !can_manage_users(current_user()['role'])) {
    $page = 'dashboard';
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<main class="flex-1 overflow-x-hidden p-4 lg:px-7 lg:py-6 iti-mobile-pad">
    <?php include __DIR__ . "/pages/{$page}.php"; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
