# PHASE_1_2_PERMISSION_FOUNDATION_REPORT.md

## Scope Phase 1.2

Phase ini melanjutkan PHP + MySQL starter dari Phase 1.1 dengan fokus awal pada:

1. Perbaikan tampilan grafik dashboard.
2. Project creation dengan pilihan anggota project.
3. Permission dasar project member agar user bisa melihat project yang memang ditugaskan kepadanya.

## Perubahan Utama

### 1. Dashboard Chart Visual Upgrade

File diubah:

- `pages/dashboard.php`

Perbaikan:

- Grafik Aktivitas Proyek Kampus diubah menjadi grouped vertical bar chart yang lebih jelas.
- Series tetap memakai data real dari MySQL:
  - Aktif
  - Review
  - Selesai
- Filter periode tetap tersedia:
  - 1 bulan
  - 3 bulan
  - 6 bulan
  - 12 bulan
- Bulan yang tidak memiliki data tetap muncul dengan nilai 0 agar grafik tidak tampak kosong/aneh.
- Tinggi chart otomatis mengikuti nilai terbesar pada periode terpilih.
- KPI cards tetap berbasis data role aktif.

### 2. Project Member Selection

File diubah:

- `pages/projects.php`
- `includes/functions.php`

Perbaikan:

- Saat Admin/Moderator/Superadmin membuat project, sekarang bisa memilih anggota project.
- Pembuat project otomatis masuk sebagai owner.
- User yang dipilih masuk ke tabel `project_members`.
- User yang dipilih akan bisa melihat project tersebut di menu Project.
- User yang dipilih menerima notifikasi `Project baru ditugaskan`.
- Activity log mencatat jumlah anggota project saat project dibuat.

### 3. Role Assignment Rule Dasar

Aturan yang ditambahkan:

- SUPERADMIN bisa menambahkan semua role sebagai anggota project.
- ADMIN bisa menambahkan ADMIN, MODERATOR, dan USER.
- MODERATOR bisa menambahkan MODERATOR dan USER.
- USER tetap tidak bisa membuat project.

## Yang Belum Masuk Phase Ini

Belum dikerjakan penuh:

- Edit anggota project setelah project dibuat.
- Transfer ownership project.
- Assign task ke anggota project tertentu melalui UI task creation.
- Permission matrix final lengkap seperti React lama.
- Pagination penuh di Project/Task.
- UI parity penuh dengan React version.

Itu masuk Phase lanjutan.

## Test yang Sudah Dilakukan

Command:

```bash
find . -name '*.php' -not -path './uploads/*' -print0 | xargs -0 -n1 php -l
```

Hasil:

- Semua file PHP lolos syntax check.
- Tidak ditemukan `reset_password.php` di project.
- Tidak ditemukan debug login `Hash length` di `login.php`.

## Test Manual yang Perlu Dilakukan User

1. Login sebagai `admin@iti.ac.id`.
2. Buka menu Project.
3. Buat project baru.
4. Pilih `Staf PMB` sebagai anggota project.
5. Logout.
6. Login sebagai `user@iti.ac.id`.
7. Cek apakah project baru tampil di menu Project.
8. Cek Dashboard apakah grafik tetap tampil.
9. Cek filter dashboard 1/3/6/12 bulan.

## Status

Phase 1.2 awal selesai untuk project member foundation dan dashboard chart visual.
