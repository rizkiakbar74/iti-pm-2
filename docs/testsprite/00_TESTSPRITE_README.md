# ITI PROJECT MANAGER — TestSprite Testing Package

Paket ini disiapkan untuk membantu testing aplikasi ITI PROJECT MANAGER menggunakan TestSprite atau AI web testing sejenis.

## Target Aplikasi

Stack:
- PHP Native
- MySQL / phpMyAdmin
- Tailwind CSS via CDN
- XAMPP/local hosting untuk testing awal

Folder project terbaru:
- `itipm_php_mysql_starter`

Database:
- import hanya `database/schema.sql`

## Syarat Penting

Jika memakai TestSprite Web Portal/cloud testing, URL aplikasi harus bisa diakses oleh TestSprite.

Tidak bisa langsung pakai:

```text
http://localhost/itipm_php_mysql_starter/login.php
```

Kecuali TestSprite agent berjalan di environment yang bisa mengakses localhost tersebut.

Opsi agar bisa dites:
1. Deploy sementara ke hosting/staging.
2. Pakai tunnel seperti ngrok atau Cloudflare Tunnel.
3. Jalankan TestSprite MCP/agent di IDE/lokal jika tersedia dan bisa akses localhost.

## URL Testing yang Harus Diberikan ke TestSprite

Ganti placeholder ini dengan URL asli:

```text
{{APP_BASE_URL}}/login.php
```

Contoh lokal:

```text
http://localhost/itipm_php_mysql_starter/login.php
```

Contoh staging:

```text
https://staging-domain-kamu.com/itipm/login.php
```
