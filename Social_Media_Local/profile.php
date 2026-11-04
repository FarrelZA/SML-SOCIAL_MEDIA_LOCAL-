<?php
require 'config.php'; need_login();
require 'includes/top.php'; require 'includes/post.php';
$id = (int)($_GET['id'] ?? $u['id']);                       // tanpa ?id= berarti profil sendiri
$s = $pdo->prepare('SELECT * FROM users WHERE id=?'); $s->execute([$id]); $x = $s->fetch();
// Profil DEV disembunyikan dari USER lain (kecuali profil sendiri)
if (!$x || ($x['role'] === 'dev' && !is_dev() && $x['id'] != $u['id'])) { echo '<p class="empty">Pengguna tidak ditemukan.</p>'; require 'includes/bottom.php'; exit; }
$own = $x['id'] == $u['id'];
?>
<div class="banner"><?php if ($x['banner']) echo '<img src="' . BASE . e($x['banner']) . '" alt="">'; ?></div>
<div class="pinfo"><?= avatar($x) ?>
  <h2><?= e($x['display_name']) ?></h2><small>@<?= e($x['username']) ?> &middot; <?= e($x['role']) ?></small>
  <p><?= linkify($x['bio']) ?></p>
  <?php if (!$own && is_dev()): // tombol khusus DEV: hapus user lain ?>
  <form method="post" action="actions.php" onsubmit="return confirm('Hapus user ini beserta semua postingannya?')">
    <input type="hidden" name="a" value="del_user"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="btn danger">Hapus user</button></form>
  <?php endif; ?>
</div>
<?php if ($own): // form edit profil & hapus akun hanya muncul di profil sendiri ?>
<details class="cm"><summary>Edit profil</summary>
  <form class="edit" method="post" action="actions.php" enctype="multipart/form-data">
    <input type="hidden" name="a" value="profile">
    <input name="display_name" value="<?= e($x['display_name']) ?>" maxlength="50">
    <input name="bio" value="<?= e($x['bio']) ?>" maxlength="160" placeholder="Bio">
    <label>Foto profil (foto/GIF)<input type="file" name="avatar" accept="image/*"></label>
    <label>Banner (foto/GIF)<input type="file" name="banner" accept="image/*"></label>
    <button class="btn">Simpan</button></form>
  <form method="post" action="actions.php" onsubmit="return confirm('Hapus akun PERMANEN?')">
    <input type="hidden" name="a" value="del_user"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn danger">Hapus akun saya</button></form>
</details>
<?php endif;
$s = $pdo->prepare('SELECT p.*,u.username,u.display_name,u.avatar,
  (SELECT COUNT(*) FROM reactions r WHERE r.post_id=p.id AND r.type=1) likes,
  (SELECT COUNT(*) FROM reactions r WHERE r.post_id=p.id AND r.type=-1) dislikes,
  (SELECT type FROM reactions r WHERE r.post_id=p.id AND r.user_id=?) mine
  FROM posts p JOIN users u ON u.id=p.user_id WHERE p.user_id=? ORDER BY p.id DESC');
$s->execute([$u['id'], $x['id']]);
foreach ($s->fetchAll() as $p) render_post($p);
require 'includes/bottom.php';
