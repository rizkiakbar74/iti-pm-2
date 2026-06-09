# Expected Menu by Role

## SUPERADMIN

Expected visible menus:
- Dashboard
- Project
- Tugas
- Deadline
- Notifikasi
- Activity Log
- Pengguna
- Profil

## ADMIN

Expected visible menus:
- Dashboard
- Project
- Tugas
- Deadline
- Notifikasi
- Activity Log
- Pengguna
- Profil

Should not:
- assign project/task to SUPERADMIN
- manage SUPERADMIN data

## MODERATOR

Expected visible menus:
- Dashboard
- Project
- Tugas
- Deadline
- Notifikasi
- Activity Log
- Pengguna
- Profil

Should only create/manage USER.

Should not:
- assign to ADMIN/SUPERADMIN
- see ADMIN/SUPERADMIN activity

## USER

Expected visible menus:
- Dashboard
- Project
- Tugas
- Deadline
- Notifikasi
- Activity Log
- Profil

Should not see:
- Pengguna

Should not:
- create user
- create project
- create task
- review task
- archive project
