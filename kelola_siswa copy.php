<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$error = '';
$success = '';

// Tentukan tab aktif (default ke 'tambah')
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'tambah';

// Daftar pilihan jurusan
$daftar_jurusan = [
    'Teknik Komputer dan Jaringan',
    'Rekayasa Program Lunak',
    'Akuntansi',
    'Teknik Kendaraan Ringan',
    'Desain Komunikasi dan Visual'
];

// Handler Tambah Siswa Baru
if (isset($_POST['tambah_siswa'])) {
    $npm = mysqli_real_escape_string($koneksi, trim($_POST['npm']));
    $nama_lengkap = mysqli_real_escape_string($koneksi, trim($_POST['nama_lengkap']));
    $jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
    $jurusan = mysqli_real_escape_string($koneksi, $_POST['jurusan']);
    $password = mysqli_real_escape_string($koneksi, $_POST['password']); // Password awal akun siswa

    // Validasi panjang NPM dan Nama
    if (strlen($npm) > 12) {
        $error = "NPM maksimal 12 digit!";
        $tab = 'tambah';
    } elseif (strlen($nama_lengkap) > 50) {
        $error = "Nama lengkap maksimal 50 karakter!";
        $tab = 'tambah';
    } elseif (!in_array($jurusan, $daftar_jurusan)) {
        $error = "Pilihan jurusan tidak valid!";
        $tab = 'tambah';
    } else {
        // Cek apakah NPM sudah terdaftar
        $cek = mysqli_query($koneksi, "SELECT * FROM students WHERE npm = '$npm'");
        if (mysqli_num_rows($cek) > 0) {
            $error = "NPM tersebut sudah terdaftar di sistem!";
            $tab = 'tambah';
        } else {
            // Simpan ke database (Pastikan tabel students memiliki kolom jenis_kelamin dan jurusan)
            $query = "INSERT INTO students (npm, nama_lengkap, jenis_kelamin, jurusan, password) VALUES ('$npm', '$nama_lengkap', '$jenis_kelamin', '$jurusan', '$password')";
            if (mysqli_query($koneksi, $query)) {
                $success = "Akun siswa berhasil didaftarkan!";
                $tab = 'daftar'; // Otomatis pindah ke tab daftar setelah sukses
            } else {
                $error = "Gagal mendaftarkan siswa: " . mysqli_error($koneksi);
                $tab = 'tambah';
            }
        }
    }
}

// Handler Hapus Siswa
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($koneksi, "DELETE FROM students WHERE id = $id");
    header("Location: kelola_siswa.php?tab=daftar");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Akun Siswa - DLWM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- SIDEBAR NAVIGATION -->
    <div class="sidebar">
        <div class="sidebar-brand">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard Utama</a></li>
            <li><a href="kelola_buku.php">Kelola Stok & Buku</a></li>
            <li class="active"><a href="kelola_siswa.php">Kelola Akun Siswa</a></li>
            <li><a href="kelola_akun.php">Kelola Akun Staff</a></li>
        </ul>
        <div class="sidebar-footer">
            <span>Staff: <strong><?= htmlspecialchars($_SESSION['user']); ?></strong></span>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <h2 style="font-size: 1.2rem; color: #1e293b;">Manajemen Akun Siswa</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Logout</a>
        </div>

        <div class="container">

            <!-- TAB NAVIGATION BUTTONS -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <a href="kelola_siswa.php?tab=tambah" class="btn" style="background: <?= ($tab == 'tambah') ? '#0284c7' : '#64748b'; ?>;">Tambah Siswa Baru</a>
                <a href="kelola_siswa.php?tab=daftar" class="btn" style="background: <?= ($tab == 'daftar') ? '#0284c7' : '#64748b'; ?>;">Daftar Siswa Terdaftar</a>
            </div>

            <?php if ($tab == 'tambah'): ?>
                <!-- FORM PENDAFTARAN SISWA -->
                <div class="card">
                    <h3>Daftarkan Akun Siswa Baru</h3>
                    
                    <?php if ($error): ?>
                        <p style="color: #991b1b; background: #fee2e2; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem;"><?= $error; ?></p>
                    <?php endif; ?>

                    <?php if ($success && $tab == 'tambah'): ?>
                        <p style="color: #166534; background: #dcfce7; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem;"><?= $success; ?></p>
                    <?php endif; ?>

                    <form action="kelola_siswa.php?tab=tambah" method="POST" style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>NIS (Maks 12 Digit)</label>
                            <input type="text" name="npm" maxlength="12" pattern="\d{1,12}" required placeholder="Contoh: 202343500123">
                        </div>
                        <div class="form-group" style="flex: 2; min-width: 250px;">
                            <label>Nama Lengkap (Maks 50 Karakter)</label>
                            <input type="text" name="nama_lengkap" maxlength="50" required placeholder="Nama lengkap siswa">
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 150px;">
                            <label>Jenis Kelamin</label>
                            <select name="jenis_kelamin" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;">
                                <option value="">-- Pilih --</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 2; min-width: 220px;">
                            <label>Jurusan</label>
                            <select name="jurusan" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;">
                                <option value="">-- Pilih Jurusan --</option>
                                <?php foreach ($daftar_jurusan as $jrs): ?>
                                    <option value="<?= $jrs; ?>"><?= $jrs; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Password Awal Siswa</label>
                            <input type="password" name="password" required placeholder="Password login siswa">
                        </div>
                        <div style="flex: 100%;">
                            <button type="submit" name="tambah_siswa" class="btn">Daftarkan Siswa</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- TABEL DAFTAR SISWA -->
                <div class="card">
                    <h3>Daftar Siswa Terdaftar</h3>
                    
                    <?php if ($success && $tab == 'daftar'): ?>
                        <p style="color: #166534; background: #dcfce7; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem;"><?= $success; ?></p>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="spreadsheet">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">No</th>
                                    <th class="col-npm">NIS</th>
                                    <th class="col-nama">Nama Lengkap</th>
                                    <th style="width: 130px;">Jenis Kelamin</th>
                                    <th style="width: 220px;">Jurusan</th>
                                    <th style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $res = mysqli_query($koneksi, "SELECT * FROM students ORDER BY id DESC");
                                if (mysqli_num_rows($res) == 0): ?>
                                    <tr><td colspan="6" style="text-align: center; color: #64748b;">Belum ada akun siswa yang terdaftar.</td></tr>
                                <?php else: 
                                    while ($s = mysqli_fetch_assoc($res)):
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td class="col-npm"><?= htmlspecialchars($s['npm']); ?></td>
                                        <td class="col-nama"><?= htmlspecialchars($s['nama_lengkap']); ?></td>
                                        <td><?= htmlspecialchars($s['jenis_kelamin'] ?? '-'); ?></td>
                                        <td>
                                            <span class="badge" style="display: inline-block; background:#f1f5f9; color:#0369a1; border:1px solid #cbd5e1; padding:3px 8px; border-radius:4px; font-size:0.78rem;"><?= htmlspecialchars($s['jurusan'] ?? '-'); ?></span>
                                        </td>
                                        <td>
                                            <a href="kelola_siswa.php?hapus=<?= $s['id']; ?>&tab=daftar" class="btn" style="background:#ef4444; padding:4px 8px; font-size:0.75rem;" onclick="return confirm('Hapus akun siswa ini?')">Hapus</a>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>