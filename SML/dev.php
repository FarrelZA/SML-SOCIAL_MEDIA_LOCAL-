<?php
/**
 * dev.php
 *
 * Dev Panel: daftar semua akun, hapus akun terpilih, dan (khusus ADMIN) ubah role.
 */

require 'config.php';
need_login();

// Penjaga: USER biasa ditolak di server, bukan hanya disembunyikan menunya
if (!is_dev()) {
    http_response_code(403);
    exit('Akses ditolak: halaman ini khusus DEV.');
}

require 'includes/top.php';

// Ambil semua akun + jumlah post dan komentar masing-masing
$rows = $pdo->query(
    "SELECT u.*,
        (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id)    AS posts,
        (SELECT COUNT(*) FROM comments c WHERE c.user_id = u.id) AS comments
     FROM users u
     ORDER BY FIELD(u.role, 'admin', 'dev', 'user'), u.id DESC"
)->fetchAll();
?>

<!-- Satu form untuk seluruh tabel: centang user lalu klik "Hapus terpilih" -->
<form method="post" action="actions.php"
      onsubmit="return confirm('Hapus user terpilih beserta semua postingannya?')">
    <input type="hidden" name="a" value="del_users">

    <div class="dp-head">
        <div>
            <b>Dev Panel</b> <small>(<?= e($u['role']) ?>)</small><br>
            <small><?= count($rows) ?> akun terdaftar</small>
        </div>
        <button class="btn danger">Hapus terpilih</button>
    </div>

    <div class="dp-wrap">
        <table class="dp">
            <tr>
                <th>
                    <input type="checkbox" title="Pilih semua"
                           onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)">
                </th>
                <th>Pengguna</th>
                <th>Role</th>
                <th>Post</th>
                <th>Komentar</th>
                <th>Daftar</th>
            </tr>

            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <?php // ADMIN bisa mencentang semua akun, DEV hanya akun user (akun sendiri tidak) ?>
                        <?php if ($r['id'] != $u['id'] && (is_admin() || $r['role'] === 'user')): ?>
                            <input type="checkbox" class="pick" name="ids[]" value="<?= $r['id'] ?>">
                        <?php endif; ?>
                    </td>

                    <td>
                        <a class="row" href="<?= BASE ?>profile.php?id=<?= $r['id'] ?>">
                            <?= avatar($r) ?>
                            <div>
                                <b><?= e($r['display_name']) ?></b>
                                <small>@<?= e($r['username']) ?></small>
                            </div>
                        </a>
                    </td>

                    <td>
                        <?php if (is_admin() && $r['id'] != $u['id']): ?>
                            <!-- Dropdown role: terhubung ke form terpisah di bawah lewat atribut form="" -->
                            <select form="role<?= $r['id'] ?>" name="role" onchange="this.form.submit()">
                                <?php foreach (['admin', 'dev', 'user'] as $option): ?>
                                    <option <?= $r['role'] === $option ? 'selected' : '' ?>><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?= e($r['role']) ?>
                        <?php endif; ?>
                    </td>

                    <td><?= $r['posts'] ?></td>
                    <td><?= $r['comments'] ?></td>
                    <td><small><?= e(substr($r['created_at'], 0, 10)) ?></small></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</form>

<?php // Form terpisah untuk ubah role (form tidak boleh bersarang di dalam form lain) ?>
<?php if (is_admin()): ?>
    <?php foreach ($rows as $r): ?>
        <form id="role<?= $r['id'] ?>" method="post" action="actions.php">
            <input type="hidden" name="a" value="set_role">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<?php require 'includes/bottom.php'; ?>
