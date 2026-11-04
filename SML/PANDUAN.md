# Panduan SML (XAMPP)

## 1. Persiapan
1. Install XAMPP, buka XAMPP Control Panel, klik **Start** pada **Apache** dan **MySQL**.
2. Salin folder `SML` ke `C:\xampp\htdocs\` (Linux: `/opt/lampp/htdocs/`).
3. Buka `http://localhost/phpmyadmin` > tab **SQL** > tempel isi `database.sql` > **Go**.
4. Agar bisa upload video: edit `xampp/php/php.ini` ubah `upload_max_filesize=512M`, `post_max_size=520M`, `max_execution_time=300`, `max_input_time=300`, `memory_limit=256M` (file `.htaccess` juga sudah mengaturnya), lalu **Restart Apache**.
5. Buka `http://localhost/SML/register.php`. **Akun pertama yang mendaftar otomatis menjadi ADMIN**; akun berikutnya menjadi USER. ADMIN bisa mengubah role akun lain menjadi DEV/USER/ADMIN di Dev Panel.
6. Dari HP (satu WiFi): `http://IP-KOMPUTER/SML/` (cek IP dengan `ipconfig`).

## 2. Struktur folder (di htdocs)
```
htdocs/SML/
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
| Aksi | ADMIN | DEV | USER |
|---|---|---|---|
| Post, like, dislike, komentar, edit profil, hapus post/akun sendiri | ya | ya | ya |
| Hapus post/komentar orang lain | ya | ya | tidak |
| Membuka Dev Panel | ya | ya | tidak (403) |
| Hapus akun USER (satuan / banyak di Dev Panel) | ya | ya | tidak |
| Hapus akun DEV dan ADMIN lain | ya | tidak | tidak |
| Ubah role akun lain | ya | tidak | tidak |

Batas ukuran upload: 512MB per file.

Pengecekan hak akses dilakukan di **server** (`actions.php`), bukan hanya dengan menyembunyikan tombol, jadi tidak bisa dibypass lewat inspect element.

## 4. Cara kerja fitur
- **Refresh**: ikon refresh di sidebar, header, dan menu bawah memanggil `location.reload()`.
- **Foto/GIF/Video**: form memakai `enctype="multipart/form-data"`; `upload()` di `config.php` memeriksa ekstensi (jpg, jpeg, png, webp, gif, mp4, webm) lalu menyimpan dengan nama acak.
- **Link**: URL `http(s)://` di teks otomatis diubah jadi link oleh `linkify()`.
- **Like/Dislike**: klik sekali untuk memberi, klik lagi untuk membatalkan, klik tombol lawan untuk mengganti.
- **Hapus akun**: baris di tabel `posts`, `comments`, `reactions` ikut terhapus otomatis (`ON DELETE CASCADE`).

## 5. Catatan keamanan (untuk dipakai di jaringan lokal)
Password di-hash (`password_hash`), query memakai prepared statement (anti SQL injection), output di-escape (`e()`). Sebelum dipublikasikan ke internet, tambahkan token CSRF, validasi MIME file, dan password MySQL root.
