# Panduan Social_Media_Local (XAMPP)

## 1. Persiapan
1. Install XAMPP, buka XAMPP Control Panel, klik **Start** pada **Apache** dan **MySQL**.
2. Salin folder `Social_Media_Local` ke `C:\xampp\htdocs\` (Linux: `/opt/lampp/htdocs/`).
3. Buka `http://localhost/phpmyadmin` > tab **SQL** > tempel isi `database.sql` > **Go**.
4. Agar bisa upload video: edit `xampp/php/php.ini` ubah `upload_max_filesize=64M` dan `post_max_size=64M`, lalu **Restart Apache**.
5. Buka `http://localhost/Social_Media_Local/register.php`. **Akun pertama yang mendaftar otomatis menjadi DEV**; akun berikutnya menjadi USER.
6. Dari HP (satu WiFi): `http://IP-KOMPUTER/Social_Media_Local/` (cek IP dengan `ipconfig`).

## 2. Struktur folder (di htdocs)
```
htdocs/Social_Media_Local/
├── database.sql        -> struktur database & tabel
├── config.php          -> koneksi DB, session, fungsi bantu (upload, ikon, link)
├── register.php        -> daftar akun (akun pertama = DEV)
├── login.php           -> masuk
├── logout.php          -> keluar
├── index.php           -> beranda / feed + form post
├── profile.php         -> profil, edit profil, hapus akun, hapus user (DEV)
├── dev.php             -> Dev Panel: daftar semua user + hapus user terpilih (khusus DEV)
├── actions.php         -> semua aksi: post, like/dislike, komentar, hapus, edit profil, hapus banyak user
├── includes/
│   ├── top.php         -> header HTML + sidebar kiri (desktop)
│   ├── bottom.php      -> kolom kanan (desktop) + menu bawah (HP)
│   └── post.php        -> fungsi render satu postingan + komentar
├── assets/style.css    -> tampilan (tema gelap, responsif HP & desktop)
└── uploads/
    ├── avatars/        -> foto profil
    ├── banners/        -> banner profil
    └── posts/          -> media post & komentar
```

## 3. Hak akses
| Aksi | DEV | USER |
|---|---|---|
| Post, like, dislike, komentar | ya | ya |
| Edit profil sendiri, hapus post sendiri, hapus akun sendiri | ya | ya |
| Hapus post/komentar orang lain | ya | tidak |
| Hapus user lain (satu per satu atau banyak sekaligus lewat Dev Panel) | ya | tidak |
| Membuka Dev Panel | ya | tidak (ditolak 403) |
| Terlihat di daftar pengguna | hanya terlihat oleh sesama DEV | - |

Pengecekan hak akses dilakukan di **server** (`actions.php`), bukan hanya dengan menyembunyikan tombol, jadi tidak bisa dibypass lewat inspect element.

## 4. Cara kerja fitur
- **Refresh**: ikon refresh di sidebar, header, dan menu bawah memanggil `location.reload()`.
- **Foto/GIF/Video**: form memakai `enctype="multipart/form-data"`; `upload()` di `config.php` memeriksa ekstensi (jpg, jpeg, png, webp, gif, mp4, webm) lalu menyimpan dengan nama acak.
- **Link**: URL `http(s)://` di teks otomatis diubah jadi link oleh `linkify()`.
- **Like/Dislike**: klik sekali untuk memberi, klik lagi untuk membatalkan, klik tombol lawan untuk mengganti.
- **Hapus akun**: baris di tabel `posts`, `comments`, `reactions` ikut terhapus otomatis (`ON DELETE CASCADE`).

## 5. Catatan keamanan (untuk dipakai di jaringan lokal)
Password di-hash (`password_hash`), query memakai prepared statement (anti SQL injection), output di-escape (`e()`). Sebelum dipublikasikan ke internet, tambahkan token CSRF, validasi MIME file, dan password MySQL root.
