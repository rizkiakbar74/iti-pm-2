<div class="ui3-page ui3-design-page">
 <header><p>UI Set 3 / Component Reference</p><h2>Design System Preview</h2><span>Sistem desain yang digunakan pada aplikasi.</span></header>
 <section><h3>Color System</h3><div class="ui3-swatches"><?php foreach(['#eff6ff','#bfdbfe','#60a5fa','#2563eb','#0b1b39','#06152d','#22c55e','#f59e0b','#ef4444','#64748b'] as $color): ?><i style="background:<?= e($color) ?>"></i><?php endforeach; ?></div></section>
 <section><h3>Typography</h3><div class="ui3-type"><h1>Judul Halaman</h1><h2>Judul Bagian</h2><p>Teks paragraf utama untuk konten aplikasi.</p><small>Teks kecil untuk keterangan tambahan.</small></div></section>
 <section><h3>Buttons & Inputs</h3><div class="ui3-components"><button class="primary">Primary Button</button><button>Secondary Button</button><button class="danger">Danger Button</button><input placeholder="Masukkan teks..."><input class="error" placeholder="Field error"></div></section>
 <section><h3>Badge, Progress, Alerts</h3><div class="ui3-components"><span class="badge blue">In Progress</span><span class="badge green">Completed</span><span class="badge orange">On Hold</span><progress value="65" max="100"></progress><a href="<?= e(app_url('index.php?page=alerts')) ?>">Lihat Alert System</a></div></section>
 <section><h3>Elevation / Shadows</h3><div class="ui3-shadows"><i></i><i></i><i></i><i></i></div></section>
</div>
