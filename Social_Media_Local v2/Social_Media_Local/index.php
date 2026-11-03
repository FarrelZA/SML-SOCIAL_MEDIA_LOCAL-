<?php
require 'config.php'; need_login();                   // halaman ini wajib login
require 'includes/top.php'; require 'includes/post.php';
?>
<!-- Form membuat postingan: teks/link + foto/gif/video. enctype wajib untuk upload file -->
<form id="composer" class="composer" method="post" action="actions.php" enctype="multipart/form-data">
  <?= avatar($u) ?>
  <div class="grow">
    <input type="hidden" name="a" value="post">
    <textarea name="body" placeholder="What's happening? (boleh tempel link)"></textarea>
    <div class="bar">
      <label class="plain"><?= icon('image') ?> Foto / GIF / Video<input type="file" name="media" accept="image/*,video/mp4,video/webm" hidden></label>
      <button class="btn">Post</button>
    </div>
  </div>
</form>
<?php
// Ambil 50 post terbaru + jumlah like, dislike, dan reaksi user yang sedang login
$s = $pdo->prepare('SELECT p.*,u.username,u.display_name,u.avatar,
  (SELECT COUNT(*) FROM reactions r WHERE r.post_id=p.id AND r.type=1) likes,
  (SELECT COUNT(*) FROM reactions r WHERE r.post_id=p.id AND r.type=-1) dislikes,
  (SELECT type FROM reactions r WHERE r.post_id=p.id AND r.user_id=?) mine
  FROM posts p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 50');
$s->execute([$u['id']]);
$rows = $s->fetchAll();
foreach ($rows as $p) render_post($p);
if (!$rows) echo '<p class="empty">Belum ada postingan. Tulis yang pertama.</p>';
require 'includes/bottom.php';
