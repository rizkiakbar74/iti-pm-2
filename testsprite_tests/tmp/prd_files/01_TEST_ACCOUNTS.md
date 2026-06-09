# Test Accounts

Semua akun demo memakai password:

```text
password
```

## SUPERADMIN

```text
Email: superadmin@iti.ac.id
Password: password
Role: SUPERADMIN
Unit: Team PDSI
```

Ekspektasi:
- Bisa melihat semua project, task, user, notifikasi, activity log.
- Bisa membuat semua role.
- Bisa mengelola semua project/task.
- Bisa review semua task.
- Bisa arsip/restore secara bebas sesuai fitur yang tersedia.

## ADMIN

```text
Email: admin@iti.ac.id
Password: password
Role: ADMIN
Unit: Rektor / Warek A
```

Ekspektasi:
- Bisa membuat MODERATOR dan USER.
- Bisa membuat project dengan anggota.
- Bisa assign project/task ke Admin/Moderator/User, bukan Superadmin.
- Bisa review task pada project yang dikelola/dibuat.

## MODERATOR

```text
Email: moderator@iti.ac.id
Password: password
Role: MODERATOR
Unit: Kepala PMB
```

Ekspektasi:
- Bisa membuat USER.
- Bisa membuat project dengan anggota Moderator/User.
- Tidak bisa assign ke Admin/Superadmin.
- Bisa review task pada project yang dikelola/dibuat.

## USER

```text
Email: user@iti.ac.id
Password: password
Role: USER
Unit: Staf PMB
```

Ekspektasi:
- Tidak bisa membuat user.
- Tidak bisa membuat project.
- Tidak bisa membuat task.
- Bisa melihat project/task yang ditugaskan.
- Bisa submit bukti.
- Tidak bisa review task.
