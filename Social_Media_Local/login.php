<?php
require 'config.php'; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $s = $pdo->prepare('SELECT * FROM users WHERE username=?'); $s->execute([trim($_POST['username'])]); $x = $s->fetch();
  if ($x && password_verify($_POST['password'], $x['password'])) { $_SESSION['uid'] = $x['id']; header('Location: ' . BASE . 'index.php'); exit; }
  $err = 'Username atau password salah.';
}
?><!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - Social_Media_Local</title><link rel="stylesheet" href="<?= BASE ?>assets/style.css"></head><body class="auth">
<form class="card" method="post"><div class="logo"><?= icon('chat') ?></div><h2>Masuk</h2>
<?php if ($err) echo '<p class="err">' . e($err) . '</p>'; ?>
<input name="username" placeholder="Username" required><input type="password" name="password" placeholder="Password" required>
<button class="btn">Masuk</button><p><a href="register.php">Buat akun baru</a></p></form></body></html>
