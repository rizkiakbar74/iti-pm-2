<?php
$user = current_user();
$allowedPeriods = [1, 3, 6, 12];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $dashboardPeriod = (int)($_POST['dashboard_period'] ?? 6);
    $_SESSION['dashboard_period'] = in_array($dashboardPeriod, $allowedPeriods, true) ? $dashboardPeriod : 6;
    $saved = true;
}
?>

<div class="mb-6">
    <p class="text-sm font-black uppercase tracking-wide text-orange-600">Settings</p>
    <h2 class="text-3xl font-black">Pengaturan Tampilan</h2>
    <p class="text-slate-500">Atur preferensi dashboard untuk sesi akun Anda.</p>
</div>

<?php if ($saved): ?>
    <div class="mb-5 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-green-700">Pengaturan dashboard berhasil disimpan.</div>
<?php endif; ?>

<form method="post" class="max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <?= csrf_field() ?>
    <label class="block">
        <span class="text-sm font-black text-slate-800">Periode default grafik dashboard</span>
        <select class="mt-2 w-full rounded-xl border border-slate-200 px-4 py-3" name="dashboard_period">
            <?php foreach ($allowedPeriods as $period): ?>
                <option value="<?= e($period) ?>" <?= (int)($_SESSION['dashboard_period'] ?? 6) === $period ? 'selected' : '' ?>><?= e($period) ?> bulan terakhir</option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="mt-5 rounded-xl bg-orange-600 px-5 py-3 font-black text-white hover:bg-orange-700">Simpan Pengaturan</button>
</form>
