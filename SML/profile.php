<?php
/**
 * profile.php
 *
 * Halaman profil. Tanpa ?id= berarti profil sendiri.
 * Di profil sendiri muncul form edit profil dan hapus akun.
 */

require 'config.php';
need_login();
require 'includes/top.php';
require 'includes/post.php';

$id = (int)($_GET['id'] ?? $u['id']);     // tanpa ?id= berarti profil sendiri

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$x = $stmt->fetch();

// Semua role (ADMIN, DEV, USER) bisa melihat profil siapa pun.
// Hanya id yang tidak ada di database yang dianggap tidak ditemukan.
if (!$x) {
    echo '<p class="empty">Pengguna tidak ditemukan.</p>';
    require 'includes/bottom.php';
    exit;
}

$own = ($x['id'] == $u['id']);
?>

<div class="banner">
    <?php if ($x['banner']): ?>
        <img src="<?= BASE . e($x['banner']) ?>" alt="">
    <?php endif; ?>
</div>

<div class="pinfo">
    <?= avatar($x) ?>
    <h2><?= e($x['display_name']) ?></h2>
    <small>@<?= e($x['username']) ?> &middot; <?= e($x['role']) ?></small>
    <p><?= linkify($x['bio']) ?></p>

    <?php // ADMIN: hapus siapa pun, DEV: hanya hapus akun user ?>
    <?php if (!$own && (is_admin() || (is_dev() && $x['role'] === 'user'))): ?>
        <form method="post" action="actions.php"
              onsubmit="return confirm('Hapus user ini beserta semua postingannya?')">
            <input type="hidden" name="a" value="del_user">
            <input type="hidden" name="id" value="<?= $x['id'] ?>">
            <button class="btn danger">Hapus user</button>
        </form>
    <?php endif; ?>
</div>

<?php // Form edit profil dan hapus akun hanya muncul di profil sendiri ?>
<?php if ($own): ?>
    <details class="cm">
        <summary>Edit profil</summary>

        <form class="edit" method="post" action="actions.php" enctype="multipart/form-data">
            <input type="hidden" name="a" value="profile">
            <input name="display_name" value="<?= e($x['display_name']) ?>" maxlength="50">
            <input name="bio" value="<?= e($x['bio']) ?>" maxlength="160" placeholder="Bio">

            <label>
                Foto profil (foto/GIF)
                <input type="file" name="avatar" accept="<?= ACCEPT_IMAGE ?>" data-preview data-kind="image">
            </label>
            <div class="preview" data-for="avatar" hidden></div>

            <label>
                Banner (foto/GIF)
                <input type="file" name="banner" accept="<?= ACCEPT_IMAGE ?>" data-preview data-kind="image">
            </label>
            <div class="preview" data-for="banner" hidden></div>

            <button class="btn">Simpan</button>
        </form>

        <form class="edit" method="post" action="actions.php" autocomplete="off">
            <input type="hidden" name="a" value="change_password">
            <h3>Ganti password</h3>
            <input type="password" name="current_password" placeholder="Password saat ini" required autocomplete="current-password">
            <input type="password" name="new_password" placeholder="Password baru (minimal 6 karakter)" minlength="6" required autocomplete="new-password">
            <input type="password" name="confirm_password" placeholder="Ulangi password baru" minlength="6" required autocomplete="new-password">
            <button class="btn">Ganti password</button>
        </form>

        <form method="post" action="actions.php" onsubmit="return confirm('Hapus akun PERMANEN?')">
            <input type="hidden" name="a" value="del_user">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button class="btn danger">Hapus akun saya</button>
        </form>
    </details>
<?php endif; ?>

<?php
// Ambil semua post milik user ini + jumlah like, dislike, dan reaksi saya
$stmt = $pdo->prepare(
    'SELECT p.*, u.username, u.display_name, u.avatar,
        (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id AND r.type = 1)  AS likes,
        (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id AND r.type = -1) AS dislikes,
        (SELECT type FROM reactions r WHERE r.post_id = p.id AND r.user_id = ?)   AS mine
     FROM posts p
     JOIN users u ON u.id = p.user_id
     WHERE p.user_id = ?
     ORDER BY p.id DESC'
);
$stmt->execute([$u['id'], $x['id']]);

foreach ($stmt->fetchAll() as $post) {
    render_post($post);
}

require 'includes/bottom.php';
