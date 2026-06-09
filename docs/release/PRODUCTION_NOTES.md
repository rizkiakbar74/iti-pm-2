# PRODUCTION_NOTES.md

## Status Project

Ini adalah **Final Candidate** untuk versi PHP + MySQL starter.

Project sudah memiliki:
- login/session PHP
- role hierarchy
- user management
- project management
- project member management
- task assignment
- submit proof
- review approve/reject
- submit history
- comments
- notifications
- activity log
- dashboard real-data
- upload validation
- mobile responsive polishing
- security hardening dasar

## Belum Disarankan Langsung Production Sebelum

- Password demo diganti.
- Hosting sudah HTTPS.
- Error display dimatikan.
- Backup database dibuat.
- Uji role dilakukan lengkap.
- Tailwind CDN diganti build lokal jika ingin production optimal.
- Audit security eksternal jika dipakai untuk data sensitif.

## File Import Database

Gunakan:

```text
database/schema.sql
```

## URL Lokal

```text
http://localhost/itipm_php_mysql_starter/login.php
```

## Akun Demo

```text
superadmin@iti.ac.id / password
admin@iti.ac.id / password
moderator@iti.ac.id / password
user@iti.ac.id / password
```
