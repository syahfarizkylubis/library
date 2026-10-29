    <?php
    session_start();
    require 'koneksi_db.php'; // Sesuaikan jika nama file koneksi Anda berbeda

    if (!isset($_SESSION['login'])) {
        header("Location: login.php");
        exit;
    }

    $success_pass = '';
    $error_pass = '';

    // Handler Ubah Password
    if (isset($_POST['ubah_password'])) {
        $password_lama = $_POST['password_lama'];
        $password_baru = $_POST['password_baru'];
        $konfirmasi_password = $_POST['konfirmasi_password'];
        $username_aktif = $_SESSION['username'];

        $q_u = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username_aktif'");
        $data_u = mysqli_fetch_assoc($q_u);

        if ($password_lama === $data_u['password']) {
            if ($password_baru === $konfirmasi_password) {
                mysqli_query($koneksi, "UPDATE users SET password = '$password_baru' WHERE username = '$username_aktif'");
                $success_pass = "Password berhasil diperbarui!";
            } else {
                $error_pass = "Konfirmasi password baru tidak cocok!";
            }
        } else {
            $error_pass = "Password lama salah!";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Kelola Akun - Digital Library Web Manager (DLWM)</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>

        <!-- SIDEBAR NAVIGATION -->
        <div class="sidebar">
            <div class="sidebar-brand">SMK RAYA MERDEKA JAKARTA</div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php">Dashboard Utama</a></li>
                <li><a href="kelola_buku.php">Kelola Stok & Buku</a></li>
                <li><a href="kelola_siswa.php">Kelola Akun Siswa</a></li>
                <li class="active"><a href="kelola_akun.php">Kelola Akun Staff</a></li>
            </ul>
            <div class="sidebar-footer">
                <span>Staff: <strong><?= htmlspecialchars($_SESSION['user']); ?></strong></span>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <div class="topbar">
                <h2 style="font-size: 1.2rem; color: #1e293b;">Kelola Akun & Keamanan</h2>
                <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Logout</a>
            </div>

            <div class="container">
                <div class="card">
                    <h3>Ubah Password Staff</h3>
                    <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 20px;">Gunakan form di bawah ini untuk mengubah password akun staff Anda yang sedang aktif.</p>
                    
                    <?php if ($success_pass): ?>
                        <p style="color: #166534; background: #dcfce7; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem;"><?= $success_pass; ?></p>
                    <?php endif; ?>
                    
                    <?php if ($error_pass): ?>
                        <p style="color: #991b1b; background: #fee2e2; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem;"><?= $error_pass; ?></p>
                    <?php endif; ?>

                    <form action="" method="POST" style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 250px;">
                            <label>Password Lama</label>
                            <input type="password" name="password_lama" required placeholder="Masukkan password saat ini">
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 250px;">
                            <label>Password Baru</label>
                            <input type="password" name="password_baru" required placeholder="Masukkan password baru">
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 250px;">
                            <label>Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi_password" required placeholder="Ulangi password baru">
                        </div>
                        <div style="flex: 100%; margin-top: 5px;">
                            <button type="submit" name="ubah_password" class="btn btn-warning">Simpan Password Baru</button>
                            <a href="dashboard.php" class="btn" style="background: #64748b; margin-left: 10px; text-decoration: none;">Kembali</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </body>
    </html>