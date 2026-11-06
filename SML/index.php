<?php
/**
 * index.php
 *
 * Beranda: form membuat post + 50 postingan terbaru.
 */

require 'config.php';
need_login();                       // halaman ini wajib login
$title = 'Home';                    // judul di header halaman
require 'includes/top.php';
require 'includes/post.php';
?>

<!-- Form membuat postingan: teks/link + foto/gif/video.
     enctype="multipart/form-data" wajib untuk upload file. -->
<form id="composer" class="composer" method="post" action="actions.php" enctype="multipart/form-data">
    <?= avatar($u) ?>
    <div class="grow">
        <input type="hidden" name="a" value="post">
        <textarea name="body" placeholder="What's happening? (boleh tempel link)"></textarea>

        <!-- Preview media yang dipilih (diisi oleh assets/app.js) -->
        <div class="preview" data-for="media" hidden></div>

        <div class="bar">
            <label class="plain">
                <?= icon('image') ?> Foto / GIF / Video
                <input type="file" name="media" accept="<?= ACCEPT_MEDIA ?>" data-preview hidden>
            </label>
            <button class="btn">Post</button>
        </div>
    </div>
</form>

<?php
// Ambil 50 post terbaru + jumlah like, dislike, dan reaksi user yang sedang login
$stmt = $pdo->prepare(
    'SELECT p.*, u.username, u.display_name, u.avatar, u.role,
        (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id AND r.type = 1)  AS likes,
        (SELECT COUNT(*) FROM reactions r WHERE r.post_id = p.id AND r.type = -1) AS dislikes,
        (SELECT type FROM reactions r WHERE r.post_id = p.id AND r.user_id = ?)   AS mine
     FROM posts p
     JOIN users u ON u.id = p.user_id
     ORDER BY p.id DESC
     LIMIT 50'
);
$stmt->execute([$u['id']]);
$posts = $stmt->fetchAll();

// #feed: daftar postingan yang diperbarui otomatis oleh assets/live.js
echo '<div id="feed">';

foreach ($posts as $post) {
    render_post($post);
}

if (!$posts) {
    echo '<p class="empty">Belum ada postingan. Tulis yang pertama.</p>';
}

echo '</div>';

require 'includes/bottom.php';
