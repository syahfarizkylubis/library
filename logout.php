<?php
session_start();

// Hapus semua variabel sesi
$_SESSION = array();

// Hancurkan sesi yang aktif
session_destroy();

// Arahkan kembali ke halaman index.php (Portal Utama)
header("Location: index.php");
exit;
?>