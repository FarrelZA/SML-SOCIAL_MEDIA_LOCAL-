<?php
require 'config.php'; need_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('Metode tidak valid'); // hanya terima POST
$u = me(); $a = $_POST['a'] ?? ''; $id = (int)($_POST['id'] ?? 0);
$back = $_SERVER['HTTP_REFERER'] ?? BASE . 'index.php';              // kembali ke halaman asal
$dev = is_dev() ? 1 : 0;

switch ($a) {
  case 'post': // membuat postingan
    [$m, $t] = upload($_FILES['media'] ?? null, 'posts'); $body = trim($_POST['body'] ?? '');
    if ($body !== '' || $m) $pdo->prepare('INSERT INTO posts(user_id,body,media,media_type) VALUES(?,?,?,?)')->execute([$u['id'], $body, $m, $t]);
    break;

  case 'del_post': // hapus post: pemilik ATAU DEV
    $pdo->prepare('DELETE FROM posts WHERE id=? AND (user_id=? OR ?=1)')->execute([$id, $u['id'], $dev]);
    break;

  case 'react': // like / dislike (klik lagi = batal, klik yang lain = ganti)
    $v = ($_POST['v'] ?? '') === '1' ? 1 : -1;
    $c = $pdo->prepare('SELECT type FROM reactions WHERE user_id=? AND post_id=?'); $c->execute([$u['id'], $id]); $old = $c->fetchColumn();
    if ($old === false) $pdo->prepare('INSERT INTO reactions VALUES(?,?,?)')->execute([$u['id'], $id, $v]);
    elseif ((int)$old === $v) $pdo->prepare('DELETE FROM reactions WHERE user_id=? AND post_id=?')->execute([$u['id'], $id]);
    else $pdo->prepare('UPDATE reactions SET type=? WHERE user_id=? AND post_id=?')->execute([$v, $u['id'], $id]);
    break;

  case 'comment': // komentar teks/link + foto/gif/video
    [$m, $t] = upload($_FILES['media'] ?? null, 'posts'); $body = trim($_POST['body'] ?? '');
    if ($body !== '' || $m) $pdo->prepare('INSERT INTO comments(post_id,user_id,body,media,media_type) VALUES(?,?,?,?,?)')->execute([$id, $u['id'], $body, $m, $t]);
    break;

  case 'del_comment': // hapus komentar: pemilik ATAU DEV
    $pdo->prepare('DELETE FROM comments WHERE id=? AND (user_id=? OR ?=1)')->execute([$id, $u['id'], $dev]);
    break;

  case 'profile': // edit profil SENDIRI saja (id diambil dari session, bukan dari form)
    [$av] = upload($_FILES['avatar'] ?? null, 'avatars'); [$bn] = upload($_FILES['banner'] ?? null, 'banners');
    $pdo->prepare('UPDATE users SET display_name=?, bio=?, avatar=COALESCE(?,avatar), banner=COALESCE(?,banner) WHERE id=?')
        ->execute([trim($_POST['display_name']) ?: $u['display_name'], trim($_POST['bio'] ?? ''), $av, $bn, $u['id']]);
    break;

  case 'del_users': // Dev Panel: hapus beberapa user terpilih sekaligus (khusus DEV)
    if ($dev && !empty($_POST['ids']) && is_array($_POST['ids'])) {
      $ids = array_values(array_filter(array_map('intval', $_POST['ids']), fn($i) => $i !== (int)$u['id'])); // akun sendiri tidak ikut terhapus
      if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));            // buat placeholder ?,?,? sesuai jumlah id
        $pdo->prepare("DELETE FROM users WHERE id IN ($in)")->execute($ids); // post/komentar ikut terhapus (CASCADE)
      }
    }
    $back = BASE . 'dev.php';
    break;

  case 'del_users': // hapus beberapa user terpilih sekaligus: HANYA DEV
    if ($dev && !empty($_POST['ids'])) {
      $ids = array_map('intval', (array)$_POST['ids']);              // paksa semua ID jadi angka
      $in = implode(',', array_fill(0, count($ids), '?'));           // buat ?,?,? sesuai jumlah ID
      $pdo->prepare("DELETE FROM users WHERE role='user' AND id IN ($in)")->execute($ids); // akun DEV tidak ikut terhapus
    }
    $back = BASE . 'dev.php';
    break;

  case 'del_user': // hapus akun: diri sendiri ATAU (DEV: siapa pun)
    if ($id === (int)$u['id'] || $dev) {
      $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);  // post/komentar ikut terhapus (CASCADE)
      if ($id === (int)$u['id']) { session_destroy(); header('Location: ' . BASE . 'login.php'); exit; }
      $back = BASE . 'index.php';
    }
    break;
}
header('Location: ' . $back); // selesai, kembali ke halaman asal
