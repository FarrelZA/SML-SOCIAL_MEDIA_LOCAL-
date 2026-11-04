<?php
require 'config.php'; need_login();
if (!is_dev()) { http_response_code(403); exit('Akses ditolak: halaman ini khusus DEV.'); } // penjaga: USER biasa ditolak di server
require 'includes/top.php';
// Ambil semua akun + jumlah post dan komentar masing-masing
$rows = $pdo->query("SELECT u.*,
  (SELECT COUNT(*) FROM posts p WHERE p.user_id=u.id) posts,
  (SELECT COUNT(*) FROM comments c WHERE c.user_id=u.id) comments
  FROM users u ORDER BY u.role, u.id DESC")->fetchAll();
?>
<!-- Satu form untuk seluruh tabel: centang user lalu klik "Hapus terpilih" -->
<form method="post" action="actions.php" onsubmit="return confirm('Hapus user terpilih beserta semua postingannya?')">
  <input type="hidden" name="a" value="del_users">
  <div class="dp-head"><div><b>Dev Panel</b><br><small><?= count($rows) ?> akun terdaftar</small></div>
    <button class="btn danger">Hapus terpilih</button></div>
  <div class="dp-wrap"><table class="dp">
    <tr><th><input type="checkbox" title="Pilih semua" onclick="document.querySelectorAll('.pick').forEach(c=>c.checked=this.checked)"></th>
        <th>Pengguna</th><th>Role</th><th>Post</th><th>Komentar</th><th>Daftar</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?php if ($r['role'] === 'user'): // akun DEV tidak punya kotak centang ?>
        <input type="checkbox" class="pick" name="ids[]" value="<?= $r['id'] ?>"><?php endif; ?></td>
      <td><a class="row" href="<?= BASE ?>profile.php?id=<?= $r['id'] ?>"><?= avatar($r) ?><div><b><?= e($r['display_name']) ?></b><small>@<?= e($r['username']) ?></small></div></a></td>
      <td><?= e($r['role']) ?></td><td><?= $r['posts'] ?></td><td><?= $r['comments'] ?></td><td><small><?= e(substr($r['created_at'], 0, 10)) ?></small></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
</form>
<?php require 'includes/bottom.php'; ?>
