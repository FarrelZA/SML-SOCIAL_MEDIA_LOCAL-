/*
 * live.js
 *
 * Auto refresh: layar ikut berubah saat ada informasi baru di database
 * (post, komentar, like, hapus, ganti nama/foto/role), tanpa perlu menekan Refresh.
 *
 * Cara kerja:
 *  1. Setiap 3 detik browser bertanya ke poll.php: "sidik jari data sekarang berapa?".
 *     Jawabannya hanya beberapa byte, jadi hampir tidak membebani server.
 *  2. Jika sama dengan sidik jari halaman ini, tidak terjadi apa-apa.
 *  3. Jika berbeda, halaman yang sama diunduh ulang di latar belakang
 *     (dengan header X-Live) lalu isinya DIBANDINGKAN dengan yang di layar.
 *     Hanya bagian yang benar-benar berubah yang diganti. Halaman tidak dimuat ulang,
 *     jadi tidak ada kedip, scroll tidak loncat, dan yang sedang berjalan tidak terganggu:
 *       - video yang sedang diputar tidak disentuh (elemen media tidak pernah diganti),
 *       - teks komentar yang sedang diketik dan file yang sudah dipilih tetap ada,
 *       - komentar yang sedang dibuka tetap terbuka.
 *  4. Kalau pengguna sedang scroll ke bawah, postingan baru tidak mendorong layar;
 *     muncul tombol "N postingan baru" yang bisa diklik.
 *  5. Dev Panel tidak diganti saat ada kotak yang dicentang atau dropdown yang dipakai;
 *     muncul tombol "Ada pembaruan" sebagai gantinya.
 *
 * Polling berhenti saat tab tidak terlihat dan berlanjut saat tab dibuka lagi.
 * Jika server tidak terjangkau, jeda antar-permintaan diperlebar otomatis.
 */
(function () {
    'use strict';

    var body = document.body;
    var pollUrl = body.getAttribute('data-poll');

    // Halaman tanpa data-poll (login, daftar) tidak memakai auto refresh
    if (!pollUrl || !window.fetch || !window.DOMParser) {
        return;
    }

    var loginUrl   = body.getAttribute('data-login') || 'login.php';
    var INTERVAL   = 3000;      // jeda normal antar-pengecekan (ms)
    var MAX_WAIT   = 30000;     // jeda terpanjang saat server tidak terjangkau
    var TOP_ZONE   = 160;       // di bawah batas ini (px) layar dianggap "sedang di atas"

    var sig       = body.getAttribute('data-sig');   // sidik jari data yang sedang tampil
    var failures  = 0;
    var running   = false;
    var timer     = null;
    var staged    = null;       // halaman terbaru dari server (untuk bagian yang ditunda)
    var pill      = null;
    var dot       = document.querySelector('.live');
    var reduce    = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;


    // ------------------------------------------------------------------
    // Utilitas kecil
    // ------------------------------------------------------------------

    function sleep(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    /** Anak langsung dari el yang cocok dengan selector (tanpa :scope, agar didukung luas). */
    function childrenOf(el, selector) {
        return Array.prototype.filter.call(el.children, function (child) {
            return child.matches(selector);
        });
    }

    /** Impor elemen dari dokumen hasil unduhan ke dokumen yang tampil. */
    function adopt(node) {
        return document.importNode(node, true);
    }

    /** Jalankan ulang animasi "angka naik" pada elemen. */
    function bump(el) {
        el.classList.remove('bump');
        void el.offsetWidth;                       // paksa browser menghitung ulang agar animasi mulai lagi
        el.classList.add('bump');
        setTimeout(function () { el.classList.remove('bump'); }, 400);
    }

    /** Ganti cur dengan next hanya jika isinya berbeda. */
    function swapIfDifferent(cur, next) {
        if (cur && next && cur.outerHTML !== next.outerHTML) {
            cur.replaceWith(adopt(next));
        }
    }

    function setStatus(state) {
        if (!dot) {
            return;
        }
        dot.setAttribute('data-state', state);
        dot.title = state === 'on' ? 'Auto refresh aktif' : 'Terputus dari server, mencoba lagi...';
    }

    /**
     * True jika pengguna sedang memakai sesuatu di dalam el (jangan diganti diam-diam):
     * ada kotak yang dicentang, atau kursor sedang di kolom teks / dropdown.
     * Kotak centang yang sekadar punya fokus tidak dihitung: setelah dilepas centangnya
     * fokus masih tertinggal di sana, dan itu tidak boleh menahan pembaruan selamanya.
     */
    function isBusy(el) {
        if (el.querySelector('input[type=checkbox]:checked')) {
            return true;
        }

        var active = document.activeElement;
        if (!active || !el.contains(active)) {
            return false;
        }

        if (active.tagName === 'SELECT' || active.tagName === 'TEXTAREA') {
            return true;
        }

        return active.tagName === 'INPUT' && !/^(checkbox|radio|button|submit|reset|file|image)$/.test(active.type);
    }

    /** Hapus elemen dengan animasi: memudar lalu mengecil sehingga yang di bawah naik halus. */
    function collapseRemove(el) {
        if (el.classList.contains('is-leaving')) {
            return;
        }

        el.classList.add('is-leaving');
        el.style.maxHeight = el.offsetHeight + 'px';
        void el.offsetHeight;
        el.style.maxHeight = '0px';
        setTimeout(function () { el.remove(); }, 320);
    }

    /** Beri tanda "baru" (animasi masuk) lalu cabut tandanya. */
    function markNew(el) {
        el.classList.add('is-new');
        setTimeout(function () { el.classList.remove('is-new'); }, 1800);
    }


    // ------------------------------------------------------------------
    // Tombol melayang: "N postingan baru" / "Ada pembaruan"
    // ------------------------------------------------------------------

    function showPill(text) {
        if (!pill) {
            pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'pill';
            pill.setAttribute('aria-live', 'polite');
            pill.addEventListener('click', onPillClick);
            body.appendChild(pill);
        }

        pill.textContent = text;
        // Satu frame kemudian agar transisi muncul berjalan
        requestAnimationFrame(function () { pill.classList.add('show'); });
    }

    function hidePill() {
        if (pill) {
            pill.classList.remove('show');
        }
    }

    function onPillClick() {
        var hadNewPosts = pill && /postingan/.test(pill.textContent);

        if (staged) {
            applyPage(staged, true);
        }
        hidePill();

        if (hadNewPosts) {
            window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
        }
    }

    // Kembali ke atas secara manual: masukkan postingan yang tadi ditunda
    var scrollQueued = false;
    window.addEventListener('scroll', function () {
        if (scrollQueued || !pill || !pill.classList.contains('show') || window.scrollY > TOP_ZONE) {
            return;
        }

        scrollQueued = true;
        requestAnimationFrame(function () {
            scrollQueued = false;
            if (staged) {
                applyPage(staged, false);
            }
        });
    }, { passive: true });


    // ------------------------------------------------------------------
    // Menerapkan halaman terbaru ke layar
    // ------------------------------------------------------------------

    /**
     * Bandingkan halaman dari server dengan layar, ganti yang berbeda.
     * force = true: abaikan penundaan (dipakai tombol melayang).
     */
    function applyPage(doc, force) {
        staged = doc;

        var deferredOther = syncSwaps(doc, force);
        var deferredPosts = syncFeed(doc, force);

        if (deferredPosts > 0) {
            showPill(deferredPosts + ' postingan baru');
        } else if (deferredOther > 0) {
            showPill('Ada pembaruan');
        } else {
            hidePill();
            staged = null;
        }
    }

    /**
     * Bagian bertanda data-live (menu, info akun, daftar pengguna, info profil, Dev Panel):
     * diganti utuh jika isinya berbeda. Mengembalikan jumlah yang ditunda karena sedang dipakai.
     */
    function syncSwaps(doc, force) {
        var deferred = 0;
        var parts = document.querySelectorAll('[data-live]');

        for (var i = 0; i < parts.length; i++) {
            var cur  = parts[i];
            var name = cur.getAttribute('data-live');
            var next = doc.querySelector('[data-live="' + name + '"]');

            if (!next) {
                // Bagian ini sudah tidak ada di server (mis. profil yang dihapus): muat ulang
                if (name === 'pinfo' || name === 'dev') {
                    location.reload();
                }
                continue;
            }

            if (cur.outerHTML === next.outerHTML) {
                continue;
            }

            if (!force && isBusy(cur)) {
                deferred++;
                continue;
            }

            cur.replaceWith(adopt(next));
        }

        return deferred;
    }

    /**
     * Daftar postingan (#feed). Mengembalikan jumlah postingan baru yang ditunda
     * karena pengguna sedang scroll ke bawah.
     */
    function syncFeed(doc, force) {
        var cur  = document.getElementById('feed');
        var next = doc.getElementById('feed');

        if (!cur || !next) {
            return 0;
        }

        var curPosts  = childrenOf(cur, 'article.post');
        var nextPosts = childrenOf(next, 'article.post');

        var curById = {};
        curPosts.forEach(function (post) { curById[post.getAttribute('data-id')] = post; });

        var keep = {};
        nextPosts.forEach(function (post) { keep[post.getAttribute('data-id')] = true; });

        // 1. Postingan yang sudah tidak ada di server (dihapus): hilang dengan animasi
        curPosts.forEach(function (post) {
            if (!keep[post.getAttribute('data-id')]) {
                collapseRemove(post);
            }
        });

        // 2. Perbarui yang ada, sisipkan yang baru sesuai urutan dari server
        var deferred   = 0;
        var previous   = null;                                  // elemen di layar tepat di atas posisi sisip
        var nearTop    = window.scrollY <= TOP_ZONE;

        nextPosts.forEach(function (np) {
            var existing = curById[np.getAttribute('data-id')];

            if (existing) {
                patchPost(existing, np);
                previous = existing;
                return;
            }

            // Postingan baru di paling atas saat pengguna sedang membaca di bawah:
            // tunda supaya bacaannya tidak terdorong.
            if (!previous && !nearTop && !force) {
                deferred++;
                return;
            }

            var fresh = adopt(np);
            markNew(fresh);

            childrenOf(cur, '.empty').forEach(function (empty) { empty.remove(); });

            if (previous) {
                previous.after(fresh);
            } else {
                cur.insertBefore(fresh, cur.firstChild);
            }
            previous = fresh;
        });

        // 3. Keadaan kosong ("Belum ada postingan")
        var remaining = childrenOf(cur, 'article.post:not(.is-leaving)').length;
        var emptyNext = childrenOf(next, '.empty')[0];

        if (!remaining && !childrenOf(cur, '.empty').length && emptyNext) {
            cur.appendChild(adopt(emptyNext));
        }

        return deferred;
    }

    /** Perbarui satu postingan hanya pada bagian yang berubah. Media tidak pernah disentuh. */
    function patchPost(cur, next) {
        if (cur.getAttribute('data-sig') === next.getAttribute('data-sig')) {
            return;
        }
        cur.setAttribute('data-sig', next.getAttribute('data-sig'));

        // Foto profil dan nama penulis (bisa berubah jika penulis edit profil / diganti role)
        swapIfDifferent(cur.querySelector('.avlink'), next.querySelector('.avlink'));
        swapIfDifferent(cur.querySelector('.meta'), next.querySelector('.meta'));

        patchActions(cur.querySelector('.acts'), next.querySelector('.acts'));

        // Jumlah komentar di tombol "N komentar"
        var curCount  = cur.querySelector('.ccount');
        var nextCount = next.querySelector('.ccount');
        if (curCount && nextCount && curCount.textContent !== nextCount.textContent) {
            curCount.textContent = nextCount.textContent;
            bump(curCount);
        }

        patchComments(cur.querySelector('.clist'), next.querySelector('.clist'));
    }

    /** Tombol like / dislike: ubah angka dan status aktif, tombolnya sendiri tetap. */
    function patchActions(cur, next) {
        if (!cur || !next) {
            return;
        }

        // Tombol hapus muncul / hilang (mis. role pengguna berubah): ganti utuh
        if (cur.querySelectorAll('form').length !== next.querySelectorAll('form').length) {
            cur.replaceWith(adopt(next));
            return;
        }

        ['1', '-1'].forEach(function (value) {
            var curBtn  = cur.querySelector('button[value="' + value + '"]');
            var nextBtn = next.querySelector('button[value="' + value + '"]');

            if (!curBtn || !nextBtn) {
                return;
            }

            var curNum  = curBtn.querySelector('.count');
            var nextNum = nextBtn.querySelector('.count');
            if (curNum && nextNum && curNum.textContent !== nextNum.textContent) {
                curNum.textContent = nextNum.textContent;
                bump(curNum);
            }

            var active = nextBtn.classList.contains('on');
            curBtn.classList.toggle('on', active);
            curBtn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    /** Daftar komentar: tambah yang baru, hapus yang hilang, perbarui nama/foto. */
    function patchComments(cur, next) {
        if (!cur || !next) {
            return;
        }

        var curList  = childrenOf(cur, '.post');
        var nextList = childrenOf(next, '.post');

        var curById = {};
        curList.forEach(function (item) { curById[item.getAttribute('data-id')] = item; });

        var keep = {};
        nextList.forEach(function (item) { keep[item.getAttribute('data-id')] = true; });

        curList.forEach(function (item) {
            if (!keep[item.getAttribute('data-id')]) {
                collapseRemove(item);
            }
        });

        var previous = null;

        nextList.forEach(function (nc) {
            var existing = curById[nc.getAttribute('data-id')];

            if (existing) {
                if (existing.getAttribute('data-sig') !== nc.getAttribute('data-sig')) {
                    existing.setAttribute('data-sig', nc.getAttribute('data-sig'));
                    swapIfDifferent(existing.querySelector('.avlink'), nc.querySelector('.avlink'));
                    swapIfDifferent(existing.querySelector('.meta'), nc.querySelector('.meta'));

                    // Tombol hapus komentar muncul / hilang
                    var curForm  = existing.querySelector('.pb > form');
                    var nextForm = nc.querySelector('.pb > form');
                    if (curForm && !nextForm) {
                        curForm.remove();
                    } else if (!curForm && nextForm) {
                        existing.querySelector('.pb').appendChild(adopt(nextForm));
                    }
                }
                previous = existing;
                return;
            }

            var fresh = adopt(nc);
            markNew(fresh);

            if (previous) {
                previous.after(fresh);
            } else {
                cur.insertBefore(fresh, cur.firstChild);
            }
            previous = fresh;
        });
    }


    // ------------------------------------------------------------------
    // Mengambil halaman terbaru dari server
    // ------------------------------------------------------------------

    async function refresh() {
        var res = await fetch(location.href, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'X-Live': '1' }
        });

        // Sesi habis atau akun dihapus: kembali ke halaman login
        if (res.redirected && res.url.indexOf(loginUrl) !== -1) {
            location.href = loginUrl;
            return;
        }

        // Hak akses berubah (mis. DEV dicabut saat membuka Dev Panel): tampilkan halaman apa adanya
        if (res.status === 401 || res.status === 403 || res.status === 404) {
            location.reload();
            return;
        }

        if (!res.ok) {
            throw new Error('HTTP ' + res.status);
        }

        var html = await res.text();
        var doc  = new DOMParser().parseFromString(html, 'text/html');
        var newSig = doc.body && doc.body.getAttribute('data-sig');

        if (!newSig) {
            throw new Error('Halaman tidak dikenali');
        }

        sig = newSig;
        body.setAttribute('data-sig', newSig);      // tetap sinkron agar mudah diperiksa di inspect element
        applyPage(doc, false);
    }


    // ------------------------------------------------------------------
    // Pengecekan berkala
    // ------------------------------------------------------------------

    function schedule(ms) {
        clearTimeout(timer);
        timer = setTimeout(tick, ms);
    }

    function nextDelay() {
        return failures ? Math.min(INTERVAL * Math.pow(2, failures), MAX_WAIT) : INTERVAL;
    }

    async function tick() {
        // Tab tidak terlihat: berhenti. Dilanjutkan oleh visibilitychange.
        if (document.hidden || running) {
            return;
        }

        running = true;

        try {
            var res = await fetch(pollUrl, { cache: 'no-store', credentials: 'same-origin' });

            if (res.status === 401) {
                location.href = loginUrl;
                return;
            }
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }

            var data = await res.json();
            failures = 0;
            setStatus('on');

            if (data.sig && data.sig !== sig) {
                // Jeda acak singkat: puluhan perangkat yang melihat perubahan yang sama
                // tidak mengunduh halaman di detik yang persis sama.
                await sleep(Math.random() * 500);
                await refresh();
            } else if (staged) {
                applyPage(staged, false);       // coba lagi bagian yang tadi ditunda
            }
        } catch (error) {
            failures++;
            setStatus('off');
        } finally {
            running = false;
            if (!document.hidden) {
                schedule(nextDelay());
            }
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            clearTimeout(timer);
        } else {
            schedule(0);                        // tab dibuka lagi: cek langsung
        }
    });

    window.addEventListener('online', function () { schedule(0); });

    // Kembali lewat tombol Back (halaman dari cache): datanya mungkin sudah usang
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            schedule(0);
        }
    });

    schedule(INTERVAL);
})();
