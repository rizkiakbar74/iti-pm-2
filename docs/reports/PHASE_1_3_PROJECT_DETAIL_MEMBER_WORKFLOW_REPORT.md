# PHASE_1_3_PROJECT_DETAIL_MEMBER_WORKFLOW_REPORT.md

## Status

Phase 1.3 menambahkan fondasi workflow project yang lebih masuk akal untuk versi PHP + MySQL.

## Fokus Phase 1.3

- Project detail.
- Manajemen anggota project.
- Pengelolaan penerima task berdasarkan anggota project.
- Screening ulang bug workflow dari Phase 1.2.2.

## Perubahan Utama

### 1. Halaman Detail Project

File baru:

```text
actions/project-detail.php
```

Fitur:
- Melihat detail project.
- Melihat owner project.
- Melihat progress project.
- Melihat jumlah anggota.
- Melihat task project.
- Melihat anggota project.
- Link kelola task sesuai project.

### 2. Manajemen Anggota Project

Owner/manager project dan SUPERADMIN bisa:
- Menambah anggota project.
- Menentukan role anggota di project: member/manager.
- Menghapus anggota project.

Validasi:
- Owner project tidak bisa dihapus.
- Anggota tidak bisa dihapus jika masih memiliki task aktif di project tersebut.
- Role assign tetap mengikuti hierarchy:
  - SUPERADMIN bisa semua.
  - ADMIN bisa Admin/Moderator/User.
  - MODERATOR bisa Moderator/User.
  - USER tidak bisa assign.

### 3. Task Assignee Lebih Benar

Dari Phase 1.2.2:
- Task wajib punya minimal 1 penerima.
- Penerima task wajib anggota project.
- Penerima task tervalidasi server-side.

### 4. Link Project Diperbaiki

Menu Project sekarang punya:
- Detail Project.
- Lihat Task.

### 5. Helper Permission Ditambah

File:

```text
includes/functions.php
```

Helper baru:
- `can_manage_project_members()`
- `get_project_by_id()`
- `get_project_active_task_count_for_user()`
- `get_project_progress_percent()`
- `get_task_status_label()`

## Koreksi yang Dilakukan Sebelum ZIP

- `php -l` semua file PHP: lolos.
- `reset_password.php` tidak ikut.
- Debug `Hash length` tidak ada.
- `uploads/.htaccess` ada.
- `database/schema.sql` ada.
- `role_rank_visible` tidak ada.
- Halaman detail project ada.
- Form penerima task ada.

## Test Manual yang Wajib Dilakukan

1. Login sebagai `admin@iti.ac.id`.
2. Buat project baru dan pilih `Staf PMB`.
3. Klik Detail Project.
4. Tambahkan anggota lain jika tersedia.
5. Coba hapus anggota yang masih punya task aktif: harus ditolak.
6. Buat task di project tersebut dan pilih penerima.
7. Login sebagai user penerima.
8. Pastikan project dan task terlihat.
9. Submit bukti.
10. Login admin/moderator.
11. Review task.
