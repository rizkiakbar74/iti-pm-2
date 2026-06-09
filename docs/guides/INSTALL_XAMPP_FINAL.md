# INSTALL_XAMPP_FINAL.md

## ITI PROJECT MANAGER — PHP + MySQL Final Candidate

### 1. Copy folder ke htdocs

Extract ZIP, lalu copy folder:

```text
itipm_php_mysql_starter
```

ke:

```text
C:\xampp\htdocs\
```

Hasil akhir:

```text
C:\xampp\htdocs\itipm_php_mysql_starter\
```

### 2. Jalankan XAMPP

Aktifkan:

- Apache
- MySQL

### 3. Import database

Buka:

```text
http://localhost/phpmyadmin
```

Import file:

```text
C:\xampp\htdocs\itipm_php_mysql_starter\database\schema.sql
```

Database default:

```text
itipm_db
```

### 4. Cek config database

Buka:

```text
config/database.php
```

Default XAMPP:

```php
$DB_HOST = '127.0.0.1';
$DB_NAME = 'itipm_db';
$DB_USER = 'root';
$DB_PASS = '';
```

Jika MySQL kamu pakai password, ubah `$DB_PASS`.

### 5. Buka aplikasi

```text
http://localhost/itipm_php_mysql_starter/login.php
```

### 6. Akun demo

```text
superadmin@iti.ac.id / password
admin@iti.ac.id / password
moderator@iti.ac.id / password
user@iti.ac.id / password
```

### 7. Setelah berhasil login

Test urutan ini:

1. Dashboard
2. Project
3. Detail Project
4. Tambah Project + anggota
5. Tambah Task + penerima
6. Submit bukti sebagai user
7. Review approve/reject sebagai admin/moderator
8. Komentar task
9. Notifikasi
10. Activity Log
11. Upload file
12. Responsive mobile

### 8. Catatan penting

- Jangan upload `reset_password.php`.
- Jangan import SQL selain `database/schema.sql`.
- Untuk production, ganti semua password demo.
- Tailwind saat ini masih memakai CDN untuk starter/testing.
