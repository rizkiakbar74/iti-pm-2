# RUNNING_PROJECT_GUIDE.md

## Target Project

Versi ini adalah starter **PHP + MySQL/phpMyAdmin + Tailwind CSS** untuk ITI PROJECT MANAGER.

Ini bukan lagi React/Vite/localStorage.

---

## Cara Menjalankan di XAMPP

### 1. Pindahkan folder

Copy folder `itipm_php_mysql` ke:

```text
C:\xampp\htdocs\
```

Sehingga menjadi:

```text
C:\xampp\htdocs\itipm_php_mysql
```

### 2. Jalankan XAMPP

Aktifkan:

- Apache
- MySQL

### 3. Buat database lewat phpMyAdmin

Buka:

```text
http://localhost/phpmyadmin
```

Lalu import file:

```text
database/schema.sql
```

File ini akan membuat database:

```text
itipm_db
```

### 4. Cek config database

Buka file:

```text
config/database.php
```

Default XAMPP:

```php
$DB_USER = 'root';
$DB_PASS = '';
```

Kalau MySQL kamu pakai password, ubah di file itu.

### 5. Buka aplikasi

```text
http://localhost/itipm_php_mysql/login.php
```

Akun demo:

```text
superadmin@iti.ac.id / password
admin@iti.ac.id / password
moderator@iti.ac.id / password
user@iti.ac.id / password
```

---

## Catatan Tailwind

Versi ini memakai Tailwind CDN:

```html
<script src="https://cdn.tailwindcss.com"></script>
```

Untuk production, Tailwind sebaiknya di-build lokal, tetapi untuk XAMPP/testing ini sudah cukup.

---

## Scope Versi Ini

Yang sudah ada:

- Login PHP session
- Role dasar
- Dashboard MySQL
- Project list/create
- Task list/create
- Submit bukti
- Review approve/reject
- Deadline page
- Notification page
- Activity log
- User list
- Profile
- Upload proof file ke folder uploads

Yang belum full seperti React lama:

- UI belum 100% sama dengan versi React
- Semua fitur kompleks belum dipindahkan
- Pagination belum lengkap di semua halaman
- Edit/delete detail belum lengkap
- Manager/co-owner project belum advanced
- Security production belum final

Ini adalah fondasi PHP + MySQL agar project bisa mulai jalan di XAMPP.
