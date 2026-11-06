    </main>

    <!-- Kolom kanan (desktop): daftar pengguna -->
    <aside class="right">
        <div class="card" data-live="users">
            <h3>Pengguna</h3>
            <?php
            // 6 akun terbaru, semua role (ADMIN, DEV, USER) tampil untuk semua orang
            $users = $pdo->query('SELECT id, username, display_name, avatar, role FROM users ORDER BY id DESC LIMIT 6');
            foreach ($users as $x): ?>
                <a class="row" href="<?= BASE ?>profile.php?id=<?= $x['id'] ?>">
                    <?= avatar($x) ?>
                    <div>
                        <b><?= e($x['display_name']) ?> <?= role_badge($x['role']) ?></b>
                        <small>@<?= e($x['username']) ?></small>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>
</div>

<!-- Navigasi bawah (khusus HP) -->
<nav class="bottom" data-live="nav-bottom" aria-label="Menu bawah">
    <a href="<?= BASE ?>index.php" aria-label="Home"<?= $onHome ? $current : '' ?>><?= icon('home') ?></a>
    <a href="javascript:location.reload()" aria-label="Refresh"><?= icon('refresh') ?></a>
    <a href="<?= BASE ?>profile.php" aria-label="Profile"<?= $onProfile ? $current : '' ?>><?= icon('user') ?></a>
    <?php if (is_dev()): ?>
        <a href="<?= BASE ?>dev.php" aria-label="Dev Panel"<?= $onDev ? $current : '' ?>><?= icon('shield') ?></a>
    <?php endif; ?>
    <a href="<?= BASE ?>logout.php" aria-label="Logout"><?= icon('out') ?></a>
</nav>
</body>
</html>
