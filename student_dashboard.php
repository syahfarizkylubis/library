<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['student_login']) || $_SESSION['student_login'] !== true) {
    header("Location: student_login.php");
    exit;
}

$npm_aktif = $_SESSION['student_npm'];

// Ambil data siswa termasuk foto profil dan jurusan
$q_siswa = mysqli_query($koneksi, "SELECT * FROM students WHERE npm = '$npm_aktif'");
$data_siswa = mysqli_fetch_assoc($q_siswa);

// Aturan Denda
define('BATAS_PINJAM_HARI', 7);
define('DENDA_PER_HARI', 2000);

// Tangkap tab aktif dari URL (default ke 'aktif')
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'aktif';

// Hitung Statistik Buku Aktif & Denda Berjalan (Dengan JOIN ke tabel buku)
$q_stat_aktif = mysqli_query($koneksi, "SELECT peminjaman.*, buku.nama_buku 
                                       FROM peminjaman 
                                       JOIN buku ON peminjaman.buku_id = buku.id 
                                       WHERE peminjaman.npm = '$npm_aktif' AND peminjaman.status = 'Dipinjam' 
                                       ORDER BY tgl_pinjam ASC");
$jumlah_buku_dipinjam = mysqli_num_rows($q_stat_aktif);

$total_denda_berjalan = 0;
$tgl_sekarang_obj = new DateTime(date('Y-m-d'));
// Query ulang untuk perhitungan denda berjalan
$q_denda_calc = mysqli_query($koneksi, "SELECT * FROM peminjaman WHERE npm = '$npm_aktif' AND status = 'Dipinjam'");
while ($row_stat = mysqli_fetch_assoc($q_denda_calc)) {
    $tgl_p_obj = new DateTime($row_stat['tgl_pinjam']);
    $selisih = $tgl_p_obj->diff($tgl_sekarang_obj)->days;
    $terlambat = $selisih - BATAS_PINJAM_HARI;
    if ($terlambat > 0) {
        $total_denda_berjalan += ($terlambat * DENDA_PER_HARI);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Siswa - Digital Library Web Manager (DLWM)</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-avatar-mini {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #38bdf8;
            margin-right: 8px;
            vertical-align: middle;
        }
        .dashboard-tab-content {
            display: none;
        }
        .dashboard-tab-content.active {
            display: block;
        }
        .uniform-btn {
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
            display: inline-block;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .uniform-btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR NAVIGATION SISWA -->
    <div class="sidebar">
        <div class="sidebar-brand">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li class="active"><a href="student_dashboard.php">Dashboard Utama</a></li>
            <li><a href="katalog_siswa.php">Katalog Buku</a></li>
            <li><a href="student_keuangan.php">Keuangan & Denda</a></li>
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

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <h2 style="font-size: 1.2rem; color: #1e293b;">Dashboard Siswa - Buku Aktif & Riwayat</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Logout</a>
        </div>

        <div class="container">

            <!-- KARTU RINGKASAN STATISTIK SISWA -->
            <div class="stat-container" style="display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap;">
                <div class="stat-card" style="flex: 1; min-width: 200px; background: #fff; padding: 20px; border-radius: 8px; border-left: 4px solid #0284c7; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <h4 style="font-size: 0.85rem; color: #64748b; margin-bottom: 5px;">Buku Dipinjam Saat Ini</h4>
                    <div style="font-size: 1.5rem; font-weight: bold; color: #0f172a;"><?= $jumlah_buku_dipinjam; ?> / 2 Buku</div>
                </div>
                <div class="stat-card" style="flex: 1; min-width: 200px; background: #fff; padding: 20px; border-radius: 8px; border-left: 4px solid <?= ($total_denda_berjalan > 0) ? '#dc2626' : '#10b981'; ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <h4 style="font-size: 0.85rem; color: #64748b; margin-bottom: 5px;">Total Denda Berjalan</h4>
                    <div style="font-size: 1.5rem; font-weight: bold; color: <?= ($total_denda_berjalan > 0) ? '#dc2626' : '#059669'; ?>;">Rp <?= number_format($total_denda_berjalan, 0, ',', '.'); ?></div>
                </div>
                <div class="stat-card" style="flex: 1; min-width: 220px; background: #fff; padding: 20px; border-radius: 8px; border-left: 4px solid #8b5cf6; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <h4 style="font-size: 0.85rem; color: #64748b; margin-bottom: 5px;">Jurusan Anda</h4>
                    <div style="font-size: 0.95rem; font-weight: bold; color: #0f172a;"><?= htmlspecialchars($data_siswa['jurusan'] ?? '-'); ?></div>
                </div>
            </div>

            <!-- TOMBOL NAVIGASI TAB & PINTASAN CEPAT (SERAGAM) -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <a href="student_dashboard.php?tab=aktif" class="uniform-btn" style="background: <?= ($active_tab == 'aktif') ? '#0284c7' : '#334155'; ?>;">📖 Pinjaman Aktif</a>
                <a href="student_dashboard.php?tab=riwayat" class="uniform-btn" style="background: <?= ($active_tab == 'riwayat') ? '#0284c7' : '#334155'; ?>;">📚 Riwayat Peminjaman</a>
                <a href="katalog_siswa.php" class="uniform-btn" style="background: #334155;">📖 Cari Buku di Katalog</a>
                <a href="student_keuangan.php" class="uniform-btn" style="background: #334155;">💰 Cek Rincian Denda & Keuangan</a>
            </div>

            <!-- KONTEN TAB 1: PINJAMAN AKTIF -->
            <div id="tab-aktif" class="dashboard-tab-content <?= ($active_tab == 'aktif') ? 'active' : ''; ?>">
                <div class="card">
                    <h3>Buku yang Sedang Anda Pinjam</h3>
                    <div class="table-responsive">
                        <table class="spreadsheet">
                            <thead>
                                <tr>
                                    <th>Nama Buku</th>
                                    <th class="col-date">Tgl Pinjam</th>
                                    <th class="col-date">Batas Kembali</th>
                                    <th class="col-status">Status / Keterlambatan</th>
                                    <th class="col-denda">Estimasi Denda</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($q_stat_aktif) == 0): ?>
                                    <tr><td colspan="5" style="text-align: center; color: #64748b;">Anda sedang tidak meminjam buku apapun saat ini.</td></tr>
                                <?php else: 
                                    mysqli_data_seek($q_stat_aktif, 0); // Reset pointer hasil query
                                    while ($row = mysqli_fetch_assoc($q_stat_aktif)):
                                        $tgl_pinjam_obj = new DateTime($row['tgl_pinjam']);
                                        $tgl_batas = clone $tgl_pinjam_obj;
                                        $tgl_batas->modify('+' . BATAS_PINJAM_HARI . ' days');

                                        $selisih = $tgl_pinjam_obj->diff($tgl_sekarang_obj)->days;
                                        $terlambat_hari = $selisih - BATAS_PINJAM_HARI;
                                        $denda = 0;

                                        if ($terlambat_hari > 0) {
                                            $denda = $terlambat_hari * DENDA_PER_HARI;
                                            $status_keterlambatan = "<span class='badge badge-danger'>Terlambat {$terlambat_hari} Hari</span>";
                                        } else {
                                            $status_keterlambatan = "<span class='badge badge-success'>Tepat Waktu / Aman</span>";
                                        }
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['nama_buku']); ?></strong></td>
                                        <td><?= $row['tgl_pinjam']; ?></td>
                                        <td><?= $tgl_batas->format('Y-m-d'); ?></td>
                                        <td><?= $status_keterlambatan; ?></td>
                                        <td><strong>Rp <?= number_format($denda, 0, ',', '.'); ?></strong></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- KONTEN TAB 2: RIWAYAT PEMINJAMAN -->
            <div id="tab-riwayat" class="dashboard-tab-content <?= ($active_tab == 'riwayat') ? 'active' : ''; ?>">
                <div class="card">
                    <h3>Riwayat Peminjaman Buku Sebelumnya</h3>
                    <div class="table-responsive">
                        <table class="spreadsheet">
                            <thead>
                                <tr>
                                    <th>Nama Buku</th>
                                    <th class="col-date">Tgl Pinjam</th>
                                    <th class="col-date">Tgl Dikembalikan</th>
                                    <th class="col-status">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q_riwayat = mysqli_query($koneksi, "SELECT peminjaman.*, buku.nama_buku 
                                                               FROM peminjaman 
                                                               JOIN buku ON peminjaman.buku_id = buku.id 
                                                               WHERE peminjaman.npm = '$npm_aktif' AND peminjaman.status = 'Dikembalikan' 
                                                               ORDER BY id DESC");

                                if (mysqli_num_rows($q_riwayat) == 0): ?>
                                    <tr><td colspan="4" style="text-align: center; color: #64748b;">Belum ada riwayat pengembalian buku.</td></tr>
                                <?php else: 
                                    while ($hist = mysqli_fetch_assoc($q_riwayat)):
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($hist['nama_buku']); ?></td>
                                        <td><?= $hist['tgl_pinjam']; ?></td>
                                        <td><?= $hist['tgl_kembali'] ?? '-'; ?></td>
                                        <td><span class='badge badge-success'>Selesai / Dikembalikan</span></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>