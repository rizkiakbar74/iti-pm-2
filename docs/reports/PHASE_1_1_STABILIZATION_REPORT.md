# PHASE_1_1_STABILIZATION_REPORT.md

## Status
Phase 1.1 — Stabilisasi PHP + MySQL starter sudah dikerjakan.

## Fokus Perubahan
Perubahan difokuskan pada keamanan dasar, kestabilan login/session, upload proof, activity log, dashboard periode, dan pembersihan file debug.

## Perubahan

1. Menghapus file `reset_password.php`
   - File reset darurat tidak boleh ikut di project aktif.
   - Password demo tetap bisa dibuat ulang lewat import `database/schema.sql`.

2. Membersihkan debug login
   - Pesan `Hash length` dihapus dari `login.php`.
   - Error login kembali menjadi pesan aman: `Email atau password salah.`

3. Memperkuat login session
   - Menambahkan `session_regenerate_id(true)` setelah login berhasil.

4. Menambahkan CSRF protection dasar
   - Fungsi baru di `includes/functions.php`:
     - `csrf_token()`
     - `csrf_field()`
     - `verify_csrf()`
   - Diterapkan pada form login, create project, create task, submit proof, dan review task.

5. Memperbaiki upload proof
   - Menambahkan validasi ukuran maksimal 10 MB.
   - Membatasi ekstensi file aman: pdf, doc/docx, xls/xlsx, ppt/pptx, png/jpg/jpeg/webp/gif, txt, zip.
   - Nama file upload dibuat aman dan unik.

6. Proteksi folder upload
   - Menambahkan `uploads/.htaccess` untuk menolak eksekusi file PHP di folder upload.

7. Memperbaiki Activity Log
   - Menghapus query yang berpotensi error karena function MySQL tidak tersedia.
   - Filter hierarki role dilakukan di PHP:
     - SUPERADMIN melihat semua.
     - ADMIN tidak melihat SUPERADMIN.
     - MODERATOR tidak melihat ADMIN/SUPERADMIN.
     - USER hanya melihat data relevan secara terbatas.

8. Memperbaiki task submit/review
   - Submit proof hanya untuk user yang menjadi assignee task.
   - Task yang sudah approved tidak bisa disubmit ulang.
   - Review hanya memproses submission terbaru yang masih `submitted`.
   - Reject tetap wajib mencantumkan alasan.

9. Memperbarui dashboard periode
   - Grafik aktivitas dapat difilter:
     - 1 bulan
     - 3 bulan
     - 6 bulan
     - 12 bulan
   - Data tetap dihitung dari MySQL sesuai role aktif.

10. Menyamakan file database default
   - `database/schema.sql` diganti dengan schema fresh yang password demo-nya benar.
   - Akun demo tetap:
     - superadmin@iti.ac.id / password
     - admin@iti.ac.id / password
     - moderator@iti.ac.id / password
     - user@iti.ac.id / password

## File Utama yang Diubah

- `login.php`
- `includes/functions.php`
- `pages/projects.php`
- `pages/tasks.php`
- `pages/dashboard.php`
- `pages/activity.php`
- `actions/task-detail.php`
- `actions/task-submit.php`
- `actions/task-review.php`
- `database/schema.sql`
- `uploads/.htaccess`

## Test yang Sudah Dilakukan

Dilakukan syntax check untuk semua file PHP:

```bash
php -l file.php
```

Hasil: seluruh file PHP lolos syntax check.

## Catatan Batasan

Versi ini masih Phase 1.1. Belum semua fitur React lama dipindahkan penuh ke PHP + MySQL.

Yang belum final:
- CRUD user lengkap.
- Edit/delete project penuh.
- Edit/delete task penuh.
- Project member management lengkap.
- Pagination di semua halaman.
- Dashboard visual advanced seperti React.
- Full permission matrix seperti dokumen final.

## Next Recommended Phase

Phase 1.2 — Permission & Role Foundation:
- membuat helper permission lebih lengkap,
- membatasi daftar user berdasarkan role,
- memperbaiki project member management,
- membuat assignment task ke anggota project,
- menjaga agar USER hanya bisa submit bukti.
