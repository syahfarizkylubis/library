<?php
session_start();
require 'koneksi_db.php';

// Jika sudah login, langsung arahkan ke dashboard
if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 1. Gunakan Prepared Statement untuk mencegah SQL Injection
    $stmt = mysqli_prepare($koneksi, "SELECT id, username, password, nama_lengkap FROM users WHERE username = ? LIMIT 1");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            $db_password = $row['password'];
            $is_valid = false;

            // 2. Cek apakah password cocok menggunakan password_verify (Bcrypt Hash)
            if (password_verify($password, $db_password)) {
                $is_valid = true;
            } 
            // Fallback transisi: Jika di DB masih plain-text, cocokkan lalu otomatis upgrade ke Hash
            elseif ($password === $db_password) {
                $is_valid = true;
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id = ?");
                if ($upd) {
                    mysqli_stmt_bind_param($upd, "si", $new_hash, $row['id']);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }
            }

            if ($is_valid) {
                // 3. Cegah Session Fixation dengan regenerasi ID session
                session_regenerate_id(true);
                
                $_SESSION['login'] = true;
                $_SESSION['username'] = $row['username'];
                $_SESSION['user'] = $row['nama_lengkap'];
                
                mysqli_stmt_close($stmt);
                header("Location: dashboard.php");
                exit;
            }
        }
        mysqli_stmt_close($stmt);
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