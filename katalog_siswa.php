<?php
session_start();
require 'koneksi_db.php';

// Validasi khusus sesi siswa
if (!isset($_SESSION['student_login']) || $_SESSION['student_login'] !== true) {
    header("Location: student_login.php");
    exit;
}

$npm_aktif = $_SESSION['student_npm'];

// Ambil data siswa termasuk foto profil dan nama lengkap
$q_siswa = mysqli_query($koneksi, "SELECT * FROM students WHERE npm = '$npm_aktif'");
$data_siswa = mysqli_fetch_assoc($q_siswa);

// Daftar genre buku standar
$daftar_genre = [
    'Pendidikan Kejuruan', 
    'Fiksi dan Hiburan', 
    'Biografi', 
    'Pendidikan Agama', 
    'Kewirausahaan', 
    'Pendidikan Umum', 
    'Referensi dan Jurnal'
];

// Ambil data filter dari URL
$keyword = isset($_GET['cari']) ? mysqli_real_escape_string($koneksi, trim($_GET['cari'])) : '';
$genre_filter = isset($_GET['genre']) ? mysqli_real_escape_string($koneksi, trim($_GET['genre'])) : '';
$penerbit_filter = isset($_GET['penerbit']) ? mysqli_real_escape_string($koneksi, trim($_GET['penerbit'])) : '';
$tahun_filter = isset($_GET['tahun']) ? mysqli_real_escape_string($koneksi, trim($_GET['tahun'])) : '';

// Query dinamis dengan pencarian dan filter
$sql_katalog = "SELECT * FROM buku WHERE 1=1";

if (!empty($keyword)) {
    $sql_katalog .= " AND (nama_buku LIKE '%$keyword%' OR barcode LIKE '%$keyword%' OR penulis LIKE '%$keyword%')";
}
if (!empty($genre_filter)) {
    $sql_katalog .= " AND genre = '$genre_filter'";
}
if (!empty($penerbit_filter)) {
    $sql_katalog .= " AND penerbit = '$penerbit_filter'";
}
if (!empty($tahun_filter)) {
    $sql_katalog .= " AND tahun_terbit = '$tahun_filter'";
}

$sql_katalog .= " ORDER BY nama_buku ASC";
$res_katalog = mysqli_query($koneksi, $sql_katalog);

// Ambil daftar penerbit unik untuk dropdown filter
$res_penerbit_opt = mysqli_query($koneksi, "SELECT DISTINCT penerbit FROM buku WHERE penerbit != '' ORDER BY penerbit ASC");

// Ambil daftar tahun terbit unik untuk dropdown filter
$res_tahun_opt = mysqli_query($koneksi, "SELECT DISTINCT tahun_terbit FROM buku WHERE tahun_terbit > 0 ORDER BY tahun_terbit DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Katalog Buku Siswa - DLWM</title>
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
        .catalog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .catalog-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .catalog-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
        }
        .catalog-img-container {
            width: 100%;
            height: 240px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-bottom: 1px solid #e2e8f0;
        }
        .catalog-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .no-cover-text {
            color: #94a3b8;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .catalog-body {
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex-grow: 1;
        }
        .catalog-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
            margin-bottom: 4px;
        }
        .catalog-info {
            font-size: 0.82rem;
            color: #475569;
        }
        .catalog-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 4px;
            width: fit-content;
        }
        .stock-status {
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid #f1f5f9;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .search-box-container {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-box-container input, 
        .search-box-container select {
            padding: 9px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.9rem;
            background: #fff;
            box-sizing: border-box;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li><a href="student_dashboard.php">Dashboard Utama</a></li>
            <li class="active"><a href="katalog_siswa.php">Katalog Buku</a></li>
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

    <div class="main-content">
        <div class="topbar">
            <h2 style="font-size: 1.2rem; color: #1e293b;">Katalog & Pencarian Buku Perpustakaan</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Logout</a>
        </div>

        <div class="container">
            
            <div class="card">
                <h3>Cari & Filter Koleksi Buku Perpustakaan</h3>
                <form action="katalog_siswa.php" method="GET" class="search-box-container">
                    <div style="width: 100%;">
                        <input type="text" name="cari" value="<?= htmlspecialchars($keyword); ?>" placeholder="Masukkan Judul Buku, Penulis, atau Barcode Unik..." style="width: 100%;">
                    </div>

                    <div style="flex: 1; min-width: 200px;">
                        <select name="genre" style="width: 100%;">
                            <option value="">-- Semua Genre --</option>
                            <?php foreach ($daftar_genre as $g): ?>
                                <option value="<?= $g; ?>" <?= ($genre_filter == $g) ? 'selected' : ''; ?>><?= $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 200px;">
                        <select name="penerbit" style="width: 100%;">
                            <option value="">-- Semua Penerbit --</option>
                            <?php while ($p = mysqli_fetch_assoc($res_penerbit_opt)): ?>
                                <option value="<?= htmlspecialchars($p['penerbit']); ?>" <?= ($penerbit_filter == $p['penerbit']) ? 'selected' : ''; ?>><?= htmlspecialchars($p['penerbit']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 150px;">
                        <select name="tahun" style="width: 100%;">
                            <option value="">-- Semua Tahun Terbit --</option>
                            <?php while ($t = mysqli_fetch_assoc($res_tahun_opt)): ?>
                                <option value="<?= $t['tahun_terbit']; ?>" <?= ($tahun_filter == $t['tahun_terbit']) ? 'selected' : ''; ?>><?= $t['tahun_terbit']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="btn" style="padding: 9px 20px;">Terapkan Filter</button>
                        <?php if (!empty($keyword) || !empty($genre_filter) || !empty($penerbit_filter) || !empty($tahun_filter)): ?>
                            <a href="katalog_siswa.php" class="btn" style="background: #64748b; text-decoration: none; display: inline-flex; align-items: center; padding: 9px 16px;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="card">
                <h3>Daftar Buku & Ketersediaan Stok</h3>
                
                <?php if (mysqli_num_rows($res_katalog) == 0): ?>
                    <p style="text-align: center; color: #64748b; padding: 30px;">Tidak ada buku yang ditemukan sesuai dengan kriteria pencarian atau filter yang dipilih.</p>
                <?php else: ?>
                    <div class="catalog-grid">
                        <?php while ($book = mysqli_fetch_assoc($res_katalog)): ?>
                            <div class="catalog-card">
                                <div class="catalog-img-container">
                                    <?php if (!empty($book['cover']) && file_exists('uploads/' . $book['cover'])): ?>
                                        <img src="uploads/<?= $book['cover']; ?>" alt="Cover Buku">
                                    <?php else: ?>
                                        <span class="no-cover-text">Tidak Ada Cover</span>
                                    <?php endif; ?>
                                </div>
                                <div class="catalog-body">
                                    <div class="catalog-title"><?= htmlspecialchars($book['nama_buku']); ?></div>
                                    <div class="catalog-info"><strong>Penulis:</strong> <?= htmlspecialchars($book['penulis']); ?></div>
                                    <div class="catalog-info"><strong>Penerbit:</strong> <?= htmlspecialchars($book['penerbit']); ?></div>
                                    <div class="catalog-info"><strong>No. Inventory:</strong> <span style="font-family: monospace;"><?= $book['barcode']; ?></span></div>
                                    <div class="catalog-info"><strong>Tahun:</strong> <?= $book['tahun_terbit']; ?> | <?= $book['jumlah_halaman']; ?> Hlm</div>
                                    <div><span class="catalog-badge"><?= htmlspecialchars($book['genre']); ?></span></div>
                                    
                                    <div class="stock-status">
                                        <?php if ($book['stok'] > 0): ?>
                                            <span style="color: #059669;">✔ Stok Tersedia: <?= $book['stok']; ?></span>
                                        <?php else: ?>
                                            <span style="color: #dc2626;">✖ Stok Habis</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>

</body>
</html>