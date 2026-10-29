<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['student_login']) || $_SESSION['student_login'] !== true) {
    header("Location: student_login.php");
    exit;
}

$npm_aktif = $_SESSION['student_npm'];
$q_siswa = mysqli_query($koneksi, "SELECT * FROM students WHERE npm = '$npm_aktif'");
$data_siswa = mysqli_fetch_assoc($q_siswa);

// Hitung total akumulasi Late Fees dari semua buku yang sedang dipinjam dan terlambat
define('BATAS_PINJAM_HARI', 7);
define('DENDA_PER_HARI', 2000);

$q_denda = mysqli_query($koneksi, "SELECT tgl_pinjam FROM peminjaman WHERE npm = '$npm_aktif' AND status = 'Dipinjam'");
$total_tunggakan = 0;

while ($row = mysqli_fetch_assoc($q_denda)) {
    $tgl_pinjam = new DateTime($row['tgl_pinjam']);
    $tgl_sekarang = new DateTime(date('Y-m-d'));
    $selisih = $tgl_pinjam->diff($tgl_sekarang)->days;
    $terlambat_hari = $selisih - BATAS_PINJAM_HARI;
    if ($terlambat_hari > 0) {
        $total_tunggakan += ($terlambat_hari * DENDA_PER_HARI);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Keuangan & Denda - DLWM</title>
    <link rel="stylesheet" href="style.css">
    <style>
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
            <li class="active"><a href="student_keuangan.php">Keuangan & Denda</a></li>
            <li><a href="student_kelola_akun.php">Kelola Akun</a></li>
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
            <h2 style="font-size: 1.2rem; color: #1e293b;">Keuangan & Status Denda</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Logout</a>
        </div>

        <div class="container">

            <!-- KOTAK INFORMASI TOTAL TUNGGAKAN -->
            <div class="stat-container">
                <div class="stat-card <?= $total_tunggakan > 0 ? 'orange' : 'green'; ?>" style="flex: 100%;">
                    <h4>Total Tunggakan Denda Aktif</h4>
                    <div class="number" style="color: <?= $total_tunggakan > 0 ? '#dc2626' : '#166534'; ?>;">
                        Rp <?= number_format($total_tunggakan, 0, ',', '.'); ?>
                    </div>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 5px;">
                        <?= $total_tunggakan > 0 ? 'Harap segera melunasi tunggakan denda keterlambatan ke bagian staff perpustakaan.' : 'Anda tidak memiliki tunggakan denda saat ini. Kerja bagus!'; ?>
                    </p>
                </div>
            </div>

            <!-- TABEL SPREADSHEET RIWAYAT PEMBAYARAN DENDA -->
            <div class="card">
                <h3>Riwayat Pembayaran Denda</h3>
                <div class="table-responsive">
                    <table class="spreadsheet">
                        <thead>
                            <tr>
                                <th style="width: 60px;">No</th>
                                <th class="col-date">Tanggal Bayar</th>
                                <th>Jumlah Dibayar</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            $q_pay = mysqli_query($koneksi, "SELECT * FROM denda_payments WHERE npm = '$npm_aktif' ORDER BY id DESC");
                            if (mysqli_num_rows($q_pay) == 0): ?>
                                <tr><td colspan="4" style="text-align: center; color: #64748b;">Belum ada riwayat pembayaran denda tercatat.</td></tr>
                            <?php else: 
                                while ($p = mysqli_fetch_assoc($q_pay)):
                            ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= $p['tgl_bayar']; ?></td>
                                    <td><strong>Rp <?= number_format($p['jumlah_bayar'], 0, ',', '.'); ?></strong></td>
                                    <td><?= htmlspecialchars($p['keterangan']); ?></td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</body>
</html>