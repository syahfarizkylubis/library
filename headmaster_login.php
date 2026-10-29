<?php
session_start();
require 'koneksi_db.php';

if (isset($_SESSION['headmaster_login'])) { header("Location: headmaster_dashboard.php"); exit; }

$error = '';
if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = $_POST['password'];

    // Untuk keamanan, akun headmaster bisa dibatasi username tertentu atau role
    $result = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if ($password === $row['password'] && $username === 'headmaster') {
            $_SESSION['headmaster_login'] = true;
            $_SESSION['headmaster_user'] = $row['nama_lengkap'];
            $_SESSION['username'] = $row['username'];
            header("Location: headmaster_dashboard.php");
            exit;
        }
    }
    $error = "Username atau password Headmaster salah!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Administrator - DLWM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
                <h2 style="font-size: 1.2rem; color: #1e293b; text-align: center; margin-bottom: 0px;">Digital Library Web Manager - SMK RAYA MERDEKA JAKARTA</h2> <br>
        <p style="text-align: center; font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">Administrator Login Portal</p>
        <p style="text-align: center; font-size: 0.67rem; color: #64748b; margin-bottom: 17px;">Silahkan Masukkan Username dan Password.</p>
        <?php if ($error): ?><p style="color:red; font-size: 0.85rem; text-align: center; margin-bottom:10px;"><?= $error; ?></p><?php endif; ?>
        <form action="" method="POST">
            <div class="form-group"><label>Username</label><input type="text" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" name="login" class="btn" style="width: 100%; background: #d97706;">Masuk Administrator</button>
            <div style="text-align: center; margin-top: 15px;"><a href="index.php" style="font-size: 0.85rem; color: #64748b; text-decoration: none;">&larr; Kembali</a></div>
        </form>
    </div>
</body>
</html>