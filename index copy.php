<?php
session_start();
if (isset($_SESSION['login'])) { header("Location: dashboard.php"); exit; }
if (isset($_SESSION['student_login'])) { header("Location: student_dashboard.php"); exit; }
if (isset($_SESSION['headmaster_login'])) { header("Location: headmaster_dashboard.php"); exit; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Portal Utama - Digital Library Web Manager (DLWM)</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
        .landing-container { max-width: 950px; width: 100%; text-align: center; }
        .role-cards { display: flex; gap: 20px; justify-content: center; flex-wrap: wrap; margin-top: 30px; }
        .role-card { background: #1e293b; border: 2px solid #334155; border-radius: 12px; padding: 30px 20px; flex: 1; min-width: 250px; max-width: 300px; text-decoration: none; color: inherit; display: flex; flex-direction: column; align-items: center; transition: 0.3s; }
        .role-card:hover { transform: translateY(-5px); border-color: #38bdf8; }
        .btn-portal { display: inline-block; width: 100%; padding: 10px; background-color: #0284c7; color: white; border-radius: 6px; font-weight: 600; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="landing-container">
        <h1 style="color: #38bdf8; margin-bottom: 10px;">SMK RAYA MERDEKA</h1>
                <h2 style="color: #0f729c; margin-bottom: 10px;">Digital Library Web Manager</h2>

        <p style="color: #94a3b8; margin-bottom: 20px;">Sistem Informasi Perpustakaan Terpadu. Pilih portal masuk Anda:</p>
        <div class="role-cards">
            <!-- Portal Siswa -->
            <a href="student_login.php" class="role-card">
                <div style="font-size: 2.5rem; margin-bottom: 15px;">🎓</div>
                <h3>Portal Siswa</h3>
                <p style="font-size: 0.85rem; color: #94a3b8;">Cek status peminjaman, batas denda, dan riwayat buku.</p>
                <div class="btn-portal">Masuk Siswa</div>
            </a>
            <!-- Portal Staff -->
            <a href="login.php" class="role-card">
                <div style="font-size: 2.5rem; margin-bottom: 15px;">👨‍💼</div>
                <h3>Portal Staff</h3>
                <p style="font-size: 0.85rem; color: #94a3b8;">Manajemen katalog buku, transaksi, dan data siswa.</p>
                <div class="btn-portal" style="background-color: #334155;">Masuk Staff</div>
            </a>
            <!-- Portal Headmaster -->
            <a href="headmaster_login.php" class="role-card" style="border-color: #f59e0b;">
                <div style="font-size: 2.5rem; margin-bottom: 15px;">🏛️</div>
                <h3>Portal Administrator</h3>
                <p style="font-size: 0.85rem; color: #94a3b8;">Dashboard eksekutif, kelola staff, dan audit pendapatan.</p>
                <div class="btn-portal" style="background-color: #d97706;">Masuk Administrator</div>
            </a>
        </div>
    </div>
</body>
</html>