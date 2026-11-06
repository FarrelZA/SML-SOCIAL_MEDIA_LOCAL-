/*
 * ui.js
 *
 * Perilaku tampilan yang dipakai di semua halaman:
 *  1. Hanya satu video yang boleh diputar: memutar video baru menjeda yang lain.
 *  2. Transisi saat berpindah halaman (isi meredup sebentar).
 *  3. Tombol yang sedang mengirim form menampilkan spinner (mencegah klik dobel).
 *  4. Pesan berhasil (kotak hijau) hilang sendiri setelah beberapa detik.
 *  5. Kolom teks post membesar mengikuti isinya.
 *
 * Animasinya sendiri ada di style.css. File ini hanya menambah / mencabut class.
 */
(function () {
    'use strict';

    var body = document.body;


    // ------------------------------------------------------------------
    // 1. Satu video saja
    //
    // Event "play" tidak menggelembung (bubble) ke atas, tetapi tetap bisa
    // ditangkap di fase capture. Satu listener di document cukup untuk semua
    // video: yang sudah ada, yang dimuat lewat auto refresh, dan video preview
    // di form post.
    // ------------------------------------------------------------------
    document.addEventListener('play', function (event) {
        var playing = event.target;

        if (!(playing instanceof HTMLVideoElement)) {
            return;
        }

        var videos = document.querySelectorAll('video');
        for (var i = 0; i < videos.length; i++) {
            if (videos[i] !== playing && !videos[i].paused) {
                videos[i].pause();
            }
        }
    }, true);


    // ------------------------------------------------------------------
    // 2. Transisi saat berpindah halaman
    // ------------------------------------------------------------------
    var leaveTimer = null;

    /** Isi halaman meredup (class "leaving" diatur di style.css). */
    function startLeaving() {
        body.classList.add('leaving');

        // Jaga-jaga jika perpindahan batal (mis. file diunduh): kembalikan tampilan
        clearTimeout(leaveTimer);
        leaveTimer = setTimeout(stopLeaving, 6000);
    }

    function stopLeaving() {
        clearTimeout(leaveTimer);
        body.classList.remove('leaving');
    }

    // Tombol Back / Forward bisa menampilkan halaman dari cache dalam keadaan meredup
    // atau tombol masih berputar; bersihkan keduanya.
    window.addEventListener('pageshow', function () {
        stopLeaving();
        var loading = document.querySelectorAll('.is-loading');
        for (var i = 0; i < loading.length; i++) {
            loading[i].classList.remove('is-loading');
            loading[i].removeAttribute('aria-busy');
        }
    });

    // Klik link ke halaman lain di situs ini
    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 ||
            event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || link.target === '_blank' || link.hasAttribute('download')) {
            return;
        }

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) {
            return;
        }

        // Link keluar situs, atau loncat ke #bagian di halaman yang sama: tidak perlu transisi
        if (link.origin !== location.origin ||
            (link.pathname === location.pathname && link.search === location.search)) {
            return;
        }

        startLeaving();
    });


    // ------------------------------------------------------------------
    // 3. Tombol mengirim: spinner + cegah klik dobel
    // ------------------------------------------------------------------
    document.addEventListener('submit', function (event) {
        // Dibatalkan, mis. pengguna menekan "Batal" di kotak konfirmasi hapus
        if (event.defaultPrevented) {
            return;
        }

        var form = event.target;
        var button = event.submitter || form.querySelector('button');

        if (button && button.classList.contains('btn')) {
            // Hanya class. Tombol TIDAK di-disable karena tombol yang disabled
            // tidak ikut mengirim name/value-nya (tombol like/dislike memakainya).
            button.classList.add('is-loading');
            button.setAttribute('aria-busy', 'true');
        }

        // Ikon like/dislike melompat kecil sebagai umpan balik
        if (button && button.name === 'v') {
            var icon = button.querySelector('.ic');
            if (icon) {
                icon.classList.add('pop');
            }
        }

        // Form yang membawa file bisa memakan waktu lama (sampai 512 MB):
        // cukup spinner di tombol, isi halaman tidak perlu diredupkan.
        var files = form.querySelectorAll('input[type=file]');
        for (var i = 0; i < files.length; i++) {
            if (files[i].files && files[i].files.length) {
                return;
            }
        }

        startLeaving();
    });


    // ------------------------------------------------------------------
    // 4. Pesan berhasil hilang sendiri (pesan error tetap sampai halaman berpindah)
    // ------------------------------------------------------------------
    var okMessages = document.querySelectorAll('.flash.ok');
    for (var m = 0; m < okMessages.length; m++) {
        (function (box) {
            setTimeout(function () {
                box.classList.add('hide');                    // memudar + mengecil (style.css)
                setTimeout(function () { box.remove(); }, 300);
            }, 4500);
        })(okMessages[m]);
    }


    // ------------------------------------------------------------------
    // 5. Animasi masuk halaman hanya sekali
    //
    // Class "enter" di <main> menyalakan animasi masuk di style.css. Setelah
    // selesai dicabut agar elemen yang diganti oleh auto refresh (mis. info
    // profil) tidak mengulang animasinya.
    // ------------------------------------------------------------------
    var main = document.querySelector('main.center');
    if (main) {
        setTimeout(function () { main.classList.remove('enter'); }, 1200);
    }


    // ------------------------------------------------------------------
    // 6. Kolom teks post membesar mengikuti isinya (maksimal 320px)
    // ------------------------------------------------------------------
    document.addEventListener('input', function (event) {
        var box = event.target;

        if (box instanceof HTMLTextAreaElement && box.closest('.composer')) {
            box.style.height = 'auto';
            box.style.height = Math.min(Math.max(box.scrollHeight, 72), 320) + 'px';
        }
    });
})();
