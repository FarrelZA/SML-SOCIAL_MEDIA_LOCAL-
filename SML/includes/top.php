<?php require_once __DIR__ . '/../config.php'; $u = me(); ?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"> <!-- wajib agar tampil pas di HP -->
<title>SML</title>
<link rel="stylesheet" href="<?= BASE ?>assets/style.css">
<link rel="icon" type="image/png" href="<?= BASE ?>assets/favicon.png"></head><body>
<div class="app">
  <!-- Kolom kiri (desktop): menu navigasi -->
  <aside class="left">
    <div>
      <div class="logo"><img src="<?= BASE ?>assets/logo.png" alt="SML"></div>
      <nav>
        <a href="<?= BASE ?>index.php"><?= icon('home') ?>Home</a>
        <a href="<?= BASE ?>profile.php"><?= icon('user') ?>Profile</a>
        <a href="javascript:location.reload()"><?= icon('refresh') ?>Refresh</a>
        <?php if (is_dev()): ?><a href="<?= BASE ?>dev.php"><?= icon('shield') ?>Dev Panel</a><?php endif; ?>
        <a href="<?= BASE ?>logout.php"><?= icon('out') ?>Logout</a>
      </nav>
      <a class="btn big" href="<?= BASE ?>index.php#composer">Post</a>
    </div>
    <div class="me"><?= avatar($u) ?><div><b><?= e($u['display_name']) ?></b><small>@<?= e($u['username']) ?> (<?= e($u['role']) ?>)</small></div></div>
  </aside>
  <!-- Kolom tengah: isi halaman -->
  <main class="center">
    <header class="top"><b><?= e($title ?? 'Home') ?></b>
      <a href="javascript:location.reload()" title="Refresh"><?= icon('refresh') ?></a> <!-- tombol refresh laman -->
    </header>
