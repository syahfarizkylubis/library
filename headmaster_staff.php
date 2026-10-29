<?php
session_start();
require 'koneksi_db.php';
if (!isset($_SESSION['headmaster_login'])) { header("Location: headmaster_login.php"); exit; }

$error = ''; $success = '';
if (isset($_POST['tambah_staff'])) {
    $username = mysqli_real_escape_string($koneksi, trim($_POST['username']));
    $password = mysqli_real_escape_string($koneksi, trim($_POST['password']));
    $nama = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));

    $cek = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username'");
    if (mysqli_num_rows($cek) > 0) {
        $error = "Username staff sudah digunakan!";
    } else {
        mysqli_query($koneksi, "INSERT INTO users (username, password, nama_lengkap) VALUES ('$username', '$password', '$nama')");
        $success = "Akun staff berhasil didaftarkan!";
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    $q_u = mysqli_query($koneksi, "SELECT username FROM users WHERE id = $id");
    $d_u = mysqli_fetch_assoc($q_u);
    if ($d_u && $d_u['username'] !== 'headmaster') {
        mysqli_query($koneksi, "DELETE FROM users WHERE id = $id");
    }
    header("Location: headmaster_staff.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Akun Staff - Headmaster</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand" style="background:#b45309;">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li><a href="headmaster_dashboard.php">Dashboard Eksekutif</a></li>
            <li class="active"><a href="headmaster_staff.php">Kelola Akun Staff</a></li>
            <li><a href="headmaster_audit.php">Audit & Pendapatan</a></li>
        </ul>
        <div class="sidebar-footer"><span>Administrator</span></div>
    </div>

    <div class="main-content">
        <div class="topbar"><h2>Manajemen Akun Staff</h2><a href="logout.php" style="color: #f87171; text-decoration: none;">Logout</a></div>
        <div class="container">
            <div class="card">
                <h3>Daftarkan Staff Baru</h3>
                <?php if ($error): ?><p style="color:red; background:#fee2f2; padding:8px;"><?= $error; ?></p><?php endif; ?>
                <?php if ($success): ?><p style="color:green; background:#dcfce7; padding:8px;"><?= $success; ?></p><?php endif; ?>
                <form action="" method="POST" style="display:flex; gap:15px; flex-wrap:wrap;">
                    <div class="form-group" style="flex:1;"><label>Username</label><input type="text" name="username" required></div>
                    <div class="form-group" style="flex:1;"><label>Password</label><input type="password" name="password" required></div>
                    <div class="form-group" style="flex:2;"><label>Nama Lengkap Staff</label><input type="text" name="nama_lengkap" required></div>
                    <div style="flex:100%;"><button type="submit" name="tambah_staff" class="btn" style="background:#d97706;">Tambah Staff</button></div>
                </form>
            </div>
            <div class="card">
                <h3>Daftar Staff Terdaftar</h3>
                <table class="spreadsheet">
                    <thead><tr><th>No</th><th>Username</th><th>Nama Lengkap</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php $no=1; $res=mysqli_query($koneksi, "SELECT * FROM users WHERE username != 'headmaster' ORDER BY id DESC");
                        while($s=mysqli_fetch_assoc($res)): ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><?= htmlspecialchars($s['username']); ?></td>
                            <td><?= htmlspecialchars($s['nama_lengkap']); ?></td>
                            <td><a href="headmaster_staff.php?hapus=<?= $s['id']; ?>" class="btn" style="background:#ef4444; padding:4px 8px; font-size:0.75rem;" onclick="return confirm('Hapus staff ini?')">Hapus</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>