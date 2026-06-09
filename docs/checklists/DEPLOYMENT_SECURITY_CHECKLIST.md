# DEPLOYMENT_SECURITY_CHECKLIST.md

Checklist sebelum upload ke hosting:

## Database
- [ ] Database production dibuat manual.
- [ ] Import `database/schema.sql`.
- [ ] Password akun demo diganti.
- [ ] User demo yang tidak dipakai dinonaktifkan.
- [ ] Backup database tersedia.

## Config
- [ ] `config/database.php` disesuaikan host/user/password production.
- [ ] Tidak ada `reset_password.php`.
- [ ] Tidak ada debug `Hash length`.
- [ ] PHP `display_errors` dimatikan di production.

## Upload
- [ ] Folder `uploads/` writable.
- [ ] `uploads/.htaccess` ikut terupload.
- [ ] File `.php` tidak bisa dijalankan dari uploads.
- [ ] Ukuran upload sesuai batas hosting.

## Web Server
- [ ] `.htaccess` root ikut terupload.
- [ ] HTTPS aktif.
- [ ] Directory listing mati.
- [ ] File `.sql`, `.md`, `.env`, `.log` tidak bisa diakses publik.

## Testing
- [ ] Login/logout.
- [ ] Role SUPERADMIN/ADMIN/MODERATOR/USER.
- [ ] Project CRUD.
- [ ] Task CRUD.
- [ ] Submit bukti.
- [ ] Review approve/reject.
- [ ] Upload file.
- [ ] Notifikasi.
- [ ] Activity log.
- [ ] Mobile responsive.
