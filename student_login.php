<?php
session_start();
require 'koneksi_db.php';

if (isset($_SESSION['student_login']) && $_SESSION['student_login'] === true) {
    header("Location: student_dashboard.php");
    exit;
}

$error = '';

if (isset($_POST['login_student'])) {
    $npm = trim(mysqli_real_escape_string($koneksi, $_POST['npm']));
    $password = trim($_POST['password']);

    $result = mysqli_query($koneksi, "SELECT * FROM students WHERE npm = '$npm'");
    
    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        
        if ($password === $row['password']) {
            $_SESSION['student_login'] = true;
            $_SESSION['student_npm'] = $row['npm'];
            $_SESSION['student_name'] = $row['nama_lengkap'];
            header("Location: student_dashboard.php");
            exit;
        }
    }
    $error = "NIS atau password salah, atau akun belum terdaftar!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Siswa - Digital Library Web Manager (DLWM)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
                <h2 style="font-size: 1.2rem; color: #1e293b; text-align: center; margin-bottom: 0px;">Digital Library Web Manager - SMK RAYA MERDEKA JAKARTA</h2> <br>
        <p style="text-align: center; font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">Student Login Portal</p>
        <p style="text-align: center; font-size: 0.67rem; color: #64748b; margin-bottom: 17px;">Silahkan Masukkan Username dan Password.</p>
        
        <?php if ($error): ?>
            <p style="color:red; margin-bottom: 10px; font-size: 0.85rem; text-align: center;"><?= $error; ?></p>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label>NIS (Nomor Induk Siswa)</label>
                <input type="text" name="npm" required placeholder="Contoh: 202343500123" maxlength="12">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Masukkan password">
            </div>
            <button type="submit" name="login_student" class="btn" style="width: 100%; background-color: #0284c7;">Masuk Portal Siswa</button>
            <div style="text-align: center; margin-top: 15px;">
                <a href="index.php" style="font-size: 0.85rem; color: #64748b; text-decoration: none;">&larr; Kembali ke Landing Page</a>
            </div>
        </form>
    </div>
</body>
</html>