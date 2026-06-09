# PHASE 1.2.1 — Project Member Validation Fix

## Perubahan Utama

Revisi ini memperbaiki logika pembuatan project pada versi PHP + MySQL.

Sebelumnya project tetap bisa dibuat walaupun tidak ada anggota kerja yang dipilih. Ini salah secara workflow karena project hanya akan terlihat oleh owner/pembuat dan tidak bisa dikerjakan oleh user lain.

## Fix yang Dilakukan

1. Project sekarang wajib memiliki minimal 1 anggota selain pembuat/owner.
2. Jika Admin/Moderator/Superadmin membuat project tanpa memilih anggota, sistem menolak submit dan menampilkan pesan error.
3. Input form tetap dipertahankan setelah error agar user tidak perlu mengetik ulang judul/deskripsi/deadline.
4. Checkbox anggota yang sudah dipilih tetap checked jika validasi gagal.
5. Teks instruksi form diperjelas:
   - pembuat project otomatis menjadi owner
   - minimal 1 anggota kerja wajib dipilih
6. Jika tidak ada anggota valid yang bisa ditambahkan sesuai role, sistem menampilkan warning dan project tidak bisa dibuat.

## Mekanisme yang Tidak Diubah

- Login/session
- Role hierarchy
- Dashboard
- Project list
- Task flow
- Submit proof
- Review approve/reject
- Notification
- Activity log
- Upload security
- Database schema

## File yang Diubah

- `pages/projects.php`

## Cara Test

1. Login sebagai `admin@iti.ac.id / password`.
2. Buka menu Project.
3. Isi nama project, deskripsi, deadline.
4. Jangan pilih anggota project.
5. Klik Tambah Project.
6. Hasil yang benar: project tidak dibuat dan muncul error validasi.
7. Pilih minimal 1 anggota project.
8. Klik Tambah Project.
9. Hasil yang benar: project berhasil dibuat dan anggota dapat melihat project tersebut.

## Status Validasi

- `php -l` semua file PHP: lolos.
- `reset_password.php`: tidak ikut.
- Debug login hash length: tidak ada.
- `uploads/.htaccess`: ada.
