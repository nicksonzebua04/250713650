# PawTrack

Aplikasi komunitas untuk mendata kucing jalanan dan log interaksi, dengan PHP, MySQL/MariaDB, XHTML, CSS, dan JavaScript.

## Menjalankan aplikasi

1. Pastikan PHP dengan ekstensi `mysqli` dan MySQL/MariaDB tersedia.
2. Jalankan `database.sql` melalui MySQL untuk membuat database dan tabel.
   Aplikasi juga memperbarui skema status, kepemilikan, keterangan, foto kucing, dan foto profil secara otomatis pada database lama saat halaman terkait dibuka (akun database perlu memiliki izin `ALTER TABLE`).
3. Sesuaikan koneksi database di `config.php`, atau atur environment variable `CATTRACKER_DB_HOST`, `CATTRACKER_DB_USER`, `CATTRACKER_DB_PASS`, dan `CATTRACKER_DB_NAME`.
4. Letakkan folder proyek di document root server PHP (misalnya `htdocs` di XAMPP), nyalakan Apache dan MySQL, lalu buka `http://localhost/test%20web/login.xhtml`.
5. Buat akun melalui halaman pendaftaran. Akun baru otomatis menjadi relawan.

Foto kucing wajib ditambahkan saat membuat data baru; saat mengedit, foto boleh dipertahankan atau diganti. Pengguna juga dapat menambahkan atau mengganti foto profil dari halaman **Profil Saya**. Format foto yang diterima JPG, PNG, atau WebP hingga 5 MB. Pada ponsel, pilih foto dari galeri atau folder; pada laptop, pilih melalui File Explorer.

Halaman login dan pendaftaran menampilkan video kucing sebagai dekorasi latar di panel sambutan. Video diputar tanpa suara dan disembunyikan saat perangkat meminta pengurangan animasi.

## Peran akun

- **Relawan** dapat melihat data kucing dan log komunitas, menambah data kucing, membuat/mengelola log miliknya, serta memperbarui profil.
- **Admin** dapat mengubah/menghapus data kucing, mengelola seluruh log, mengatur peran akun, dan menghapus akun tanpa log dari menu **Admin**. Akun aktif, admin terakhir, serta akun yang masih memiliki log dilindungi agar riwayat interaksi tetap utuh.
- Data kucing dan log interaksi tersedia pada halaman terpisah melalui navigasi utama.
- Tidak ada akun admin atau kata sandi bawaan. Untuk mempromosikan akun pertama, buka phpMyAdmin, pilih database `cattracker_local`, lalu jalankan:

  ```sql
  UPDATE users SET role = 'admin' WHERE username = 'username_anda';
  ```

  Ganti `username_anda` dengan username akun yang sudah terdaftar, lalu keluar dan masuk kembali.

Password disimpan menggunakan `password_hash()` dan diverifikasi dengan `password_verify()`. Operasi perubahan memakai prepared statement, token CSRF, pemeriksaan peran di server, dan permintaan POST.
