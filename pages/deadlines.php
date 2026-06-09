<?php
$user = current_user();

$where = [];
$params = [];
if ($user['role'] !== 'SUPERADMIN') {
    $where[] = "(pm.user_id = ? OR ta.user_id = ?)";
    $params[] = $user['id'];
    $params[] = $user['id'];
}
$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

$stmt = $pdo->prepare("
    SELECT DISTINCT t.id, t.title, t.deadline_at, t.status, p.title AS project_title
    FROM tasks t
    JOIN projects p ON p.id = t.project_id
    LEFT JOIN project_members pm ON pm.project_id = p.id
    LEFT JOIN task_assignees ta ON ta.task_id = t.id
    {$whereSql}
    ORDER BY t.deadline_at ASC
    LIMIT 100
");
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div>
        <p class="text-sm font-black uppercase tracking-wide text-orange-600">Deadline</p>
        <h2 class="text-3xl font-black">Pusat Deadline</h2>
    </div>
    <?php if (in_array($user['role'], ['SUPERADMIN','ADMIN','MODERATOR'], true)): ?>
    <button type="button" id="open-deadline-reminder" class="rounded-xl bg-orange-600 px-4 py-3 text-sm font-black text-white">Buat Reminder Deadline</button>
    <?php endif; ?>
</div>

<?php if (in_array($user['role'], ['SUPERADMIN','ADMIN','MODERATOR'], true)): ?>
<div id="deadline-reminder-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4">
    <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-black uppercase tracking-wide text-orange-600">Reminder Deadline</p>
                <h3 class="text-2xl font-black text-slate-900">Buat Reminder Deadline</h3>
                <p class="mt-2 text-sm text-slate-500">Sistem akan membuat notifikasi untuk task yang overdue, deadline hari ini, atau deadline dalam 3 hari.</p>
            </div>
            <button type="button" id="close-deadline-reminder" class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-black text-slate-700">Tutup</button>
        </div>

        <form method="post" action="actions/deadline-reminder.php" class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
            <?= csrf_field() ?>
            <button type="button" id="cancel-deadline-reminder" class="rounded-xl bg-slate-100 px-4 py-3 text-sm font-black text-slate-700">Batal</button>
            <button class="rounded-xl bg-orange-600 px-4 py-3 text-sm font-black text-white">Kirim Reminder</button>
        </form>
    </div>
</div>

<script>
    const reminderModal = document.getElementById('deadline-reminder-modal');
    const openReminder = document.getElementById('open-deadline-reminder');
    const closeReminder = document.getElementById('close-deadline-reminder');
    const cancelReminder = document.getElementById('cancel-deadline-reminder');

    function setReminderModal(open) {
        reminderModal.classList.toggle('hidden', !open);
        reminderModal.classList.toggle('flex', open);
    }

    openReminder?.addEventListener('click', () => setReminderModal(true));
    closeReminder?.addEventListener('click', () => setReminderModal(false));
    cancelReminder?.addEventListener('click', () => setReminderModal(false));
    reminderModal?.addEventListener('click', (event) => {
        if (event.target === reminderModal) {
            setReminderModal(false);
        }
    });
</script>
<?php endif; ?>

<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="space-y-3">
        <?php foreach ($rows as $row): ?>
            <a href="actions/task-detail.php?id=<?= e($row['id']) ?>" class="flex items-center justify-between rounded-2xl border border-slate-100 p-4 hover:bg-slate-50">
                <div>
                    <strong><?= e($row['title']) ?></strong>
                    <p class="text-sm text-slate-500"><?= e($row['project_title']) ?></p>
                </div>
                <div class="text-right">
                    <p class="<?= strtotime($row['deadline_at']) < time() && $row['status'] !== 'approved' ? 'font-black text-red-600' : 'font-bold text-slate-700' ?>"><?= e(date('d M Y H:i', strtotime($row['deadline_at']))) ?></p>
                    <?= status_badge($row['status']) ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
