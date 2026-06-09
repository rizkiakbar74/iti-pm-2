# FINAL_QA_CHECKLIST.md

## Login & Session

- [ ] Login SUPERADMIN berhasil.
- [ ] Login ADMIN berhasil.
- [ ] Login MODERATOR berhasil.
- [ ] Login USER berhasil.
- [ ] Logout berhasil.
- [ ] Password salah menampilkan error normal.
- [ ] Login salah 5 kali mengaktifkan lock sementara.
- [ ] Session expired diarahkan ke login.

## Role & User Management

- [ ] SUPERADMIN bisa membuat ADMIN/MODERATOR/USER.
- [ ] ADMIN hanya bisa membuat MODERATOR/USER.
- [ ] MODERATOR hanya bisa membuat USER.
- [ ] USER tidak bisa membuka menu Pengguna.
- [ ] Reset password user berhasil.
- [ ] User dengan task aktif tidak bisa dinonaktifkan.
- [ ] Superadmin terakhir tidak bisa diturunkan.

## Project

- [ ] Project tidak bisa dibuat tanpa anggota.
- [ ] Admin bisa memilih anggota saat membuat project.
- [ ] User yang ditambahkan bisa melihat project.
- [ ] Detail project tampil.
- [ ] Owner/manager bisa tambah anggota.
- [ ] Anggota dengan task aktif tidak bisa dihapus.
- [ ] Project belum 100% tidak bisa diarsipkan oleh non-SUPERADMIN.
- [ ] SUPERADMIN bisa mengelola semua project.

## Task

- [ ] Task tidak bisa dibuat tanpa penerima.
- [ ] Penerima task wajib anggota project.
- [ ] Deadline task tidak boleh melewati deadline project.
- [ ] User penerima bisa melihat task.
- [ ] Task bisa diedit sebelum ada submit.
- [ ] Task terkunci setelah submitted/approved.
- [ ] Task approved bisa dibuka ulang oleh reviewer dengan alasan.

## Submit & Review

- [ ] User bisa submit bukti.
- [ ] Submit ganda saat menunggu review ditolak.
- [ ] Reviewer bisa approve.
- [ ] Reviewer bisa reject.
- [ ] Reject wajib alasan.
- [ ] User bisa submit ulang setelah reject.
- [ ] Riwayat submit tampil lengkap.
- [ ] File bukti bisa dibuka.

## Komentar

- [ ] User terkait bisa komentar task.
- [ ] Komentar masuk notifikasi.
- [ ] Komentar masuk activity log.
- [ ] Komentar bisa dihapus oleh pembuat/reviewer/SUPERADMIN.

## Dashboard

- [ ] KPI tampil real-data.
- [ ] Grafik periode 1 bulan jalan.
- [ ] Grafik periode 3 bulan jalan.
- [ ] Grafik periode 6 bulan jalan.
- [ ] Grafik periode 12 bulan jalan.
- [ ] Deadline & tindak lanjut bisa diklik.
- [ ] Dashboard user hanya menampilkan data visible.

## Notifikasi

- [ ] Badge unread tampil di sidebar.
- [ ] Filter unread/read berjalan.
- [ ] Filter jenis notifikasi berjalan.
- [ ] Klik notifikasi menuju task/project terkait.
- [ ] Tandai semua dibaca berjalan.
- [ ] Hapus notifikasi berjalan.

## Activity Log

- [ ] Activity log tampil sesuai hierarchy.
- [ ] Filter aksi/detail berjalan.
- [ ] Filter aktor/role berjalan.
- [ ] Filter target project/task/general berjalan.
- [ ] Klik activity membuka task/project terkait.

## Upload & Security

- [ ] Upload JPG/PNG/PDF valid berhasil.
- [ ] Upload PHP ditolak.
- [ ] Upload file >10MB ditolak.
- [ ] Folder uploads tidak menjalankan script.
- [ ] `.htaccess` root ikut terupload.
- [ ] `uploads/.htaccess` ikut terupload.

## Responsive

- [ ] Sidebar desktop tampil normal.
- [ ] Mobile nav horizontal scroll.
- [ ] Dashboard mobile tidak pecah.
- [ ] Task table mobile bisa scroll.
- [ ] User table mobile bisa scroll.
- [ ] Activity table mobile bisa scroll.
- [ ] Detail task/project nyaman dibaca di mobile.
