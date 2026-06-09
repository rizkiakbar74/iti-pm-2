# PHASE_1_4_USER_MANAGEMENT_ROLE_FOUNDATION_REPORT.md

## Status

Phase 1.4 menambahkan fondasi manajemen pengguna dan guard role hierarchy dasar untuk versi PHP + MySQL.

## Perubahan Utama

### 1. Create User

Menu Pengguna sekarang memiliki form tambah user.

Aturan:
- SUPERADMIN bisa membuat ADMIN, MODERATOR, USER.
- ADMIN bisa membuat MODERATOR dan USER.
- MODERATOR bisa membuat USER.
- USER tidak bisa membuat user.

### 2. Update User

Pengelola dapat mengubah:
- nama
- role
- unit kerja
- status active/inactive

Validasi:
- Role target tetap mengikuti hierarchy.
- Role tidak bisa diturunkan jika user masih punya project aktif sebagai owner.
- Role tidak bisa diturunkan jika user masih punya bawahan aktif.
- SUPERADMIN terakhir tidak bisa diturunkan.

### 3. Reset Password

Pengelola dapat reset password user yang berada di bawah hierarkinya.
Password diproses memakai `password_hash()`, bukan hash statis.

### 4. Guard Deaktivasi User

User tidak bisa dinonaktifkan jika:
- masih menjadi owner project aktif
- masih memiliki task aktif
- masih memiliki bawahan aktif
- merupakan SUPERADMIN terakhir

### 5. Visibility User

Daftar user mengikuti hierarchy:
- SUPERADMIN melihat semua.
- ADMIN melihat dirinya dan role di bawahnya.
- MODERATOR melihat dirinya dan USER.
- USER tidak mengakses menu pengguna.

## File yang Diubah

- `pages/users.php`
- `includes/functions.php`

## Koreksi Sebelum ZIP

- `php -l` semua file PHP lolos.
- `reset_password.php` tidak ikut.
- Debug `Hash length` tidak ada.
- `uploads/.htaccess` ada.
- `database/schema.sql` ada.
- `role_rank_visible` tidak ada.
- Link detail project tetap ada.
- Form penerima task tetap ada.

## Test Manual

1. Login SUPERADMIN, buat ADMIN/MODERATOR/USER.
2. Login ADMIN, pastikan hanya bisa buat MODERATOR/USER.
3. Login MODERATOR, pastikan hanya bisa buat USER.
4. Coba nonaktifkan user yang masih punya task aktif: harus ditolak.
5. Coba turunkan role user yang masih owner project: harus ditolak.
6. Coba reset password user, lalu login dengan password baru.
