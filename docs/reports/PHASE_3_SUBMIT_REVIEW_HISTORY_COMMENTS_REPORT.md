# PHASE_3_SUBMIT_REVIEW_HISTORY_COMMENTS_REPORT.md

## Fokus Phase 3

Phase 3 memperdalam workflow submit bukti, review, riwayat submit, komentar task, notifikasi, dan audit activity.

## Perubahan Utama

### 1. Riwayat Submit & Review Lebih Lengkap

Halaman detail task sekarang menampilkan:
- Nama submitter.
- Status submit.
- Waktu submit.
- Catatan submit.
- File bukti.
- Nama reviewer.
- Waktu review.
- Catatan review.

Semua submit tetap disimpan dan urutan terbaru tampil paling atas.

### 2. Pencegahan Submit Ganda

User tidak bisa submit ulang jika submit sebelumnya masih berstatus `submitted`.

Alur benar:
- Submit pertama → menunggu review.
- Jika reject → user boleh submit ulang.
- Jika approved → task selesai dan submit ulang ditutup.

### 3. Review Lebih Aman

Form review hanya tampil jika:
- User punya hak review.
- Ada submission dengan status `submitted`.

Jika belum ada bukti yang menunggu review, form review tidak muncul.

### 4. Komentar Task

Ditambahkan fitur komentar pada detail task:
- User terkait task bisa menulis komentar.
- Komentar masuk activity log.
- Komentar memicu notifikasi ke pihak terkait.
- Komentar bisa dihapus oleh pembuat komentar, SUPERADMIN, atau reviewer.

File baru:
- `actions/task-comment.php`

### 5. Notifikasi Lebih Rapi

Halaman Notifikasi sekarang:
- Tidak otomatis menandai semua terbaca saat dibuka.
- Ada filter semua/belum dibaca/sudah dibaca.
- Ada tombol tandai semua dibaca.
- Ada tombol bersihkan notifikasi terbaca.
- Klik notifikasi lewat `notification-open.php`, lalu baru ditandai terbaca.

File baru:
- `actions/notification-open.php`

### 6. Notifikasi Submit Diperluas

Saat user submit bukti:
- Pembuat task mendapat notifikasi.
- Owner project mendapat notifikasi.
- Manager project mendapat notifikasi.

### 7. Seed Data Komentar

`database/schema.sql` ditambah contoh komentar task agar halaman komentar tidak kosong saat import ulang.

## File yang Diubah

- `actions/task-submit.php`
- `actions/task-detail.php`
- `actions/task-comment.php`
- `actions/notification-open.php`
- `pages/notifications.php`
- `database/schema.sql`

## Koreksi Sebelum ZIP

- `php -l` semua file PHP: lolos.
- `reset_password.php`: tidak ikut.
- Debug `Hash length`: tidak ada.
- `uploads/.htaccess`: ada.
- `database/schema.sql`: ada.
- `role_rank_visible`: tidak ada.
- `task-comment.php`: ada.
- `notification-open.php`: ada.
- Guard submit ganda: ada.
- Tabel komentar: ada.

## Test Manual

1. Login user.
2. Buka task yang ditugaskan.
3. Submit bukti.
4. Coba submit ulang sebelum review: harus ditolak.
5. Login reviewer.
6. Buka task.
7. Cek riwayat submit dan nama submitter.
8. Reject dengan alasan.
9. Login user.
10. Submit ulang.
11. Login reviewer.
12. Approve.
13. Coba komentar task dari user/reviewer.
14. Cek notifikasi.
15. Klik notifikasi dan pastikan berpindah ke task/project terkait.
