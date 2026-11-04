<?php
/**
 * render_post(): menampilkan satu postingan + tombol aksi + komentar.
 *
 * @param array $post baris dari tabel posts, ditambah kolom
 *                    username, display_name, avatar, likes, dislikes, mine
 */
function render_post($post)
{
    global $pdo;

    $me        = me();
    $actionUrl = BASE . 'actions.php';

    // Ambil semua komentar untuk post ini
    $stmt = $pdo->prepare(
        'SELECT c.*, u.username, u.display_name, u.avatar
         FROM comments c
         JOIN users u ON u.id = c.user_id
         WHERE c.post_id = ?
         ORDER BY c.id'
    );
    $stmt->execute([$post['id']]);
    $comments = $stmt->fetchAll();

    $myReaction = (int)$post['mine'];                            // 1 = like, -1 = dislike, 0 = belum
    $canDelete  = is_dev() || $post['user_id'] == $me['id'];     // DEV hapus semua, user hanya miliknya
    ?>
    <article class="post">
        <a href="<?= BASE ?>profile.php?id=<?= $post['user_id'] ?>"><?= avatar($post) ?></a>

        <div class="pb">
            <div class="meta">
                <b><?= e($post['display_name']) ?></b>
                <small>@<?= e($post['username']) ?> &middot; <?= e($post['created_at']) ?></small>
            </div>

            <div class="txt"><?= linkify($post['body']) ?></div>
            <?= media_html($post['media'], $post['media_type']) ?>

            <!-- Tombol like, dislike, dan hapus -->
            <div class="acts">
                <form method="post" action="<?= $actionUrl ?>">
                    <input type="hidden" name="a" value="react">
                    <input type="hidden" name="id" value="<?= $post['id'] ?>">
                    <button name="v" value="1" class="<?= $myReaction === 1 ? 'on' : '' ?>">
                        <?= icon('up') ?><?= $post['likes'] ?>
                    </button>
                    <button name="v" value="-1" class="<?= $myReaction === -1 ? 'on' : '' ?>">
                        <?= icon('down') ?><?= $post['dislikes'] ?>
                    </button>
                </form>

                <?php if ($canDelete): ?>
                    <form method="post" action="<?= $actionUrl ?>" onsubmit="return confirm('Hapus postingan ini?')">
                        <input type="hidden" name="a" value="del_post">
                        <input type="hidden" name="id" value="<?= $post['id'] ?>">
                        <button><?= icon('trash') ?></button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Komentar: bisa dibuka/tutup tanpa JavaScript -->
            <details class="cm">
                <summary><?= icon('chat') ?> <?= count($comments) ?> komentar</summary>

                <?php foreach ($comments as $comment): ?>
                    <div class="post small">
                        <?= avatar($comment) ?>
                        <div class="pb">
                            <div class="meta">
                                <b><?= e($comment['display_name']) ?></b>
                                <small>@<?= e($comment['username']) ?></small>
                            </div>
                            <div class="txt"><?= linkify($comment['body']) ?></div>
                            <?= media_html($comment['media'], $comment['media_type']) ?>

                            <?php if (is_dev() || $comment['user_id'] == $me['id']): ?>
                                <form method="post" action="<?= $actionUrl ?>">
                                    <input type="hidden" name="a" value="del_comment">
                                    <input type="hidden" name="id" value="<?= $comment['id'] ?>">
                                    <button class="plain"><?= icon('trash') ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Form komentar: teks/link + foto/gif/video -->
                <form class="cf" method="post" action="<?= $actionUrl ?>" enctype="multipart/form-data">
                    <input type="hidden" name="a" value="comment">
                    <input type="hidden" name="id" value="<?= $post['id'] ?>">

                    <!-- Preview media yang dipilih (diisi oleh assets/app.js) -->
                    <div class="preview" data-for="media" hidden></div>

                    <div class="cf-row">
                        <input name="body" placeholder="Tulis komentar / link">
                        <label class="plain">
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
