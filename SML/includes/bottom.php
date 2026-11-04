  </main>
  <!-- Kolom kanan (desktop): daftar pengguna -->
  <aside class="right">
    <div class="card"><h3>Pengguna</h3>
      <?php foreach ($pdo->query('SELECT id,username,display_name,avatar FROM users ' . (is_dev() ? '' : "WHERE role='user' ") . 'ORDER BY id DESC LIMIT 6') as $x): ?>
        <a class="row" href="<?= BASE ?>profile.php?id=<?= $x['id'] ?>"><?= avatar($x) ?><div><b><?= e($x['display_name']) ?></b><small>@<?= e($x['username']) ?></small></div></a>
      <?php endforeach; ?>
    </div>
  </aside>
</div>
<!-- Navigasi bawah (khusus HP) -->
<nav class="bottom">
  <a href="<?= BASE ?>index.php"><?= icon('home') ?></a>
  <a href="javascript:location.reload()"><?= icon('refresh') ?></a>
  <a href="<?= BASE ?>profile.php"><?= icon('user') ?></a>
  <?php if (is_dev()): ?><a href="<?= BASE ?>dev.php"><?= icon('shield') ?></a><?php endif; ?>
  <a href="<?= BASE ?>logout.php"><?= icon('out') ?></a>
</nav>
</body></html>
