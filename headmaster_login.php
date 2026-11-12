<?php
session_start();
require 'koneksi_db.php';

if (isset($_SESSION['headmaster_login']) && $_SESSION['headmaster_login'] === true) {
    header("Location: headmaster_dashboard.php");
    exit;
}

$error = '';

if (isset($_POST['login_headmaster']) || isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 1. Gunakan Prepared Statement (mengambil dari tabel users)
    $stmt = mysqli_prepare($koneksi, "SELECT id, username, password, nama_lengkap, role FROM users WHERE username = ? LIMIT 1");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // Pastikan jika ada kolom role, role-nya sesuai (opsional jika tabel memiliki kolom role)
            $role_valid = (!isset($row['role']) || strtolower($row['role']) === 'headmaster' || strtolower($row['role']) === 'kepsek' || strtolower($row['role']) === 'admin');
            
            $db_password = $row['password'];
            $is_valid = false;

            // 2. Verifikasi Password Hash (dengan auto-upgrade jika masih plain-text)
            if (password_verify($password, $db_password)) {
                $is_valid = true;
            } elseif ($password === $db_password) {
                $is_valid = true;
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = mysqli_prepare($koneksi, "UPDATE users SET password = ? WHERE id = ?");
                if ($upd) {
                    mysqli_stmt_bind_param($upd, "si", $new_hash, $row['id']);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }
            }

            if ($is_valid && $role_valid) {
                // 3. Regenerasi Session ID
                session_regenerate_id(true);

                $_SESSION['headmaster_login'] = true;
                $_SESSION['headmaster_username'] = $row['username'];
                $_SESSION['headmaster_name'] = $row['nama_lengkap'];
                
                mysqli_stmt_close($stmt);
                header("Location: headmaster_dashboard.php");
                exit;
            }
        }
        mysqli_stmt_close($stmt);
    }

    $error = "Kredensial Kepala Sekolah tidak valid!";
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