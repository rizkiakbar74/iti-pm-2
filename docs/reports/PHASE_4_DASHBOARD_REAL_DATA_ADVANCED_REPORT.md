# PHASE_4_DASHBOARD_REAL_DATA_ADVANCED_REPORT.md

## Fokus Phase 4

Phase 4 memperkuat dashboard berbasis data real MySQL, sesuai role aktif dan project/task yang visible.

## Perubahan Utama

### 1. KPI Cards Lebih Informatif

KPI utama:
- Total Project
- Total Task
- Task Berjalan
- Dalam Review
- Lewat Deadline

Tambahan KPI operasional:
- Completion Rate
- Perlu Revisi
- Deadline Hari Ini
- Deadline 3 Hari ke Depan

### 2. Sparkline KPI Real Data

KPI Total Project dan Total Task memakai sparkline mini dari data real per bulan.

Periode mengikuti filter:
- 1 bulan
- 3 bulan
- 6 bulan
- 12 bulan

### 3. Progress KPI Real Data

Task Berjalan, Dalam Review, dan Lewat Deadline memakai progress bar berdasarkan persentase dari total task visible.

### 4. Grafik Aktivitas Proyek Kampus

Grafik utama tetap:
- Aktif
- Review
- Selesai

Grafik dihitung dari task real berdasarkan role aktif dan project visible.

Periode:
- 1 bulan
- 3 bulan
- 6 bulan
- 12 bulan

Bulan tanpa data tetap tampil agar chart tidak lompat/kosong.

### 5. Deadline & Tindak Lanjut

Dashboard sekarang punya blok deadline:
- task lewat deadline ditandai merah
- task mendekati deadline tampil berdasarkan urutan prioritas
- klik langsung ke detail task

### 6. Project Terdekat

Project terdekat menampilkan:
- nama project
- owner
- deadline
- progress bar real berdasarkan approved task

### 7. Aktivitas Terbaru

Dashboard menampilkan activity log terbaru sesuai akses role aktif.

### 8. Seed Data Dashboard

`database/schema.sql` ditambah data demo lintas bulan agar dashboard tidak kosong setelah import ulang.

## File yang Diubah

- `pages/dashboard.php`
- `database/schema.sql`

## Koreksi Sebelum ZIP

- `php -l` semua file PHP: lolos.
- `reset_password.php`: tidak ikut.
- Debug `Hash length`: tidak ada.
- `uploads/.htaccess`: ada.
- `database/schema.sql`: ada.
- `role_rank_visible`: tidak ada.
- Filter periode dashboard: ada.
- KPI deadline hari ini: ada.
- Seed dashboard phase 4: ada.

## Test Manual

1. Import ulang `database/schema.sql`.
2. Login sebagai `superadmin@iti.ac.id`.
3. Buka dashboard.
4. Klik filter 1/3/6/12 bulan.
5. Cek KPI Total Project dan Total Task.
6. Cek grafik Aktivitas Proyek Kampus.
7. Cek Deadline & Tindak Lanjut.
8. Login sebagai user dan pastikan dashboard hanya menampilkan data visible untuk user tersebut.
