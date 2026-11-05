<?php
/**
 * actions.php
 *
 * Semua aksi (post, like/dislike, komentar, hapus, edit profil, kelola user)
 * dikirim ke file ini lewat form POST. Parameter "a" menentukan aksinya.
 * Setelah selesai, pengguna diarahkan kembali ke halaman asal.
 */

require 'config.php';
need_login();

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Metode tidak valid');
}

$user   = me();
$action = $_POST['a'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$back   = $_SERVER['HTTP_REFERER'] ?? (BASE . 'index.php');   // halaman asal
$isDev  = is_dev() ? 1 : 0;

// Jika ukuran kiriman melebihi post_max_size, PHP mengosongkan $_POST dan $_FILES.
// Beri tahu pengguna alih-alih gagal tanpa pesan.
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('Ukuran upload melebihi batas server (post_max_size = ' . ini_get('post_max_size')
        . '). Naikkan batas di php.ini lalu restart Apache.');
    header('Location: ' . $back);
    exit;
}

switch ($action) {

    // -----------------------------------------------------------------
    // Membuat postingan (teks/link + foto/gif/video)
    // -----------------------------------------------------------------
    case 'post':
        [$media, $mediaType] = upload($_FILES['media'] ?? null, 'posts');
        $body = trim($_POST['body'] ?? '');

        // Post kosong (tanpa teks dan tanpa media) diabaikan
        if ($body !== '' || $media) {
            $stmt = $pdo->prepare('INSERT INTO posts (user_id, body, media, media_type) VALUES (?, ?, ?, ?)');
            $stmt->execute([$user['id'], $body, $media, $mediaType]);
        }
        break;

    // -----------------------------------------------------------------
    // Hapus post: pemilik ATAU DEV
    // -----------------------------------------------------------------
    case 'del_post':
        $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ? AND (user_id = ? OR ? = 1)');
        $stmt->execute([$id, $user['id'], $isDev]);
        break;

    // -----------------------------------------------------------------
    // Like / dislike: klik lagi = batal, klik tombol lawan = ganti
    // -----------------------------------------------------------------
    case 'react':
        $value = (($_POST['v'] ?? '') === '1') ? 1 : -1;

        $stmt = $pdo->prepare('SELECT type FROM reactions WHERE user_id = ? AND post_id = ?');
        $stmt->execute([$user['id'], $id]);
        $old = $stmt->fetchColumn();

        if ($old === false) {
            // Belum pernah bereaksi: tambah
            $stmt = $pdo->prepare('INSERT INTO reactions (user_id, post_id, type) VALUES (?, ?, ?)');
            $stmt->execute([$user['id'], $id, $value]);
        } elseif ((int)$old === $value) {
            // Klik reaksi yang sama: batalkan
            $stmt = $pdo->prepare('DELETE FROM reactions WHERE user_id = ? AND post_id = ?');
            $stmt->execute([$user['id'], $id]);
        } else {
            // Klik reaksi yang berbeda: ganti
            $stmt = $pdo->prepare('UPDATE reactions SET type = ? WHERE user_id = ? AND post_id = ?');
            $stmt->execute([$value, $user['id'], $id]);
        }
        break;

    // -----------------------------------------------------------------
    // Komentar teks/link + foto/gif/video ($id = id post)
    // -----------------------------------------------------------------
    case 'comment':
        [$media, $mediaType] = upload($_FILES['media'] ?? null, 'posts');
        $body = trim($_POST['body'] ?? '');

        if ($body !== '' || $media) {
            $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, body, media, media_type) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$id, $user['id'], $body, $media, $mediaType]);
        }
        break;

    // -----------------------------------------------------------------
    // Hapus komentar: pemilik ATAU DEV
    // -----------------------------------------------------------------
    case 'del_comment':
        $stmt = $pdo->prepare('DELETE FROM comments WHERE id = ? AND (user_id = ? OR ? = 1)');
        $stmt->execute([$id, $user['id'], $isDev]);
        break;

    // -----------------------------------------------------------------
    // Edit profil SENDIRI (id diambil dari session, bukan dari form)
    // Foto profil dan banner hanya menerima gambar/GIF.
    // -----------------------------------------------------------------
    case 'profile':
        [$avatar] = upload($_FILES['avatar'] ?? null, 'avatars', false);
        [$banner] = upload($_FILES['banner'] ?? null, 'banners', false);

        $displayName = trim($_POST['display_name'] ?? '');
        if ($displayName === '') {
            $displayName = $user['display_name'];
        }

        // COALESCE: jika tidak ada file baru, pertahankan foto lama
        $stmt = $pdo->prepare(
            'UPDATE users
             SET display_name = ?, bio = ?, avatar = COALESCE(?, avatar), banner = COALESCE(?, banner)
             WHERE id = ?'
        );
        $stmt->execute([$displayName, trim($_POST['bio'] ?? ''), $avatar, $banner, $user['id']]);
        break;

    // -----------------------------------------------------------------
    // Ganti password akun sendiri: wajib verifikasi password saat ini.
    // -----------------------------------------------------------------
    case 'change_password':
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $user['password'])) {
            flash('Password saat ini salah.');
        } elseif (strlen($newPassword) < 6) {
            flash('Password baru harus berisi minimal 6 karakter.');
        } elseif ($newPassword !== $confirmPassword) {
            flash('Konfirmasi password baru tidak cocok.');
        } elseif (password_verify($newPassword, $user['password'])) {
            flash('Password baru harus berbeda dari password saat ini.');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            flash('Password berhasil diganti.');
        }
        $back = BASE . 'profile.php';
        break;

    // -----------------------------------------------------------------
    // Dev Panel: hapus banyak akun sekaligus.
    // ADMIN: akun apa pun, DEV: hanya akun dengan role user.
    // -----------------------------------------------------------------
    case 'del_users':
        if ($isDev && !empty($_POST['ids']) && is_array($_POST['ids'])) {
            // Akun sendiri tidak ikut terhapus
            $ids = array_values(array_filter(
                array_map('intval', $_POST['ids']),
                fn($i) => $i !== (int)$user['id']
            ));

            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                if (is_admin()) {
                    $sql = "DELETE FROM users WHERE id IN ($placeholders)";
                } else {
                    $sql = "DELETE FROM users WHERE role = 'user' AND id IN ($placeholders)";
                }

                // Post, komentar, dan reaksi ikut terhapus (ON DELETE CASCADE)
                $pdo->prepare($sql)->execute($ids);
            }
        }
        $back = BASE . 'dev.php';
        break;

    // -----------------------------------------------------------------
    // Khusus ADMIN: ubah role akun lain (user / dev / admin)
    // -----------------------------------------------------------------
    case 'set_role':
        $role = $_POST['role'] ?? '';

        if (is_admin() && $id !== (int)$user['id'] && in_array($role, ['admin', 'dev', 'user'], true)) {
            $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
            $stmt->execute([$role, $id]);
        }
        $back = BASE . 'dev.php';
        break;

    // -----------------------------------------------------------------
    // Hapus satu akun: diri sendiri; ADMIN: siapa pun; DEV: hanya akun user
    // -----------------------------------------------------------------
    case 'del_user':
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $targetRole = $stmt->fetchColumn();

        if ($id === (int)$user['id'] || is_admin() || ($isDev && $targetRole === 'user')) {
            // Post, komentar, dan reaksi ikut terhapus (ON DELETE CASCADE)
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);

            // Menghapus akun sendiri: keluar dan kembali ke halaman login
            if ($id === (int)$user['id']) {
                session_destroy();
                header('Location: ' . BASE . 'login.php');
                exit;
            }

            $back = BASE . 'index.php';
        }
        break;
}

// Selesai, kembali ke halaman asal
header('Location: ' . $back);
