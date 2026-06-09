# PHASE_5_NOTIFICATION_ACTIVITY_ADVANCED_REPORT.md

## Fokus Phase 5

Phase 5 memperkuat Notification Center dan Activity Log agar lebih operasional, bisa ditindaklanjuti, dan lebih mudah diaudit.

## Perubahan Utama

### 1. Badge Notifikasi di Sidebar

Sidebar sekarang menampilkan jumlah notifikasi belum dibaca pada menu Notifikasi.

File:
- `includes/sidebar.php`

### 2. Notification Center Advanced

Halaman notifikasi sekarang mendukung:
- Filter semua/belum dibaca/sudah dibaca.
- Filter jenis notifikasi:
  - submit
  - komentar
  - project
  - task
  - deadline
  - reject
  - verified
  - umum
- Badge jenis notifikasi.
- Hapus satu notifikasi.
- Tandai semua dibaca.
- Bersihkan notifikasi terbaca.

File:
- `pages/notifications.php`

### 3. Klik Notifikasi Lebih Aman

Klik notifikasi sekarang melewati:

```text
actions/notification-open.php
```

Lalu notifikasi baru ditandai terbaca dan diarahkan ke:
- detail task jika punya `task_id`
- detail project jika punya `project_id`
- pusat notifikasi jika umum

### 4. Activity Log Advanced

Activity log sekarang:
- Bisa difilter berdasarkan aksi/detail.
- Bisa difilter berdasarkan aktor/role.
- Bisa difilter berdasarkan target:
  - semua
  - project
  - task
  - umum
- Baris activity bisa diklik.
- Jika activity terkait task, klik membuka detail task.
- Jika activity terkait project, klik membuka detail project.
- Ada ringkasan visible log, hasil filter, aktor unik, dan jenis aktivitas.
- Ada top activity shortcut.

File:
- `pages/activity.php`

### 5. Reminder Deadline Manual

Ditambahkan action:

```text
actions/deadline-reminder.php
```

Admin/Moderator/SUPERADMIN bisa membuat reminder deadline untuk task:
- lewat deadline
- deadline hari ini
- deadline 3 hari ke depan

Reminder dikirim ke penerima task.

### 6. Helper Baru

File:
- `includes/functions.php`

Helper baru:
- `get_unread_notification_count()`
- `get_activity_target_url()`
- `get_notification_type()`
- `notification_type_badge()`

### 7. Seed Notifikasi

`database/schema.sql` ditambah data demo notifikasi beragam untuk testing.

## Koreksi Sebelum ZIP

- `php -l` semua file PHP: lolos.
- `reset_password.php`: tidak ikut.
- Debug `Hash length`: tidak ada.
- `uploads/.htaccess`: ada.
- `database/schema.sql`: ada.
- `role_rank_visible`: tidak ada.
- Badge unread sidebar: ada.
- Activity clickable: ada.
- Notification type filter: ada.
- Deadline reminder action: ada.
- Seed notification phase 5: ada.

## Test Manual

1. Import ulang `database/schema.sql`.
2. Login sebagai `user@iti.ac.id`.
3. Cek badge notifikasi di sidebar.
4. Buka Notifikasi.
5. Coba filter belum dibaca/sudah dibaca.
6. Coba filter jenis notifikasi.
7. Klik notifikasi task/project.
8. Pastikan pindah ke detail terkait.
9. Login admin/moderator.
10. Buka Deadline.
11. Klik Buat Reminder Deadline.
12. Cek notifikasi user.
13. Buka Activity Log.
14. Coba filter aksi, aktor, dan target.
15. Klik activity yang terkait task/project.
