# PHASE_1_2_2_SCREENING_AND_TASK_ASSIGNEE_FIX_REPORT.md

## Ringkasan

Screening ulang menemukan beberapa bug logika dan bug teknis yang belum cukup tertutup di Phase 1.2.1.

## Bug yang Diperbaiki

### 1. Task baru otomatis diberikan ke pembuat task
Sebelumnya, ketika Admin/Moderator membuat task, task otomatis masuk ke pembuat task sendiri. Akibatnya anggota project/user yang baru ditambahkan tetap tidak menerima task.

Perbaikan:
- Form tambah task sekarang memiliki pilihan **Penerima Task**.
- Penerima task wajib dipilih minimal 1 orang.
- Penerima task harus merupakan anggota project yang dipilih.
- Server memvalidasi ulang assignee agar tidak bisa dimanipulasi dari HTML.

### 2. Task bisa dibuat tanpa penerima kerja
Sebelumnya task bisa dibuat walau tidak ada user penerima yang jelas.

Perbaikan:
- Task ditolak jika tidak ada minimal 1 penerima yang valid.
- Pesan error muncul di form tanpa menghilangkan input lama.

### 3. Activity Log masih menyimpan query lama yang berisiko error
Ada query prepare dengan function MySQL custom `role_rank_visible()` yang tidak tersedia.

Perbaikan:
- Query tersebut dihapus total.
- Hierarki role tetap difilter aman di PHP.

### 4. Redirect login dari subfolder actions bisa salah
`require_login()` sebelumnya redirect ke `login.php` relatif terhadap URL saat ini. Jika user membuka halaman actions tanpa session, redirect bisa mengarah ke `/actions/login.php`.

Perbaikan:
- Ditambahkan helper `app_url()`.
- `require_login()` sekarang redirect ke root app `login.php`.

### 5. Halaman detail task menampilkan form review walau belum ada bukti submitted
Reviewer bisa melihat form review padahal tidak ada submission yang menunggu review, lalu action mati di halaman proses.

Perbaikan:
- Form review hanya tampil jika ada submission berstatus `submitted`.
- Jika belum ada, tampil pesan yang jelas.

### 6. Tombol kembali dari detail task kehilangan konteks project
Sebelumnya kembali ke daftar tugas umum.

Perbaikan:
- Tombol kembali membawa `project_id` task terkait.

## File yang Diubah

- `includes/functions.php`
- `pages/tasks.php`
- `pages/activity.php`
- `actions/task-detail.php`

## Yang Tidak Diubah

- Struktur database
- Login/session
- Project member validation
- Submit proof flow
- Review approve/reject action
- Notifikasi dasar
- Activity log dasar

## Test yang Sudah Dilakukan

- `php -l` untuk semua file PHP.
- Cek `reset_password.php` tidak ikut.
- Cek `Hash length` debug tidak ada.
- Cek `uploads/.htaccess` ada.
- Cek schema database tetap tersedia.

## Test Manual yang Perlu Dilakukan User

1. Login sebagai `admin@iti.ac.id`.
2. Buat project baru dan pilih `Staf PMB`.
3. Buka menu Tugas.
4. Pilih project baru.
5. Buat task dan pilih `Staf PMB` sebagai penerima.
6. Login sebagai `user@iti.ac.id`.
7. Pastikan project terlihat.
8. Pastikan task baru terlihat.
9. Submit bukti.
10. Login kembali sebagai admin/moderator.
11. Review task.
