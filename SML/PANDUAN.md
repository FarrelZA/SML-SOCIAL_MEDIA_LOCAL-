# Panduan SML (XAMPP)

## 1. Persiapan
1. Install XAMPP, buka XAMPP Control Panel, klik **Start** pada **Apache** dan **MySQL**.
2. Salin folder `SML` ke `C:\xampp\htdocs\` (Linux: `/opt/lampp/htdocs/`).
3. Buka `http://localhost/phpmyadmin` > tab **SQL** > tempel isi `database.sql` > **Go**.
4. Batas upload 512MB sudah diatur di file `.htaccess` (folder `SML`). Pastikan file ini ikut tersalin (file berawalan titik kadang tersembunyi di Windows). Jika batas tetap kecil, ubah di `xampp/php/php.ini`:
   ```
   upload_max_filesize = 512M
   post_max_size = 520M
   max_execution_time = 300
   max_input_time = 300
   memory_limit = 256M
   ```
   lalu **Restart Apache**.
5. Buka `http://localhost/SML/register.php`. **Akun pertama yang mendaftar otomatis menjadi ADMIN**; akun berikutnya menjadi USER. ADMIN bisa mengubah role akun lain menjadi DEV/USER/ADMIN di Dev Panel.
6. Dari HP (satu WiFi): `http://IP-KOMPUTER/SML/` (cek IP dengan `ipconfig`).

## 2. Struktur folder (di htdocs)
```
htdocs/SML/
├── .htaccess           -> batas upload 512MB (untuk Apache + mod_php seperti XAMPP)
├── database.sql        -> struktur database & tabel
├── config.php          -> koneksi DB, session, fungsi bantu (upload, ikon, link)
├── register.php        -> daftar akun (akun pertama = ADMIN)
├── login.php           -> masuk
├── logout.php          -> keluar
├── index.php           -> beranda / feed + form post
├── profile.php         -> profil, edit profil, hapus akun, hapus user (DEV/ADMIN)
├── dev.php             -> Dev Panel: daftar semua user + hapus terpilih + ubah role (khusus DEV/ADMIN)
├── actions.php         -> semua aksi: post, like/dislike, komentar, hapus, edit profil, kelola user
├── includes/
│   ├── top.php         -> header HTML + sidebar kiri (desktop) + pesan error
│   ├── bottom.php      -> kolom kanan (desktop) + menu bawah (HP)
│   └── post.php        -> fungsi render satu postingan + komentar
├── assets/
│   ├── style.css       -> tampilan (tema gelap, responsif HP & desktop)
│   └── app.js          -> preview foto/GIF/video sebelum dikirim
└── uploads/            -> dibuat otomatis saat ada upload pertama
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

Pengecekan hak akses dilakukan di **server** (`actions.php`), bukan hanya dengan menyembunyikan tombol, jadi tidak bisa dibypass lewat inspect element.

## 4. Cara kerja fitur
- **Refresh**: ikon refresh di sidebar, header, dan menu bawah memanggil `location.reload()`.
- **Foto/GIF/Video**: form memakai `enctype="multipart/form-data"`; `upload()` di `config.php` memeriksa ukuran (maks 512MB per file) dan ekstensi (jpg, jpeg, png, webp, gif, mp4, webm), lalu menyimpan dengan nama acak. Foto profil dan banner hanya menerima gambar/GIF.
- **Preview**: setelah memilih file di form post, komentar, atau edit profil, `assets/app.js` langsung menampilkan preview (foto/GIF tampil, video bisa diputar) beserta nama dan ukuran file. Tombol **×** membatalkan pilihan. File dengan format tidak didukung atau lebih besar dari batas langsung ditolak dengan pesan, sebelum diunggah.
- **Pesan error upload**: jika upload gagal di server (terlalu besar, format salah, folder tidak bisa ditulis), alasannya tampil sebagai kotak merah di bawah judul halaman.
- **Batas upload yang berlaku**: yang terkecil antara 512MB dan `upload_max_filesize`/`post_max_size` di PHP. Angka ini dipakai juga oleh preview, jadi jika php.ini belum diubah, pesan akan menyebut batas yang sebenarnya.
- **Link**: URL `http(s)://` di teks otomatis diubah jadi link oleh `linkify()`.
- **Like/Dislike**: klik sekali untuk memberi, klik lagi untuk membatalkan, klik tombol lawan untuk mengganti.
- **Hapus akun**: baris di tabel `posts`, `comments`, `reactions` ikut terhapus otomatis (`ON DELETE CASCADE`).

## 5. Hosting di Debian / Ubuntu (Apache + PHP + MariaDB)
```bash
sudo apt install apache2 mariadb-server php libapache2-mod-php php-mysql
sudo cp -r SML /var/www/html/
sudo mariadb < /var/www/html/SML/database.sql
sudo mkdir -p /var/www/html/SML/uploads
sudo chown -R www-data:www-data /var/www/html/SML/uploads
```
- Di Debian, user `root` MariaDB tidak bisa login dari PHP dengan password kosong. Buat user khusus (`CREATE USER 'sml'@'localhost' IDENTIFIED BY '...'; GRANT ALL ON sml.* TO 'sml'@'localhost';`) lalu ubah baris `new PDO(...)` di `config.php`.
- Debian biasanya tidak membaca `.htaccess` (`AllowOverride None`), jadi atur batas upload di php.ini (`ls /etc/php` untuk melihat versinya, misalnya 8.4):
  ```
  sudo nano /etc/php/8.4/apache2/php.ini
  upload_max_filesize = 512M
  post_max_size = 520M
  max_execution_time = 300
  max_input_time = 300
  memory_limit = 256M
  ```
  lalu `sudo systemctl restart apache2`.
- Folder harus bernama `SML` (huruf besar/kecil dibedakan di Linux) agar cocok dengan `BASE` di `config.php`.

## 6. Catatan keamanan (untuk dipakai di jaringan lokal)
Password di-hash (`password_hash`), query memakai prepared statement (anti SQL injection), output di-escape (`e()`). Sebelum dipublikasikan ke internet, tambahkan token CSRF, validasi MIME file, dan password MySQL root.
