<?php
require 'config.php'; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $un = strtolower(trim($_POST['username'])); $dn = trim($_POST['display_name']);
  if (!preg_match('/^[a-z0-9_]{3,30}$/', $un)) $err = 'Username 3-30 karakter: huruf, angka, underscore.';
  elseif (strlen($_POST['password']) < 6) $err = 'Password minimal 6 karakter.';
  else {
    $c = $pdo->prepare('SELECT 1 FROM users WHERE username=?'); $c->execute([$un]);
    if ($c->fetch()) $err = 'Username sudah dipakai.';
    else {
      $role = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() == 0 ? 'admin' : 'user'; // akun PERTAMA otomatis ADMIN
      $pdo->prepare('INSERT INTO users(username,display_name,password,role) VALUES(?,?,?,?)')
          ->execute([$un, $dn ?: $un, password_hash($_POST['password'], PASSWORD_DEFAULT), $role]); // password di-hash
      $_SESSION['uid'] = $pdo->lastInsertId(); header('Location: ' . BASE . 'index.php'); exit;
    }
  }
}
?><!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daftar - SML</title><link rel="stylesheet" href="<?= BASE ?>assets/style.css"><link rel="icon" type="image/png" href="<?= BASE ?>assets/favicon.png"></head><body class="auth">
<form class="card" method="post"><div class="logo"><img src="<?= BASE ?>assets/logo.png" alt="SML"></div><h2>Buat akun</h2>
<?php if ($err) echo '<p class="err">' . e($err) . '</p>'; ?>
<input name="username" placeholder="Username" required><input name="display_name" placeholder="Nama tampilan">
<input type="password" name="password" placeholder="Password (min. 6)" required>
<button class="btn">Daftar</button><p><a href="login.php">Sudah punya akun? Masuk</a></p></form></body></html>
