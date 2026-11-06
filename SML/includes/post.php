<?php
/**
 * render_post(): menampilkan satu postingan + tombol aksi + komentar.
 *
 * @param array $post baris dari tabel posts, ditambah kolom
 *                    username, display_name, avatar, role, likes, dislikes, mine
 *
 * Atribut data-id dan data-sig dibaca oleh assets/live.js untuk auto refresh:
 *  - data-id  : id post / komentar, untuk mencocokkan elemen lama dengan yang baru.
 *  - data-sig : sidik jari isi yang bisa berubah (angka like, nama, foto, daftar komentar).
 *               Jika sama dengan yang di layar, elemen itu tidak disentuh sama sekali.
 */
function render_post($post)
{
    global $pdo;

    $me        = me();
    $actionUrl = BASE . 'actions.php';

    // Ambil semua komentar untuk post ini
    $stmt = $pdo->prepare(
        'SELECT c.*, u.username, u.display_name, u.avatar, u.role
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ?
         ORDER BY c.id'
    );
    $stmt->execute([$post['id']]);
    $comments = $stmt->fetchAll();

    $myReaction = (int)$post['mine'];                            // 1 = like, -1 = dislike, 0 = belum
    $canDelete  = is_dev() || $post['user_id'] == $me['id'];     // DEV hapus semua, user hanya miliknya

    // Sidik jari tiap komentar dan sidik jari seluruh post (lihat penjelasan di atas)
    $parts = [$post['likes'], $post['dislikes'], $myReaction, $post['display_name'],
              $post['avatar'], $post['role'], (int)$canDelete];

    foreach ($comments as $i => $comment) {
        $canDeleteComment = is_dev() || $comment['user_id'] == $me['id'];
        $comments[$i]['sig'] = md5(implode('|', [$comment['display_name'], $comment['avatar'],
                                                 $comment['role'], (int)$canDeleteComment]));
        $comments[$i]['can_delete'] = $canDeleteComment;
        $parts[] = $comment['id'] . ':' . $comments[$i]['sig'];
    }
    $postSig = md5(implode('|', $parts));
    ?>
    <article class="post" data-id="<?= $post['id'] ?>" data-sig="<?= $postSig ?>">
        <a class="avlink" href="<?= BASE ?>profile.php?id=<?= $post['user_id'] ?>"
           aria-label="Profil <?= e($post['display_name']) ?>"><?= avatar($post) ?></a>

        <div class="pb">
            <div class="meta">
                <b><?= e($post['display_name']) ?></b> <?= role_badge($post['role']) ?>
                <small>@<?= e($post['username']) ?> &middot; <?= e($post['created_at']) ?></small>
            </div>

            <div class="txt"><?= linkify($post['body']) ?></div>
            <?= media_html($post['media'], $post['media_type']) ?>

            <!-- Tombol like, dislike, dan hapus -->
            <div class="acts">
                <form method="post" action="<?= $actionUrl ?>">
                    <input type="hidden" name="a" value="react">
                    <input type="hidden" name="id" value="<?= $post['id'] ?>">
                    <button name="v" value="1" class="<?= $myReaction === 1 ? 'on' : '' ?>"
                            aria-label="Suka" aria-pressed="<?= $myReaction === 1 ? 'true' : 'false' ?>">
                        <?= icon('up') ?><span class="count"><?= $post['likes'] ?></span>
                    </button>
                    <button name="v" value="-1" class="<?= $myReaction === -1 ? 'on' : '' ?>"
                            aria-label="Tidak suka" aria-pressed="<?= $myReaction === -1 ? 'true' : 'false' ?>">
                        <?= icon('down') ?><span class="count"><?= $post['dislikes'] ?></span>
                    </button>
                </form>

                <?php if ($canDelete): ?>
                    <form method="post" action="<?= $actionUrl ?>" onsubmit="return confirm('Hapus postingan ini?')">
                        <input type="hidden" name="a" value="del_post">
                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                        <button aria-label="Hapus postingan"><?= icon('trash') ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Komentar: bisa dibuka/tutup tanpa JavaScript -->
            <details class="cm">
                <summary><?= icon('chat') ?> <span class="ccount"><?= count($comments) ?></span> komentar</summary>

                <div class="clist">
                    <?php foreach ($comments as $comment): ?>
                        <div class="post small" data-id="<?= $comment['id'] ?>" data-sig="<?= $comment['sig'] ?>">
                            <span class="avlink"><?= avatar($comment) ?></span>
                            <div class="pb">
                                <div class="meta">
                                    <b><?= e($comment['display_name']) ?></b> <?= role_badge($comment['role']) ?>
                                    <small>@<?= e($comment['username']) ?></small>
                                </div>
                                <div class="txt"><?= linkify($comment['body']) ?></div>
                                <?= media_html($comment['media'], $comment['media_type']) ?>

                                <?php if ($comment['can_delete']): ?>
                                    <form method="post" action="<?= $actionUrl ?>">
                                        <input type="hidden" name="a" value="del_comment">
                                        <input type="hidden" name="id" value="<?= $comment['id'] ?>">
                                        <button class="plain" aria-label="Hapus komentar"><?= icon('trash') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Form komentar: teks/link + foto/gif/video -->
                <form class="cf" method="post" action="<?= $actionUrl ?>" enctype="multipart/form-data">
                    <input type="hidden" name="a" value="comment">
                    <input type="hidden" name="id" value="<?= $post['id'] ?>">

                    <!-- Preview media yang dipilih (diisi oleh assets/app.js) -->
                    <div class="preview" data-for="media" hidden></div>

                    <div class="cf-row">
                        <input name="body" placeholder="Tulis komentar / link" aria-label="Tulis komentar">
                        <label class="plain" title="Lampirkan foto, GIF, atau video">
                            <?= icon('image') ?>
                            <input type="file" name="media" accept="<?= ACCEPT_MEDIA ?>" data-preview hidden>
                        </label>
                        <button class="btn">Kirim</button>
                    </div>
                </form>
            </details>
        </div>
    </article>
    <?php
}
