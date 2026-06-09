# SECURITY_HARDENING_GUIDE.md

## Phase 7 Security Notes

Project ini masih PHP native starter, tetapi sudah ditambah hardening dasar:

1. Session cookie:
   - `HttpOnly`
   - `SameSite=Lax`
   - strict mode
   - idle timeout 2 jam

2. Security headers:
   - `X-Frame-Options`
   - `X-Content-Type-Options`
   - `Referrer-Policy`
   - `Permissions-Policy`

3. Login throttling:
   - 5 kali gagal login di browser yang sama → lock 5 menit.

4. Upload policy:
   - maksimal 10 MB
   - ekstensi dibatasi
   - MIME image dicek
   - nama file dibuat random
   - file executable/script diblok
   - folder uploads tidak boleh menjalankan PHP

5. .htaccess:
   - root: disable indexes dan blok file sensitif
   - uploads: disable indexes dan blok script execution

## Catatan Production

Sebelum production:
- ganti password default
- matikan display_errors
- aktifkan HTTPS
- pindahkan config rahasia keluar webroot jika hosting memungkinkan
- backup database rutin
- review permission folder uploads
- gunakan Tailwind build lokal, bukan CDN
