<?php
require_once __DIR__ . '/../config.php';
$u = me();

// Halaman yang sedang dibuka, untuk menandai menu aktif (aria-current)
$page = basename($_SERVER['SCRIPT_NAME']);
$onHome    = ($page === 'index.php');
$onProfile = ($page === 'profile.php' && (int)($_GET['id'] ?? $u['id']) === (int)$u['id']);
$onDev     = ($page === 'dev.php');
$current   = ' aria-current="page"';

// Sidik jari data saat halaman dibuat (dibandingkan oleh assets/live.js lewat poll.php).
// Dihitung SEBELUM query halaman: jika data berubah di antaranya, hasilnya hanya
// satu kali refresh tambahan yang tidak berbahaya.
$sig = data_signature();

// Permintaan dari live.js (header X-Live) tidak boleh mengambil pesan flash,
// supaya pesan itu tetap utuh untuk halaman yang benar-benar dibuka pengguna.
$isLive = isset($_SERVER['HTTP_X_LIVE']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <!-- wajib agar tampil pas di HP -->
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0b1220">
    <title>SML</title>
    <link rel="stylesheet" href="<?= BASE ?>assets/style.css">
    <link rel="icon" type="image/png" href="<?= BASE ?>assets/favicon.png">
    <!-- app.js: preview foto/GIF/video sebelum dikirim -->
    <script src="<?= BASE ?>assets/app.js" defer></script>
    <!-- ui.js: transisi halaman, tombol loading, pesan hilang sendiri, hanya 1 video diputar -->
    <script src="<?= BASE ?>assets/ui.js" defer></script>
    <!-- live.js: auto refresh saat ada data baru di database -->
    <script src="<?= BASE ?>assets/live.js" defer></script>
</head>
<!-- data-max-upload: batas upload yang berlaku di server (byte), dibaca oleh app.js
     data-sig / data-poll / data-login: dibaca oleh live.js untuk auto refresh -->
<body data-max-upload="<?= max_upload_bytes() ?>"
      data-sig="<?= e($sig) ?>"
      data-poll="<?= BASE ?>poll.php"
      data-login="<?= BASE ?>login.php">
<div class="app">

    <!-- Kolom kiri (desktop): menu navigasi -->
    <aside class="left">
        <div>
            <div class="logo"><img src="<?= BASE ?>assets/logo.png" alt="SML"></div>
            <nav data-live="nav-left" aria-label="Menu utama">
                <a href="<?= BASE ?>index.php"<?= $onHome ? $current : '' ?>><?= icon('home') ?>Home</a>
                <a href="<?= BASE ?>profile.php"<?= $onProfile ? $current : '' ?>><?= icon('user') ?>Profile</a>
                <a href="javascript:location.reload()"><?= icon('refresh') ?>Refresh</a>
                <?php if (is_dev()): ?>
                    <a href="<?= BASE ?>dev.php"<?= $onDev ? $current : '' ?>><?= icon('shield') ?>Dev Panel</a>
                <?php endif; ?>
                <a href="<?= BASE ?>logout.php"><?= icon('out') ?>Logout</a>
            </nav>
            <a class="btn big" href="<?= BASE ?>index.php#composer">Post</a>
        </div>
        <div class="me" data-live="me">
            <?= avatar($u) ?>
            <div>
                <b><?= e($u['display_name']) ?></b>
                <small>@<?= e($u['username']) ?> <?= role_badge($u['role'], true) ?></small>
            </div>
        </div>
    </aside>

    <!-- Kolom tengah: isi halaman -->
    <main class="center enter">
        <header class="top">
            <h1><?= e($title ?? 'Home') ?></h1>
            <div class="top-tools">
                <!-- Indikator auto refresh: hijau berdenyut = tersambung, abu-abu = terputus (diatur live.js) -->
                <span class="live" data-state="on" role="status" title="Auto refresh aktif">
                    <i aria-hidden="true"></i><span>Live</span>
                </span>
                <!-- tombol refresh laman -->
                <a href="javascript:location.reload()" title="Refresh" aria-label="Refresh halaman"><?= icon('refresh') ?></a>
            </div>
        </header>
        <?php
        // Pesan singkat dari halaman sebelumnya: error (merah) atau berhasil (hijau)
        $messages = $isLive ? [] : flash_pull();
        foreach ($messages as $flash): ?>
            <div class="flash<?= $flash['type'] === 'ok' ? ' ok' : '' ?>"
                 role="<?= $flash['type'] === 'ok' ? 'status' : 'alert' ?>"><?= e($flash['text']) ?></div>
        <?php endforeach; ?>

