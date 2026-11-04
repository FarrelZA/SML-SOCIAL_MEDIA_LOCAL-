<?php
/**
 * logout.php
 *
 * Keluar: hapus session lalu kembali ke halaman login.
 */

require 'config.php';

session_destroy();                          // hapus session = logout
header('Location: ' . BASE . 'login.php');  // kembali ke halaman login
