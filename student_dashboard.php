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

// Aturan Denda (Sinkron dengan aturan perpustakaan)
define('BATAS_PINJAM_HARI', 7);
define('DENDA_PER_HARI', 2000);
define('MAKS_DENDA', 250000);

/**
 * Fungsi Menghitung Hari Keterlambatan Tanpa Hari Minggu
 */
function hitungTerlambatTanpaMinggu($tgl_pinjam_str, $tgl_akhir_str) {
    $tgl_pinjam = new DateTime($tgl_pinjam_str);
    $tgl_akhir = new DateTime($tgl_akhir_str);

    $tgl_jatuh_tempo = clone $tgl_pinjam;
    $tgl_jatuh_tempo->modify('+' . BATAS_PINJAM_HARI . ' days');

    if ($tgl_akhir <= $tgl_jatuh_tempo) {
        return 0;
    }

    $hari_terlambat = 0;
    $periode_mulai = clone $tgl_jatuh_tempo;
    $periode_mulai->modify('+1 day');

    $periode_akhir = clone $tgl_akhir;
    $periode_akhir->modify('+1 day');

    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($periode_mulai, $interval, $periode_akhir);

    foreach ($daterange as $date) {
        if ((int)$date->format('N') !== 7) {
            $hari_terlambat++;
        }
    }

    return $hari_terlambat;
}

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
$jumlah_buku_overdue = 0;
$tgl_sekarang_str = date('Y-m-d');
$tgl_sekarang_obj = new DateTime($tgl_sekarang_str);

// Query perhitungan denda berjalan
$q_denda_calc = mysqli_query($koneksi, "SELECT * FROM peminjaman WHERE npm = '$npm_aktif' AND status = 'Dipinjam'");
while ($row_stat = mysqli_fetch_assoc($q_denda_calc)) {
    $terlambat = hitungTerlambatTanpaMinggu($row_stat['tgl_pinjam'], $tgl_sekarang_str);
    if ($terlambat > 0) {
        $jumlah_buku_overdue++;
        $denda_item = min($terlambat * DENDA_PER_HARI, MAKS_DENDA);
        $total_denda_berjalan += $denda_item;
    }
}

// --- DATA GRAFIK BATANG: RIWAYAT BUKU YANG DIBACA PER BULAN (6 BULAN / 1 SEMESTER TERAKHIR) ---
$nama_bulan_id = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
    7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
];

$labels_bulan_baca = [];
$data_buku_selesai_per_bulan = [];
$data_buku_dipinjam_per_bulan = [];

for ($m = 5; $m >= 0; $m--) {
    $dt_bulan = new DateTime(date('Y-m-01'));
    $dt_bulan->modify("-{$m} months");
    $bln_num = (int)$dt_bulan->format('n');
    $thn_num = (int)$dt_bulan->format('Y');

    $labels_bulan_baca[] = $nama_bulan_id[$bln_num] . ' ' . $thn_num;

    // Jumlah buku yang ditamatkan (Dikembalikan) pada bulan tersebut
    $q_tamat = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE npm = '$npm_aktif' AND status = 'Dikembalikan' AND MONTH(COALESCE(tgl_kembali, tgl_pinjam)) = $bln_num AND YEAR(COALESCE(tgl_kembali, tgl_pinjam)) = $thn_num");
    $data_buku_selesai_per_bulan[] = (int)(mysqli_fetch_assoc($q_tamat)['total'] ?? 0);

    // Total buku yang dipinjam pada bulan tersebut
    $q_pinjam_bln = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE npm = '$npm_aktif' AND MONTH(tgl_pinjam) = $bln_num AND YEAR(tgl_pinjam) = $thn_num");
    $data_buku_dipinjam_per_bulan[] = (int)(mysqli_fetch_assoc($q_pinjam_bln)['total'] ?? 0);
}

// Total seluruh buku yang sudah ditamatkan siswa (untuk Achievement Badge)
$q_total_tamat = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE npm = '$npm_aktif' AND status = 'Dikembalikan'");
$total_buku_tamat = (int)(mysqli_fetch_assoc($q_total_tamat)['total'] ?? 0);

if ($total_buku_tamat >= 10) {
    $badge_achievement = "🏆 Kutu Buku Teladan";
    $warna_achievement = "#b45309";
    $bg_achievement = "#fef3c7";
} elseif ($total_buku_tamat >= 3) {
    $badge_achievement = "🌟 Pembaca Aktif";
    $warna_achievement = "#0369a1";
    $bg_achievement = "#e0f2fe";
} else {
    $badge_achievement = "🌱 Penjelajah Literasi";
    $warna_achievement = "#059669";
    $bg_achievement = "#d1fae5";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Siswa - Digital Library Web Manager (DLWM)</title>
    <link rel="stylesheet" href="style.css">
    <!-- Pustaka Chart.js untuk Grafik Riwayat Baca Bulanan -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Styling Progress Bar & Badge Warning Status Peminjaman Aktif */
        .loan-progress-wrapper {
            min-width: 190px;
        }
        .loan-progress-track {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
            margin-top: 6px;
        }
        .loan-progress-bar {
            height: 100%;
            border-radius: 999px;
            transition: width 0.3s ease;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .status-pill.safe {
            background: #dcfce7;
            color: #15803d;
        }
        .status-pill.warn {
            background: #fef3c7;
            color: #b45309;
        }
        .status-pill.danger {
            background: #fee2e2;
            color: #b91c1c;
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

            <!-- KARTU RINGKASAN STATISTIK & FINANSIAL DENDA PRIBADI -->
            <div class="stat-container" style="display: flex; gap: 18px; margin-bottom: 20px; flex-wrap: wrap;">
                
                <!-- Kartu 1: Kuota Pinjaman Aktif -->
                <div class="stat-card" style="flex: 1; min-width: 200px; background: #fff; padding: 18px 20px; border-radius: 8px; border-left: 4px solid #0284c7; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <h4 style="font-size: 0.82rem; color: #64748b; margin-bottom: 6px;">Buku Dipinjam Saat Ini</h4>
                    <div style="font-size: 1.45rem; font-weight: bold; color: #0f172a;"><?= $jumlah_buku_dipinjam; ?> / 2 Buku</div>
                    <div style="font-size: 0.78rem; color: #64748b; margin-top: 4px;">
                        <?= ($jumlah_buku_dipinjam < 2) ? 'Masih tersedia kuota ' . (2 - $jumlah_buku_dipinjam) . ' buku' : 'Kuota peminjaman penuh'; ?>
                    </div>
                </div>

                <!-- Kartu 2: Ringkasan Finansial Denda Pribadi -->
                <div class="stat-card" style="flex: 1.3; min-width: 240px; background: #fff; padding: 18px 20px; border-radius: 8px; border-left: 4px solid <?= ($total_denda_berjalan > 0) ? '#dc2626' : '#10b981'; ?>; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h4 style="font-size: 0.82rem; color: #64748b; margin-bottom: 6px;">Ringkasan Finansial Denda Pribadi</h4>
                        <?php if ($total_denda_berjalan > 0): ?>
                            <span class="status-pill danger" style="font-size: 0.7rem; padding: 2px 8px;"><?= $jumlah_buku_overdue; ?> Buku Menunggak</span>
                        <?php else: ?>
                            <span class="status-pill safe" style="font-size: 0.7rem; padding: 2px 8px;">Bebas Tunggakan</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 1.45rem; font-weight: bold; color: <?= ($total_denda_berjalan > 0) ? '#dc2626' : '#059669'; ?>;">
                        Rp <?= number_format($total_denda_berjalan, 0, ',', '.'); ?>
                    </div>
                    <div style="font-size: 0.76rem; color: #64748b; margin-top: 4px; display: flex; justify-content: space-between; align-items: center;">
                        <span><?= ($total_denda_berjalan > 0) ? 'Segera lunasi di petugas perpustakaan' : 'Tidak ada denda berjalan saat ini'; ?></span>
                        <a href="student_keuangan.php" style="color: #0284c7; font-weight: 600; text-decoration: none;">Rincian &rarr;</a>
                    </div>
                </div>

                <!-- Kartu 3: Pencapaian Literasi & Jurusan -->
                <div class="stat-card" style="flex: 1; min-width: 210px; background: #fff; padding: 18px 20px; border-radius: 8px; border-left: 4px solid #8b5cf6; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h4 style="font-size: 0.82rem; color: #64748b; margin-bottom: 6px;">Total Buku Ditamatkan</h4>
                        <span style="background: <?= $bg_achievement; ?>; color: <?= $warna_achievement; ?>; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;">
                            <?= $badge_achievement; ?>
                        </span>
                    </div>
                    <div style="font-size: 1.45rem; font-weight: bold; color: #0f172a;"><?= $total_buku_tamat; ?> Buku</div>
                    <div style="font-size: 0.78rem; color: #64748b; margin-top: 4px;">
                        Jurusan: <strong><?= htmlspecialchars($data_siswa['jurusan'] ?? '-'); ?></strong>
                    </div>
                </div>

            </div>

            <!-- GRAFIK BATANG: RIWAYAT BUKU YANG DIBACA PER BULAN (ACHIEVEMENT LITERASI) -->
            <div class="card" style="margin-bottom: 20px; padding: 18px 20px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
                    <div>
                        <h3 style="margin: 0; font-size: 1rem; color: #1e293b;">📊 Statistik Buku yang Dibaca per Bulan (Semester Ini)</h3>
                        <p style="margin: 4px 0 0 0; font-size: 0.82rem; color: #64748b;">
                            Grafik keaktifan Anda meminjam dan menamatkan buku perpustakaan selama 6 bulan terakhir.
                        </p>
                    </div>
                    <span style="background: <?= $bg_achievement; ?>; color: <?= $warna_achievement; ?>; font-size: 0.8rem; font-weight: 700; padding: 5px 12px; border-radius: 999px;">
                        Status Literasi: <?= $badge_achievement; ?>
                    </span>
                </div>
                <div style="position: relative; height: 210px; width: 100%;">
                    <canvas id="chartRiwayatBacaBulanan"></canvas>
                </div>
            </div>

            <!-- TOMBOL NAVIGASI TAB & PINTASAN CEPAT (SERAGAM) -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <a href="student_dashboard.php?tab=aktif" class="uniform-btn" style="background: <?= ($active_tab == 'aktif') ? '#0284c7' : '#334155'; ?>;">📖 Pinjaman Aktif</a>
                <a href="student_dashboard.php?tab=riwayat" class="uniform-btn" style="background: <?= ($active_tab == 'riwayat') ? '#0284c7' : '#334155'; ?>;">📚 Riwayat Peminjaman</a>
                <a href="katalog_siswa.php" class="uniform-btn" style="background: #334155;">📖 Cari Buku di Katalog</a>
                <a href="student_keuangan.php" class="uniform-btn" style="background: #334155;">💰 Cek Rincian Denda & Keuangan</a>
            </div>

            <!-- KONTEN TAB 1: PINJAMAN AKTIF DENGAN PROGRESS BAR & BADGE WARNING -->
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
                                    <th style="min-width: 220px;">Indikator Masa Peminjaman</th>
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

                                        $terlambat_hari = hitungTerlambatTanpaMinggu($row['tgl_pinjam'], $tgl_sekarang_str);
                                        $denda = 0;

                                        // Hitung Sisa Hari & Progress Bar Visual
                                        if ($tgl_sekarang_obj <= $tgl_batas) {
                                            $sisa_hari = $tgl_sekarang_obj->diff($tgl_batas)->days;
                                            $hari_terpakai = max(0, BATAS_PINJAM_HARI - $sisa_hari);
                                            $persen_bar = min(100, round(($hari_terpakai / BATAS_PINJAM_HARI) * 100));

                                            if ($sisa_hari > 2) {
                                                // Aman (Hijau)
                                                $warna_bar = "#10b981";
                                                $badge_html = "<span class='status-pill safe'>● Aman (Sisa {$sisa_hari} Hari)</span>";
                                            } elseif ($sisa_hari > 0) {
                                                // Mendekati Tenggat: Sisa 1-2 Hari (Kuning/Oranye)
                                                $warna_bar = "#f59e0b";
                                                $badge_html = "<span class='status-pill warn'>⚠️ Segera Kembalikan (Sisa {$sisa_hari} Hari)</span>";
                                            } else {
                                                // Hari Terakhir Jatuh Tempo (Kuning/Oranye Penuh)
                                                $persen_bar = 100;
                                                $warna_bar = "#d97706";
                                                $badge_html = "<span class='status-pill warn'>⏰ Batas Hari Ini!</span>";
                                            }
                                        } else {
                                            // Sudah melewati tanggal batas kembali
                                            $persen_bar = 100;
                                            if ($terlambat_hari > 0) {
                                                $denda = min($terlambat_hari * DENDA_PER_HARI, MAKS_DENDA);
                                                $warna_bar = "#dc2626";
                                                $badge_html = "<span class='status-pill danger'>🚨 Overdue (Terlambat {$terlambat_hari} Hari)</span>";
                                            } else {
                                                // Jatuh tempo Sabtu & hari ini Minggu (Bebas Denda)
                                                $warna_bar = "#10b981";
                                                $badge_html = "<span class='status-pill safe'>● Libur Minggu (Bebas Denda)</span>";
                                            }
                                        }
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['nama_buku']); ?></strong></td>
                                        <td><?= $row['tgl_pinjam']; ?></td>
                                        <td><strong><?= $tgl_batas->format('Y-m-d'); ?></strong></td>
                                        <td>
                                            <div class="loan-progress-wrapper">
                                                <?= $badge_html; ?>
                                                <div class="loan-progress-track">
                                                    <div class="loan-progress-bar" style="width: <?= $persen_bar; ?>%; background: <?= $warna_bar; ?>;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong style="color: <?= ($denda > 0) ? '#dc2626' : '#059669'; ?>;">
                                                Rp <?= number_format($denda, 0, ',', '.'); ?>
                                            </strong>
                                        </td>
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

    <!-- SCRIPT CHART.JS UNTUK RIWAYAT BUKU YANG DIBACA PER BULAN -->
    <script>
        Chart.defaults.devicePixelRatio = Math.max(window.devicePixelRatio || 1, 2);
        Chart.defaults.font.family = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        Chart.defaults.color = '#334155';

        const ctxBaca = document.getElementById('chartRiwayatBacaBulanan').getContext('2d');
        new Chart(ctxBaca, {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels_bulan_baca); ?>,
                datasets: [
                    {
                        label: 'Buku Selesai Dibaca (Dikembalikan)',
                        data: <?= json_encode($data_buku_selesai_per_bulan); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 6,
                        maxBarThickness: 38
                    },
                    {
                        label: 'Total Buku Dipinjam',
                        data: <?= json_encode($data_buku_dipinjam_per_bulan); ?>,
                        backgroundColor: '#0284c7',
                        borderRadius: 6,
                        maxBarThickness: 38
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11.5, weight: '600' } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 5,
                        ticks: {
                            precision: 0,
                            font: { size: 11, weight: '500' },
                            callback: function(val) { return val + ' Buku'; }
                        }
                    },
                    x: {
                        ticks: { font: { size: 11.5, weight: '600' } }
                    }
                }
            }
        });
    </script>

</body>
</html> 