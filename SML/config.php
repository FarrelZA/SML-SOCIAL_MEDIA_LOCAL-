<?php
session_start(); // memulai session supaya login tetap tersimpan antar halaman

// Koneksi ke MySQL XAMPP (user default: root, password kosong)
$pdo = new PDO('mysql:host=localhost;dbname=sml;charset=utf8mb4', 'root', '', [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // tampilkan error SQL
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // hasil query berupa array asosiatif
]);

define('BASE', '/SML/'); // alamat dasar web di localhost

// e(): membersihkan teks agar kode HTML/JS berbahaya tidak dieksekusi (anti-XSS)
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// me(): mengambil data user yang sedang login (null jika belum login)
function me() {
  global $pdo; static $u = null;
  if (!$u && isset($_SESSION['uid'])) {
    $s = $pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$_SESSION['uid']]); $u = $s->fetch();
  }
  return $u;
}
// need_login(): paksa pindah ke halaman login jika belum login
function need_login() { if (!me()) { header('Location: ' . BASE . 'login.php'); exit; } }
// is_dev(): true jika user yang login berole DEV
// is_admin(): true jika user berole ADMIN
function is_admin() { $u = me(); return $u && $u['role'] === 'admin'; }
// is_dev(): true jika DEV atau ADMIN (admin punya semua hak dev)
function is_dev() { $u = me(); return $u && in_array($u['role'], ['dev', 'admin'], true); }

define('MAX_UPLOAD', 512 * 1024 * 1024); // batas upload 512MB

// linkify(): ubah URL (http/https) di teks menjadi link yang bisa diklik
function linkify($t) {
  return preg_replace('~(https?://[^\s<]+)~i', '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', nl2br(e($t)));
}

// upload(): simpan file foto/gif/video ke folder uploads, kembalikan [path, tipe]
function upload($f, $folder) {
  if (!$f || $f['error'] !== UPLOAD_ERR_OK) return [null, null];   // tidak ada file / gagal
  if ($f['size'] > MAX_UPLOAD) return [null, null];                 // lebih dari 512MB ditolak
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));      // ambil ekstensi file
  if ($ext === 'gif') $type = 'gif';
  elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) $type = 'image';
  elseif (in_array($ext, ['mp4', 'webm'])) $type = 'video';
  else return [null, null];                                         // format lain ditolak
  $name = bin2hex(random_bytes(8)) . '.' . $ext;                    // nama acak agar tidak bentrok
  move_uploaded_file($f['tmp_name'], __DIR__ . "/uploads/$folder/$name");
  return ["uploads/$folder/$name", $type];
}

// media_html(): tampilkan video dengan <video>, foto/gif dengan <img>
function media_html($path, $type) {
  if (!$path) return '';
  $p = BASE . e($path);
  return $type === 'video' ? "<video src=\"$p\" controls class=\"media\"></video>" : "<img src=\"$p\" class=\"media\" alt=\"\">";
}

// icon(): ikon SVG satu warna (putih), tanpa emoji
function icon($n) {
  $p = [
    'home' => '<path d="M3 11l9-8 9 8v10h-6v-6H9v6H3z"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
    'refresh' => '<path d="M20 12a8 8 0 1 1-2.3-5.7M20 4v5h-5"/>',
    'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
    'up' => '<path d="M7 11v10H3V11zM7 11l4-8a2 2 0 0 1 3 2v4h6l-2 10H7"/>',
    'down' => '<g transform="rotate(180 12 12)"><path d="M7 11v10H3V11zM7 11l4-8a2 2 0 0 1 3 2v4h6l-2 10H7"/></g>',
    'chat' => '<path d="M21 12a8 8 0 0 1-12 7l-5 1 1-4A8 8 0 1 1 21 12z"/>',
    'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"/>',
    'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
    'out' => '<path d="M9 4H4v16h5M16 8l4 4-4 4M20 12H9"/>',
  ];
  return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p[$n] . '</svg>';
}

// avatar(): foto profil, atau ikon user jika belum punya foto
function avatar($u) {
  return $u['avatar'] ? '<img class="av" src="' . BASE . e($u['avatar']) . '" alt="">' : '<span class="av">' . icon('user') . '</span>';
}
