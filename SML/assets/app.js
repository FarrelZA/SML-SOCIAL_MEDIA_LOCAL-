/*
 * app.js
 *
 * Preview foto / GIF / video sebelum dikirim.
 *
 * Cara kerja:
 *  - Setiap <input type="file" data-preview> yang dipilih akan ditampilkan di
 *    <div class="preview" data-for="NAMA_INPUT"> dalam form yang sama.
 *  - Preview dibuat dari file di browser (URL.createObjectURL), jadi file
 *    belum dikirim ke server dan tidak memakai kuota upload.
 *  - File dengan format tidak didukung atau lebih besar dari batas server
 *    langsung ditolak dengan pesan, tanpa perlu menunggu upload selesai.
 *  - data-kind="image" berarti hanya gambar/GIF yang boleh (foto profil, banner).
 *  - Batas ukuran dibaca dari <body data-max-upload="..."> (diisi oleh PHP).
 */
(function () {
    'use strict';

    var IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    var VIDEO_EXT = ['mp4', 'webm'];

    // Batas ukuran file dalam byte (0 = tidak diketahui, jangan dicek)
    var maxUpload = parseInt(document.body.getAttribute('data-max-upload'), 10) || 0;


    /** Ubah byte menjadi teks, contoh: 1536 -> "1.5 KB". */
    function formatSize(bytes) {
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = 0;

        while (bytes >= 1024 && i < units.length - 1) {
            bytes /= 1024;
            i++;
        }

        return (i === 0 ? Math.round(bytes) : Math.round(bytes * 10) / 10) + ' ' + units[i];
    }

    /** Ambil ekstensi file dalam huruf kecil, contoh: "Foto.JPG" -> "jpg". */
    function extensionOf(name) {
        var dot = name.lastIndexOf('.');
        return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
    }

    /** Cari kotak preview milik input ini di dalam form yang sama. */
    function findBox(input) {
        if (!input.form) {
            return null;
        }
        return input.form.querySelector('.preview[data-for="' + input.name + '"]');
    }

    /** Lepaskan URL sementara agar memori browser tidak terpakai terus. */
    function releaseUrl(box) {
        if (box.objectUrl) {
            URL.revokeObjectURL(box.objectUrl);
            box.objectUrl = null;
        }
    }

    /** Batalkan pilihan file dan kosongkan preview. */
    function clearSelection(input, box) {
        var video = box.querySelector('video');
        if (video) {
            video.pause();
            video.removeAttribute('src');
            video.load();
        }

        releaseUrl(box);
        input.value = '';
        box.textContent = '';
        box.className = 'preview';
        box.hidden = true;
    }

    /** Batalkan pilihan file lalu tampilkan pesan error di kotak preview. */
    function showError(input, box, message) {
        clearSelection(input, box);
        box.className = 'preview error';
        box.textContent = message;
        box.hidden = false;
    }

    /** Tampilkan preview untuk file yang baru dipilih. */
    function showPreview(input, box, file) {
        var ext = extensionOf(file.name);
        var imagesOnly = input.getAttribute('data-kind') === 'image';
        var isImage = IMAGE_EXT.indexOf(ext) !== -1;
        var isVideo = !imagesOnly && VIDEO_EXT.indexOf(ext) !== -1;

        // 1. Cek format file
        if (!isImage && !isVideo) {
            showError(input, box, 'Format ".' + ext + '" tidak didukung. Gunakan ' +
                (imagesOnly ? 'JPG, PNG, WEBP, atau GIF.' : 'JPG, PNG, WEBP, GIF, MP4, atau WEBM.'));
            return;
        }

        // 2. Cek ukuran file
        if (maxUpload && file.size > maxUpload) {
            showError(input, box, 'File terlalu besar (' + formatSize(file.size) +
                '). Maksimal ' + formatSize(maxUpload) + '.');
            return;
        }

        // 3. Buat elemen preview dari file di browser
        var url = URL.createObjectURL(file);
        var media;

        if (isVideo) {
            media = document.createElement('video');
            media.controls = true;
            media.muted = true;
            media.preload = 'metadata';
            media.setAttribute('playsinline', '');
            media.src = url + '#t=0.1';            // tampilkan frame pertama sebagai gambar awal
        } else {
            media = document.createElement('img');
            media.alt = 'Preview';
            media.src = url;
        }
        media.className = 'preview-media';

        // Tombol silang untuk membatalkan pilihan
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'preview-remove';
        remove.title = 'Hapus pilihan';
        remove.setAttribute('aria-label', 'Hapus pilihan');
        remove.textContent = '×';
        remove.addEventListener('click', function () {
            clearSelection(input, box);
        });

        var frame = document.createElement('div');
        frame.className = 'preview-frame';
        frame.appendChild(media);
        frame.appendChild(remove);

        // Nama file dan ukurannya
        var info = document.createElement('div');
        info.className = 'preview-info';
        info.textContent = file.name + ' · ' + formatSize(file.size);

        // Jika file tidak bisa ditampilkan browser
        media.addEventListener('error', function () {
            if (isVideo) {
                info.textContent += ' · pratinjau tidak bisa diputar di browser ini (file tetap bisa dikirim)';
            } else {
                showError(input, box, 'Gambar tidak bisa dibaca. File mungkin rusak.');
            }
        });

        box.textContent = '';
        box.className = 'preview';
        box.objectUrl = url;
        box.appendChild(frame);
        box.appendChild(info);
        box.hidden = false;
    }


    // Satu listener untuk semua input file (form post, komentar, edit profil)
    document.addEventListener('change', function (event) {
        var input = event.target;

        if (!(input instanceof HTMLInputElement) || input.type !== 'file' || !input.hasAttribute('data-preview')) {
            return;
        }

        var box = findBox(input);
        if (!box) {
            return;
        }

        releaseUrl(box);

        var file = input.files && input.files[0];
        if (!file) {
            clearSelection(input, box);    // dialog dibatalkan
            return;
        }

        showPreview(input, box, file);
    });
})();
