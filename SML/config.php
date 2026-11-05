<?php
/**
 * config.php
 *
 * Pengaturan utama aplikasi: session, koneksi database, dan fungsi bantu.
 * File ini di-require oleh hampir semua halaman.
 */

// Memulai session supaya status login tetap tersimpan antar halaman
session_start();

// ---------------------------------------------------------------------
// Pengaturan
// ---------------------------------------------------------------------

// Koneksi ke MySQL XAMPP (user default: root, password kosong)
$pdo = new PDO('mysql:host=localhost;dbname=sml;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // tampilkan error SQL
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // hasil query berupa array asosiatif
]);

// Alamat dasar web di localhost (sesuaikan dengan nama folder di htdocs)
define('BASE', '/SML/');

// Batas ukuran upload per file: 512 MB
define('MAX_UPLOAD', 512 * 1024 * 1024);

// Ekstensi file yang boleh diunggah
define('IMAGE_EXT', ['jpg', 'jpeg', 'png', 'webp']);   // GIF ditangani terpisah
define('VIDEO_EXT', ['mp4', 'webm']);

// Atribut accept untuk <input type="file"> (filter di dialog pilih file)
define('ACCEPT_IMAGE', 'image/jpeg,image/png,image/webp,image/gif');
define('ACCEPT_MEDIA', ACCEPT_IMAGE . ',video/mp4,video/webm');


// ---------------------------------------------------------------------
// Fungsi umum
// ---------------------------------------------------------------------

/**
 * e(): membersihkan teks agar kode HTML/JS berbahaya tidak dieksekusi (anti-XSS).
 */
function e($text)
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * me(): mengambil data user yang sedang login (null jika belum login).
 */
function me()
{
    global $pdo;
    static $user = null;

    if (!$user && isset($_SESSION['uid'])) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['uid']]);
        $user = $stmt->fetch();
    }

    return $user;
}

/**
 * need_login(): paksa pindah ke halaman login jika belum login.
 */
function need_login()
{
    if (!me()) {
        header('Location: ' . BASE . 'login.php');
        exit;
    }
}

/**
 * is_admin(): true jika user yang login berole ADMIN.
 */
function is_admin()
{
    $user = me();
    return $user && $user['role'] === 'admin';
}

/**
 * is_dev(): true jika DEV atau ADMIN (admin punya semua hak dev).
 */
function is_dev()
{
    $user = me();
    return $user && in_array($user['role'], ['dev', 'admin'], true);
}


// ---------------------------------------------------------------------
// Pesan singkat (flash message)
// Disimpan di session, ditampilkan satu kali di halaman berikutnya.
// ---------------------------------------------------------------------

/**
 * flash(): menyimpan satu pesan untuk ditampilkan di halaman berikutnya.
 *
 * @param string $message isi pesan
 * @param string $type    'error' (merah, bawaan) atau 'ok' (hijau, untuk pesan berhasil)
 */
function flash($message, $type = 'error')
{
    $_SESSION['flash'][] = ['text' => $message, 'type' => $type];
}

/**
 * flash_pull(): mengambil semua pesan yang tersimpan (tiap pesan berisi
 * 'text' dan 'type') lalu menghapusnya.
 */
function flash_pull()
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}


// ---------------------------------------------------------------------
// Upload file
// ---------------------------------------------------------------------

/**
 * format_size(): ubah byte menjadi teks yang mudah dibaca (mis. 512 MB).
 */
function format_size($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return ($i === 0 ? (int)$bytes : round($bytes, 1)) . ' ' . $units[$i];
}

/**
 * ini_bytes(): ubah nilai php.ini seperti "512M" atau "2G" menjadi byte.
 */
function ini_bytes($value)
{
    $value  = trim((string)$value);
    $number = (float)$value;
    $unit   = strtolower(substr($value, -1));

    if ($unit === 'g') {
        $number *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $number *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $number *= 1024;
    }

    return (int)$number;
}

/**
 * max_upload_bytes(): batas upload yang BENAR-BENAR berlaku.
 * Nilainya yang terkecil antara MAX_UPLOAD (512 MB) dan batas PHP
 * (upload_max_filesize dan post_max_size di php.ini / .htaccess).
 */
function max_upload_bytes()
{
    $limits = [MAX_UPLOAD];

    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $bytes = ini_bytes(ini_get($key));
        if ($bytes > 0) {          // 0 atau -1 berarti tanpa batas
            $limits[] = $bytes;
        }
    }

    return min($limits);
}

/**
 * upload(): simpan file foto/gif/video ke folder uploads.
 *
 * @param array|null $file       isi $_FILES['nama']
 * @param string     $folder     posts | avatars | banners
 * @param bool       $allowVideo false untuk foto profil dan banner (hanya gambar)
 * @return array                 [path, tipe] atau [null, null] jika tidak ada file / gagal.
 *                               Jika gagal, alasannya disimpan lewat flash().
 */
function upload($file, $folder, $allowVideo = true)
{
    // Tidak ada file yang dipilih: bukan error, abaikan saja
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }

    $label = '"' . $file['name'] . '"';

    // Upload ditolak PHP (mis. melebihi upload_max_filesize)
    if ($file['error'] !== UPLOAD_ERR_OK) {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            flash("File $label terlalu besar. Batas upload saat ini " . format_size(max_upload_bytes()) . '.');
        } else {
            flash("Upload $label gagal (kode error {$file['error']}).");
        }
        return [null, null];
    }

    // Lebih dari 512 MB ditolak
    if ($file['size'] > MAX_UPLOAD) {
        flash("File $label lebih dari " . format_size(MAX_UPLOAD) . '.');
        return [null, null];
    }

    // Tentukan jenis file dari ekstensinya
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext === 'gif') {
        $type = 'gif';
    } elseif (in_array($ext, IMAGE_EXT, true)) {
        $type = 'image';
    } elseif ($allowVideo && in_array($ext, VIDEO_EXT, true)) {
        $type = 'video';
    } else {
        flash("Format file $label tidak didukung.");
        return [null, null];
    }

    // Buat folder tujuan jika belum ada (folder kosong tidak ikut terbawa di Git)
    $dir = __DIR__ . '/uploads/' . $folder;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        flash("Folder uploads/$folder tidak bisa dibuat. Periksa izin folder.");
        return [null, null];
    }

    // Nama acak agar tidak bentrok dengan file lain
    $name = bin2hex(random_bytes(8)) . '.' . $ext;

    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
        flash("File $label gagal disimpan. Periksa izin tulis folder uploads/$folder.");
        return [null, null];
    }

    return ["uploads/$folder/$name", $type];
}

/**
 * media_html(): tampilkan video dengan <video>, foto/gif dengan <img>.
 */
function media_html($path, $type)
{
    if (!$path) {
        return '';
    }

    $src = BASE . e($path);

    if ($type === 'video') {
        // preload="metadata": jangan unduh seluruh video (bisa sampai 512 MB) saat halaman dibuka
        return '<video src="' . $src . '" controls preload="metadata" class="media"></video>';
    }

    return '<img src="' . $src . '" class="media" alt="">';
}


// ---------------------------------------------------------------------
// Tampilan
// ---------------------------------------------------------------------

/**
 * icon(): ikon SVG satu warna (putih), tanpa emoji.
 */
function icon($name)
{
    $paths = [
        'home'    => '<path d="M3 11l9-8 9 8v10h-6v-6H9v6H3z"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v5h-5"/>',
        'image'   => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
        'up'      => '<path d="M7 11v10H3V11zM7 11l4-8a2 2 0 0 1 3 2v4h6l-2 10H7"/>',
        'down'    => '<g transform="rotate(180 12 12)"><path d="M7 11v10H3V11zM7 11l4-8a2 2 0 0 1 3 2v4h6l-2 10H7"/></g>',
        'chat'    => '<path d="M21 12a8 8 0 0 1-12 7l-5 1 1-4A8 8 0 1 1 21 12z"/>',
        'trash'   => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"/>',
        'shield'  => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'out'     => '<path d="M9 4H4v16h5M16 8l4 4-4 4M20 12H9"/>',
    ];

    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"'
         . ' stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>';
}

/**
 * avatar(): foto profil, atau ikon user jika belum punya foto.
 */
function avatar($user)
{
    if ($user['avatar']) {
        return '<img class="av" src="' . BASE . e($user['avatar']) . '" alt="">';
    }

    return '<span class="av">' . icon('user') . '</span>';
}

/**
 * linkify(): ubah URL (http/https) di teks menjadi link yang bisa diklik.
 * Teks di-escape lebih dulu sehingga tetap aman dari XSS.
 */
function linkify($text)
{
    return preg_replace(
        '~(https?://[^\s<]+)~i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        nl2br(e($text))
    );
}
