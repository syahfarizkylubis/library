<?php
session_start();
require 'koneksi_db.php';

$error = '';

if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = $_POST['password'];

    $result = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");
    
    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        
        if ($password === $row['password']) {
            $_SESSION['login'] = true;
            $_SESSION['username'] = $row['username'];
            $_SESSION['user'] = $row['nama_lengkap'];
            header("Location: dashboard.php");
            exit;
        }
    }
    $error = "Username atau password salah!";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login - Digital Library Web Manager (DLWM)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2 style="font-size: 1.2rem; color: #1e293b; text-align: center; margin-bottom: 0px;">Digital Library Web Manager - SMK RAYA MERDEKA JAKARTA</h2> <br>
        <p style="text-align: center; font-size: 0.85rem; color: #64748b; margin-bottom: 20px;">Staff Login Portal</p>
        <p style="text-align: center; font-size: 0.67rem; color: #64748b; margin-bottom: 17px;">Silahkan Masukkan Username dan Password.</p>
        
        <?php if ($error): ?>
            <p style="color:red; margin-bottom: 10px; font-size: 0.85rem; text-align: center;"><?= $error; ?></p>
        <?php endif; ?>
        <form action="" method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn" style="width: 100%;">Masuk</button>
            <div style="text-align: center; margin-top: 15px;">
    <a href="index.php" style="font-size: 0.85rem; color: #64748b; text-decoration: none;">&larr; Kembali ke Landing Page</a>
</div>
        </form>
    </div>
</body>
</html> 