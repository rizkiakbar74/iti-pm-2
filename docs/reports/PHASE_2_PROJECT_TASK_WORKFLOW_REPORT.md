# PHASE_2_PROJECT_TASK_WORKFLOW_REPORT.md

## Fokus Phase 2

Phase 2 memperkuat workflow Project dan Task pada versi PHP + MySQL, tanpa mengubah arah stack dan tanpa menghapus fitur phase sebelumnya.

## Perubahan Utama

### 1. Edit Project

Di halaman Detail Project, owner/manager project dan SUPERADMIN bisa:
- Mengubah nama project.
- Mengubah deskripsi.
- Mengubah deadline.
- Mengubah status project.

Status yang tersedia:
- Draft
- Aktif
- Dalam Review
- Selesai
- Archived khusus SUPERADMIN

### 2. Arsip Project

Project bisa diarsipkan dari halaman Detail Project.

Aturan:
- SUPERADMIN bisa arsipkan.
- Role lain hanya bisa arsipkan jika progress project 100%.
- Ini menjaga project belum selesai agar tidak hilang dari workflow.

### 3. Auto Sync Status Project

Status project otomatis disinkronkan dari task:
- Semua task approved → project completed.
- Ada task submitted → project review.
- Selain itu → active.
- Project draft/archived tidak diganggu auto-sync.

Auto-sync dipanggil saat:
- Detail project dibuka.
- Task disubmit.
- Task direview.

### 4. Edit Task

Di halaman Detail Task, pihak yang berhak bisa edit task jika task belum punya submission submitted/approved.

Yang bisa diedit:
- Judul task.
- Deskripsi.
- Deadline.
- Penerima task.

Validasi:
- Deadline task tidak boleh melewati deadline project.
- Task wajib punya minimal 1 penerima valid.
- Penerima task wajib anggota project.
- Task yang sudah submit/approved tidak bisa edit data utama.

### 5. Buka Ulang Task

Reviewer bisa membuka ulang task yang sudah approved.

Aturan:
- Hanya task approved yang bisa dibuka ulang.
- Alasan buka ulang wajib diisi.
- Status task berubah menjadi rejected agar user bisa submit ulang.
- Activity log mencatat alasan.

### 6. Detail Task Lebih Informatif

Detail task sekarang menampilkan:
- Status label lebih manusiawi.
- Penerima task.
- Form edit task jika role berhak.
- Form reopen jika role berhak dan task approved.

## File yang Diubah

- `includes/functions.php`
- `actions/project-detail.php`
- `actions/task-detail.php`
- `actions/task-submit.php`
- `actions/task-review.php`

## Koreksi Sebelum ZIP

- `php -l` semua file PHP: lolos.
- `reset_password.php`: tidak ikut.
- Debug `Hash length`: tidak ada.
- `uploads/.htaccess`: ada.
- `database/schema.sql`: ada.
- `role_rank_visible`: tidak ada.
- Form penerima task: ada.
- Halaman detail project: ada.
- Fitur edit project: ada.
- Fitur edit task: ada.
- Fitur reopen task: ada.

## Test Manual yang Disarankan

1. Login sebagai admin.
2. Buka Detail Project.
3. Edit nama/deskripsi/deadline project.
4. Coba arsipkan project yang progress belum 100%: harus ditolak.
5. Buat task baru dan pilih penerima.
6. Buka detail task sebelum submit.
7. Edit task dan ubah penerima.
8. Login user penerima, submit bukti.
9. Login reviewer, approve.
10. Coba buka ulang task approved dengan alasan.
11. Pastikan task kembali ke status perlu revisi/rejected.
