<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['student_login']) || $_SESSION['student_login'] !== true) {
    header("Location: student_login.php");
    exit;
}

$npm_aktif = $_SESSION['student_npm'];
$success = '';
$error = '';

// Handler Update Akun Siswa (Telepon, Foto, Password)
if (isset($_POST['update_akun'])) {
    $telepon = mysqli_real_escape_string($koneksi, trim($_POST['telepon']));
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_pass = $_POST['konfirmasi_pass'];

    // Ambil data lama
    $q_s = mysqli_query($koneksi, "SELECT * FROM students WHERE npm = '$npm_aktif'");
    $d_s = mysqli_fetch_assoc($q_s);

    // Proses Upload Foto Profil jika ada
    $nama_file_foto = $d_s['foto'] ?? '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['foto']['tmp_name'];
        $nama_asli = $_FILES['foto']['name'];
        $ekstensi = strtolower(pathinfo($nama_asli, PATHINFO_EXTENSION));
        $ekstensi_boleh = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ekstensi, $ekstensi_boleh)) {
            $nama_file_foto = "profile_" . $npm_aktif . "_" . time() . "." . $ekstensi;
            $folder_tujuan = "uploads/" . $nama_file_foto;
            
            // Buat folder uploads jika belum ada
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }

            move_uploaded_file($file_tmp, $folder_tujuan);
        } else {
            $error = "Format foto harus JPG, JPEG, PNG, atau WEBP!";
        }
    }

    if (!$error) {
        // Jika ingin mengubah password
        if (!empty($password_lama) || !empty($password_baru)) {
            if ($password_lama === $d_s['password']) {
                if ($password_baru === $konfirmasi_pass) {
                    $update_pass = ", password = '$password_baru'";
                } else {
                    $error = "Konfirmasi password baru tidak cocok!";
                }
            } else {
                $error = "Password lama yang Anda masukkan salah!";
            }
        } else {
            $update_pass = "";
        }

        if (!$error) {
            $sql_update = "UPDATE students SET telepon = '$telepon', foto = '$nama_file_foto' $update_pass WHERE npm = '$npm_aktif'";
            if (mysqli_query($koneksi, $sql_update)) {
                $success = "Profil dan informasi akun berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui database: " . mysqli_error($koneksi);
            }
        }
    }
}

// Ambil data terbaru siswa
$q_siswa = mysqli_query($koneksi, "SELECT * FROM students WHERE npm = '$npm_aktif'");
$data_siswa = mysqli_fetch_assoc($q_siswa);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Akun - Portal Siswa DLWM</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-preview {
            width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #38bdf8; margin-bottom: 15px;
        }
        .profile-avatar-mini {
            width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #38bdf8; margin-right: 8px; vertical-align: middle;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li><a href="student_dashboard.php">Dashboard Utama</a></li>
            <li><a href="katalog_siswa.php">Katalog Buku</a></li>
            <li><a href="student_keuangan.php">Keuangan & Denda</a></li>
            <li class="active"><a href="student_kelola_akun.php">Kelola Akun</a></li>
        </ul>
        <div class="sidebar-footer" style="display: flex; align-items: center;">
            <?php if (!empty($data_siswa['foto']) && file_exists("uploads/" . $data_siswa['foto'])): ?>
                <img src="uploads/<?= $data_siswa['foto']; ?>" class="profile-avatar-mini">
            <?php else: ?>
                <div style="width:35px; height:35px; background:#334155; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-right:8px; font-weight:bold; color:#fff;">
                    <?= strtoupper(substr($data_siswa['nama_lengkap'], 0, 1)); ?>
                </div>
            <?php endif; ?>
            <span style="font-size: 0.85rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><strong><?= htmlspecialchars($data_siswa['nama_lengkap']); ?></strong></span>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h2 style="font-size: 1.2rem; color: #1e293b;">Kelola Profil & Keamanan Akun</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Logout</a>
        </div>

        <div class="container">

            <div class="card" style="max-width: 700px; margin: 0 auto;">
                <h3>Pengaturan Akun Siswa</h3>
                
                <?php if ($error): ?>
                    <p style="color: #991b1b; background: #fee2e2; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem;"><?= $error; ?></p>
                <?php endif; ?>

                <?php if ($success): ?>
                    <p style="color: #166534; background: #dcfce7; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem;"><?= $success; ?></p>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <?php if (!empty($data_siswa['foto']) && file_exists("uploads/" . $data_siswa['foto'])): ?>
                            <img src="uploads/<?= $data_siswa['foto']; ?>" class="profile-preview">
                        <?php else: ?>
                            <div style="width: 100px; height: 100px; background: #cbd5e1; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #475569; margin-bottom: 15px;">
                                <?= strtoupper(substr($data_siswa['nama_lengkap'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label style="font-size: 0.85rem; color: #64748b; display: block; margin-bottom: 5px;">Ganti Foto Profil (JPG/PNG)</label>
                            <input type="file" name="foto" accept="image/*" style="font-size: 0.85rem;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>NPM (Nomor Pokok Mahasiswa/Siswa - Tidak Dapat Diubah)</label>
                        <input type="text" value="<?= htmlspecialchars($data_siswa['npm']); ?>" disabled style="background-color: #f8fafc; color: #64748b;">
                    </div>

                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" value="<?= htmlspecialchars($data_siswa['nama_lengkap']); ?>" disabled style="background-color: #f8fafc; color: #64748b;">
                    </div>

                    <div class="form-group">
                        <label>Nomor Telepon / WhatsApp</label>
                        <input type="text" name="telepon" value="<?= htmlspecialchars($data_siswa['telepon'] ?? ''); ?>" placeholder="Contoh: 081234567890">
                    </div>

                    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                    <h4 style="font-size: 1rem; color: #1e293b; margin-bottom: 15px;">Ubah Password (Opsional)</h4>

                    <div class="form-group">
                        <label>Password Lama</label>
                        <input type="password" name="password_lama" placeholder="Isi jika ingin mengganti password">
                    </div>

                    <div class="form-group">
                        <label>Password Baru</label>
                        <input type="password" name="password_baru" placeholder="Password baru">
                    </div>

                    <div class="form-group">
                        <label>Konfirmasi Password Baru</label>
                        <input type="password" name="konfirmasi_pass" placeholder="Ulangi password baru">
                    </div>

                    <button type="submit" name="update_akun" class="btn" style="width: 100%; margin-top: 10px;">Simpan Perubahan</button>
                </form>
            </div>

        </div>
    </div>

</body>
</html>