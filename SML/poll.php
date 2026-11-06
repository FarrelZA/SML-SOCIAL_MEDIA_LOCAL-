<?php
/**
 * poll.php
 *
 * Dipanggil otomatis oleh assets/live.js setiap beberapa detik.
 * Mengembalikan JSON berisi "sidik jari" data (lihat data_signature() di config.php).
 * Jika sidik jari berbeda dari yang dipegang browser, layar diperbarui.
 */

require 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Session dikunci selama skrip berjalan; lepaskan segera agar permintaan
// lain dari browser yang sama (klik tombol, buka halaman) tidak menunggu.
$loggedIn = (bool)me();
session_write_close();

if (!$loggedIn) {
    // Belum login, atau akun sudah dihapus: browser akan diarahkan ke halaman login
    http_response_code(401);
    echo json_encode(['error' => 'login']);
    exit;
}

echo json_encode(['sig' => data_signature()]);
