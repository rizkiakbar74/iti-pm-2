# PHASE_7_SECURITY_HARDENING_FILE_POLICY_REPORT.md

## Fokus Phase 7

Phase 7 memperkuat keamanan dasar aplikasi PHP + MySQL sebelum masuk final QA/deployment.

## Perubahan Utama

### 1. Session Hardening

File:
- `includes/auth.php`

Perubahan:
- `session.use_strict_mode`
- cookie `HttpOnly`
- cookie `SameSite=Lax`
- `secure` otomatis aktif jika HTTPS
- idle timeout 2 jam
- security headers dasar

### 2. Login Throttling

File:
- `login.php`

Perubahan:
- 5 kali gagal login di browser yang sama akan lock sementara 5 menit.
- Pesan expired session ditambahkan.

### 3. Upload Security

File:
- `includes/functions.php`

Perubahan:
- validasi upload error
- maksimal 10 MB
- ekstensi dibatasi
- blok ekstensi script/executable
- MIME check untuk gambar
- nama file random lebih kuat
- chmod file upload 0644

### 4. Proteksi .htaccess

File baru/diubah:
- `.htaccess`
- `uploads/.htaccess`

Proteksi:
- disable directory listing
- blok file sensitif
- blok script execution di uploads
- security headers via Apache jika tersedia

### 5. Dokumentasi Security

File baru:
- `docs/security/SECURITY_HARDENING_GUIDE.md`
- `docs/checklists/DEPLOYMENT_SECURITY_CHECKLIST.md`

## Yang Tidak Diubah

- Workflow role
- Project/task logic
- Submit/review
- Dashboard
- Notifikasi
- Activity log
- Database schema inti

## Koreksi Sebelum ZIP

- `php -l` semua file PHP: lolos.
- `reset_password.php`: tidak ikut.
- Debug `Hash length`: tidak ada.
- `.htaccess` root: ada.
- `uploads/.htaccess`: ada.
- `database/schema.sql`: ada.
- `role_rank_visible`: tidak ada.
- session hardening: ada.
- login throttling: ada.
- upload hardening: ada.

## Test Manual

1. Login normal.
2. Coba salah password 5 kali.
3. Pastikan lock sementara aktif.
4. Upload file gambar/pdf valid.
5. Coba upload file `.php`: harus ditolak.
6. Cek folder uploads tidak menampilkan directory listing.
7. Cek aplikasi tetap jalan di XAMPP.
