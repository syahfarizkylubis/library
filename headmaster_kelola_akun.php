<?php
session_start();
require 'koneksi_db.php';

// Pastikan hanya staff yang login yang dapat mengakses
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$username_aktif = $_SESSION['user'];
$success = '';
$error = '';

// Ambil data staff saat ini
$q_u = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username_aktif'");
$d_u = mysqli_fetch_assoc($q_u);

// Handler Proses Update Akun
if (isset($_POST['update_akun'])) {
    $nama_lengkap = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
    $telepon = mysqli_real_escape_string($koneksi, trim($_POST['telepon']));
    $password_lama = $_POST['password_lama'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_pass = $_POST['konfirmasi_pass'] ?? '';

    $nama_file_foto = $d_u['foto'] ?? '';

    // Handle Upload Foto Profil (Patokan dari Portal Siswa)
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['foto']['tmp_name'];
        $nama_asli = $_FILES['foto']['name'];
        $ekstensi = strtolower(pathinfo($nama_asli, PATHINFO_EXTENSION));
        $ekstensi_boleh = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ekstensi, $ekstensi_boleh)) {
            $nama_file_foto = "profile_staff_" . $username_aktif . "_" . time() . "." . $ekstensi;
            $folder_tujuan = "uploads/" . $nama_file_foto;
            
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }

            if (move_uploaded_file($file_tmp, $folder_tujuan)) {
                // Berhasil upload
            } else {
                $error = "Gagal mengunggah file foto.";
            }
        } else {
            $error = "Format foto harus JPG, JPEG, PNG, atau WEBP!";
        }
    }

    if (!$error) {
        $update_pass = ""; 

        // Jika ingin mengubah password
        if (!empty($password_lama) || !empty($password_baru)) {
            if ($password_lama === $d_u['password']) {
                if ($password_baru === $konfirmasi_pass) {
                    $update_pass = ", password = '$password_baru'";
                } else {
                    $error = "Konfirmasi password baru tidak cocok!";
                }
            } else {
                $error = "Password lama yang Anda masukkan salah!";
            }
        }

        if (!$error) {
            $sql_update = "UPDATE users SET nama_lengkap = '$nama_lengkap', telepon = '$telepon', foto = '$nama_file_foto' $update_pass WHERE username = '$username_aktif'";
            if (mysqli_query($koneksi, $sql_update)) {
                $success = "Profil dan informasi akun berhasil diperbarui!";
                // Refresh data staff
                $q_u = mysqli_query($koneksi, "SELECT * FROM users WHERE username = '$username_aktif'");
                $d_u = mysqli_fetch_assoc($q_u);
            } else {
                $error = "Gagal memperbarui database: " . mysqli_error($koneksi);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Akun Staff - DLWM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-brand">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard Utama</a></li>
            <li><a href="kelola_buku.php">Kelola Stok & Buku</a></li>
            <li class="active"><a href="kelola_akun.php">Kelola Akun Saya</a></li>
            <li><a href="kelola_siswa.php">Kelola Akun Siswa</a></li>
        </ul>
        <div class="sidebar-footer"><span>Administrator</span></div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <h2>Kelola Akun & Profil Staff</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600;">Logout</a>
        </div>

        <div class="container" style="max-width: 800px;">
            <div class="card">
                <h3>Pengaturan Profil & Keamanan Akun</h3>
                <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 20px;">Perbarui informasi identitas, nomor telepon, foto profil, serta sandi akun Anda.</p>

                <?php if ($error): ?><div style="background: #fee2e2; color: #dc2626; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem;"><?= $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div style="background: #dcfce7; color: #16a34a; padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem;"><?= $success; ?></div><?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    
                    <!-- Preview Foto Profil -->
                    <div style="text-align: center; margin-bottom: 25px;">
                        <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; margin: 0 auto 10px auto; background: #e2e8f0; border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($d_u['foto']) && file_exists("uploads/" . $d_u['foto'])): ?>
                                <img src="uploads/<?= $d_u['foto']; ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <span style="font-size: 2rem; color: #64748b;">👤</span>
                            <?php endif; ?>
                        </div>
                        <label style="font-size: 0.85rem; color: #64748b; cursor: pointer;">
                            Ganti Foto Profil (JPG, PNG, WEBP)
                            <input type="file" name="foto" accept=".jpg, .jpeg, .png, .webp" style="display: block; margin: 5px auto 0 auto; font-size: 0.8rem;">
                        </label>
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Username (Tidak dapat diubah)</label>
                        <input type="text" value="<?= htmlspecialchars($d_u['username']); ?>" disabled style="background-color: #f1f5f9; cursor: not-allowed;">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($d_u['nama_lengkap']); ?>" required>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Nomor Telepon / WhatsApp</label>
                        <input type="text" name="telepon" value="<?= htmlspecialchars($d_u['telepon'] ?? ''); ?>" placeholder="Contoh: 081234567890">
                    </div>

                    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;">

                    <h4 style="margin-bottom: 15px; color: #334155; font-size: 1rem;">Ubah Password (Opsional)</h4>
                    <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 15px;">Kosongkan bagian ini jika Anda tidak ingin mengganti password lama.</p>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Password Lama</label>
                        <input type="password" name="password_lama" placeholder="Masukkan password saat ini jika ingin mengganti">
                    </div>

                    <div class="form-group" style="margin-bottom: 15px;">
                        <label>Password Baru</label>
                        <input type="password" name="password_baru" placeholder="Masukkan password baru">
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="konfirmasi_pass" placeholder="Ulangi password baru">
                    </div>

                    <button type="submit" name="update_akun" class="btn" style="width: 100%; padding: 12px; font-weight: 600;">Simpan Perubahan Akun</button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>