<?php
/**
 * register.php
 *
 * Halaman daftar akun. Akun PERTAMA yang terdaftar otomatis menjadi ADMIN,
 * akun berikutnya menjadi USER. Password disimpan dalam bentuk hash.
 */

require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = strtolower(trim($_POST['username'] ?? ''));
    $displayName = trim($_POST['display_name'] ?? '');
    $password    = $_POST['password'] ?? '';

    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        $error = 'Username 3-30 karakter: huruf, angka, underscore.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        // Pastikan username belum dipakai
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = ?');
        $stmt->execute([$username]);

        if ($stmt->fetch()) {
            $error = 'Username sudah dipakai.';
        } else {
            // Akun PERTAMA otomatis ADMIN
            $isFirst = ($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() == 0);
            $role    = $isFirst ? 'admin' : 'user';

            $stmt = $pdo->prepare('INSERT INTO users (username, display_name, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                $username,
                $displayName ?: $username,                    // nama tampilan kosong = pakai username
                password_hash($password, PASSWORD_DEFAULT),   // password di-hash
                $role,
            ]);

            // Langsung masuk setelah daftar
            $_SESSION['uid'] = $pdo->lastInsertId();
            header('Location: ' . BASE . 'index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Daftar - SML</title>
    <link rel="stylesheet" href="<?= BASE ?>assets/style.css">
    <link rel="icon" type="image/png" href="<?= BASE ?>assets/favicon.png">
    <script src="<?= BASE ?>assets/ui.js" defer></script>
</head>
<body class="auth">
    <form class="card" method="post">
        <div class="logo"><img src="<?= BASE ?>assets/logo.png" alt="SML"></div>
        <h2>Buat akun</h2>

        <?php if ($error): ?>
            <p class="err"><?= e($error) ?></p>
        <?php endif; ?>

        <input name="username" placeholder="Username" required>
        <input name="display_name" placeholder="Nama tampilan">
        <input type="password" name="password" placeholder="Password (min. 6)" required>
        <button class="btn">Daftar</button>
        <p><a href="login.php">Sudah punya akun? Masuk</a></p>
    </form>
</body>
</html>
