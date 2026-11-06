<?php
/**
 * login.php
 *
 * Halaman masuk. Password dicek dengan password_verify() terhadap hash di database.
 */

require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $found = $stmt->fetch();

    if ($found && password_verify($_POST['password'] ?? '', $found['password'])) {
        // Login berhasil: simpan id user di session
        $_SESSION['uid'] = $found['id'];
        header('Location: ' . BASE . 'index.php');
        exit;
    }

    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - SML</title>
    <link rel="stylesheet" href="<?= BASE ?>assets/style.css">
    <link rel="icon" type="image/png" href="<?= BASE ?>assets/favicon.png">
    <script src="<?= BASE ?>assets/ui.js" defer></script>
</head>
<body class="auth">
    <form class="card" method="post">
        <div class="logo"><img src="<?= BASE ?>assets/logo.png" alt="SML"></div>
        <h2>Masuk</h2>

        <?php if ($error): ?>
            <p class="err"><?= e($error) ?></p>
        <?php endif; ?>

        <input name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button class="btn">Masuk</button>
        <p><a href="register.php">Buat akun baru</a></p>
    </form>
</body>
</html>
