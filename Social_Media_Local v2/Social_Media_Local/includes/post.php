<?php
// render_post(): menampilkan satu postingan + tombol aksi + komentar
function render_post($p) {
  global $pdo; $m = me();
  $q = $pdo->prepare('SELECT c.*,u.username,u.display_name,u.avatar FROM comments c JOIN users u ON u.id=c.user_id WHERE c.post_id=? ORDER BY c.id');
  $q->execute([$p['id']]); $cs = $q->fetchAll();
  $mine = (int)$p['mine'];                                // reaksi saya: 1 like, -1 dislike, 0 belum
  $canDel = is_dev() || $p['user_id'] == $m['id'];        // DEV boleh hapus semua, user hanya miliknya
  $act = BASE . 'actions.php'; ?>
  <article class="post">
    <a href="<?= BASE ?>profile.php?id=<?= $p['user_id'] ?>"><?= avatar($p) ?></a>
    <div class="pb">
      <div class="meta"><b><?= e($p['display_name']) ?></b> <small>@<?= e($p['username']) ?> &middot; <?= e($p['created_at']) ?></small></div>
      <div class="txt"><?= linkify($p['body']) ?></div>
      <?= media_html($p['media'], $p['media_type']) ?>
      <div class="acts">
        <form method="post" action="<?= $act ?>"><input type="hidden" name="a" value="react"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button name="v" value="1" class="<?= $mine === 1 ? 'on' : '' ?>"><?= icon('up') ?><?= $p['likes'] ?></button>
          <button name="v" value="-1" class="<?= $mine === -1 ? 'on' : '' ?>"><?= icon('down') ?><?= $p['dislikes'] ?></button>
        </form>
        <?php if ($canDel): ?>
        <form method="post" action="<?= $act ?>" onsubmit="return confirm('Hapus postingan ini?')"><input type="hidden" name="a" value="del_post"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button><?= icon('trash') ?></button></form>
        <?php endif; ?>
      </div>
      <details class="cm"><summary><?= icon('chat') ?> <?= count($cs) ?> komentar</summary>
        <?php foreach ($cs as $c): ?>
        <div class="post small"><?= avatar($c) ?><div class="pb">
          <div class="meta"><b><?= e($c['display_name']) ?></b> <small>@<?= e($c['username']) ?></small></div>
          <div class="txt"><?= linkify($c['body']) ?></div><?= media_html($c['media'], $c['media_type']) ?>
          <?php if (is_dev() || $c['user_id'] == $m['id']): ?>
          <form method="post" action="<?= $act ?>"><input type="hidden" name="a" value="del_comment"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="plain"><?= icon('trash') ?></button></form>
          <?php endif; ?></div></div>
        <?php endforeach; ?>
        <form class="cf" method="post" action="<?= $act ?>" enctype="multipart/form-data">
          <input type="hidden" name="a" value="comment"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <input name="body" placeholder="Tulis komentar / link"><label class="plain"><?= icon('image') ?><input type="file" name="media" accept="image/*,video/mp4,video/webm" hidden></label>
          <button class="btn">Kirim</button>
        </form>
      </details>
    </div>
  </article>
<?php } ?>
