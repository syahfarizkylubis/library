<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['headmaster_login'])) { header("Location: headmaster_login.php"); exit; }

// Aturan Denda & Batas Pinjam (Sinkron dengan Dashboard Staff)
if (!defined('BATAS_PINJAM_HARI')) define('BATAS_PINJAM_HARI', 7);
if (!defined('DENDA_PER_HARI')) define('DENDA_PER_HARI', 2000);
if (!defined('MAKS_DENDA')) define('MAKS_DENDA', 250000);

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

// Tab Aktif (Default: 'ringkasan' untuk Fokus Makro, 'analitik' untuk Grafik Mendalam)
$active_hm_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ringkasan';

// 1. Statistik Utama (4 Kartu Metrik Makro)
$q_staff = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE username != 'headmaster'");
$total_staff = mysqli_fetch_assoc($q_staff)['total'] ?? 0;

$q_buku = mysqli_query($koneksi, "SELECT SUM(stok) as total FROM buku");
$total_buku = mysqli_fetch_assoc($q_buku)['total'] ?? 0;

$q_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM students");
$total_siswa = mysqli_fetch_assoc($q_siswa)['total'] ?? 0;

$q_out = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Dipinjam'");
$total_outgoing = mysqli_fetch_assoc($q_out)['total'] ?? 0;

// 2. Statistik Pendapatan Denda & Buku Terlambat (Untuk Alert Krusial & Tab Analitik)
$bulan_ini = date('m');
$tahun_ini = date('Y');
$q_pendapatan = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total FROM denda_payments WHERE MONTH(tgl_bayar) = '$bulan_ini' AND YEAR(tgl_bayar) = '$tahun_ini'");
$total_pendapatan = mysqli_fetch_assoc($q_pendapatan)['total'] ?? 0;

$total_terlambat = 0;
$kat_aman = 0;
$kat_tenggat = 0;
$kat_ringan = 0;
$kat_berat = 0;
$total_potensi_denda_aktif = 0;

$tgl_sekarang_str = date('Y-m-d');
$tgl_sekarang_dt = new DateTime($tgl_sekarang_str);

$q_all_aktif = mysqli_query($koneksi, "SELECT tgl_pinjam FROM peminjaman WHERE status = 'Dipinjam'");
if ($q_all_aktif) {
    while ($r_akt = mysqli_fetch_assoc($q_all_aktif)) {
        $tp_dt = new DateTime($r_akt['tgl_pinjam']);
        $tempo_dt = clone $tp_dt;
        $tempo_dt->modify('+' . BATAS_PINJAM_HARI . ' days');

        if ($tgl_sekarang_dt <= $tempo_dt) {
            $sisa_hr = $tgl_sekarang_dt->diff($tempo_dt)->days;
            if ($sisa_hr > 2) {
                $kat_aman++;
            } else {
                $kat_tenggat++;
            }
        } else {
            $telat_hr = hitungTerlambatTanpaMinggu($r_akt['tgl_pinjam'], $tgl_sekarang_str);
            if ($telat_hr == 0) {
                $kat_tenggat++;
            } elseif ($telat_hr >= 1 && $telat_hr <= 3) {
                $kat_ringan++;
                $total_terlambat++;
            } else {
                $kat_berat++;
                $total_terlambat++;
            }

            if ($telat_hr > 0) {
                $total_potensi_denda_aktif += min($telat_hr * DENDA_PER_HARI, MAKS_DENDA);
            }
        }
    }
}

// --- DATA GRAFIK MODULAR & SIRKULASI HARIAN: MINGGU INI (SENIN - SABTU) ---
$current_day_of_week = (int)date('N'); // 1 (Senin) sampai 7 (Minggu)
$monday_timestamp = strtotime('-' . ($current_day_of_week - 1) . ' days', strtotime(date('Y-m-d')));

$labels_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$data_harian_pinjam = [];
$data_harian_kembali = [];
$data_denda_harian = [];

for ($i = 0; $i < 6; $i++) {
    $target_date = date('Y-m-d', strtotime('+' . $i . ' days', $monday_timestamp));

    // Jumlah Peminjaman Harian
    $q_harian = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE DATE(tgl_pinjam) = '$target_date'");
    $row_harian = $q_harian ? mysqli_fetch_assoc($q_harian) : null;
    $data_harian_pinjam[] = (int)($row_harian['total'] ?? 0);

    // Jumlah Pengembalian Harian
    $q_kembali = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Dikembalikan' AND DATE(tgl_kembali) = '$target_date'");
    $row_kembali = $q_kembali ? mysqli_fetch_assoc($q_kembali) : null;
    $data_harian_kembali[] = (int)($row_kembali['total'] ?? 0);

    // Akumulasi Denda Harian
    $q_denda_hr = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total_denda FROM denda_payments WHERE DATE(tgl_bayar) = '$target_date'");
    $row_denda_hr = $q_denda_hr ? mysqli_fetch_assoc($q_denda_hr) : null;
    $data_denda_harian[] = (int)($row_denda_hr['total_denda'] ?? 0);
}

$total_denda_minggu_ini = array_sum($data_denda_harian);
$idx_hari_ini = min(max($current_day_of_week - 1, 0), 5);
$denda_terkumpul_hari_ini = $data_denda_harian[$idx_hari_ini] ?? 0;

// --- DATA MODULAR: BERDASARKAN JUDUL BUKU (TOP 5) ---
$labels_judul = [];
$data_val_judul = [];
$q_top_buku = mysqli_query($koneksi, "SELECT buku.nama_buku, COUNT(peminjaman.id) as total 
                                      FROM peminjaman 
                                      JOIN buku ON peminjaman.buku_id = buku.id 
                                      GROUP BY peminjaman.buku_id 
                                      ORDER BY total DESC LIMIT 5");
if ($q_top_buku) {
    while ($tb = mysqli_fetch_assoc($q_top_buku)) {
        $labels_judul[] = (strlen($tb['nama_buku']) > 22) ? substr($tb['nama_buku'], 0, 22) . '...' : $tb['nama_buku'];
        $data_val_judul[] = (int)$tb['total'];
    }
}

// --- DATA MODULAR: BERDASARKAN GENRE ---
$labels_genre = [];
$data_val_genre = [];
$q_top_genre = mysqli_query($koneksi, "SELECT buku.genre, COUNT(peminjaman.id) as total 
                                       FROM peminjaman 
                                       JOIN buku ON peminjaman.buku_id = buku.id 
                                       WHERE buku.genre != '' 
                                       GROUP BY buku.genre 
                                       ORDER BY total DESC");
if ($q_top_genre) {
    while ($tg = mysqli_fetch_assoc($q_top_genre)) {
        $labels_genre[] = $tg['genre'];
        $data_val_genre[] = (int)$tg['total'];
    }
}

$labels_genre_top5 = array_slice($labels_genre, 0, 5);
$data_val_genre_top5 = array_slice($data_val_genre, 0, 5);
if (empty($labels_genre_top5)) {
    $labels_genre_top5 = $labels_judul;
    $data_val_genre_top5 = $data_val_judul;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrator - DLWM</title>
    <link rel="stylesheet" href="style.css">
    <!-- Pustaka Chart.js untuk Grafik -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Navigasi Tab Eksekutif (Ringkasan Makro vs Analitik Mendalam) */
        .hm-tab-nav {
            display: flex;
            gap: 12px;
            margin-bottom: 22px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            flex-wrap: wrap;
        }
        .hm-tab-btn {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-size: 0.92rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .hm-tab-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .hm-tab-btn.active {
            background: #b45309;
            color: #ffffff;
            border-color: #b45309;
            box-shadow: 0 2px 4px rgba(180, 83, 9, 0.2);
        }
        .hm-tab-pane {
            display: none;
        }
        .hm-tab-pane.active {
            display: block;
        }

        /* Kartu Peringatan Krusial (Muncul jika ada buku terlambat / info krusial) */
        .crucial-alert-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 5px solid #d97706;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .crucial-alert-banner.danger {
            background: #fef2f2;
            border-color: #fecaca;
            border-left-color: #dc2626;
        }

        /* Tombol Toggle Grafik Modular */
        .chart-toggle-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        .chart-btn {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 6px 14px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .chart-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .chart-btn.active {
            background: #b45309;
            color: #ffffff;
            border-color: #b45309;
        }

        /* Grid Analitik Khusus di Tab 2 */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        @media (max-width: 1024px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }
        .analytics-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .analytics-header h3 {
            margin: 0;
            font-size: 0.98rem;
            color: #1e293b;
        }
        .analytics-subtitle {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 3px;
        }
        .analytics-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fef3c7;
            color: #b45309;
            white-space: nowrap;
        }
        .denda-kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }
        .denda-kpi-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .denda-kpi-item .kpi-label {
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 600;
        }
        .denda-kpi-item .kpi-val {
            font-size: 0.98rem;
            font-weight: 700;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-brand" style="background:#b45309;">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li class="active"><a href="headmaster_dashboard.php">Dashboard Eksekutif</a></li>
            <li><a href="headmaster_staff.php">Kelola Akun Staff</a></li>
            <li><a href="headmaster_audit.php">Audit & Pendapatan</a></li>
        </ul>
        <div class="sidebar-footer"><span>Administrator</span></div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h2>Dashboard Eksekutif Administrator</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600;">Logout</a>
        </div>
        
        <div class="container">

            <!-- NAVIGASI TAB: MEMISAHKAN RINGKASAN MAKRO DAN ANALITIK MENDALAM -->
            <div class="hm-tab-nav">
                <button type="button" id="btn-tab-ringkasan" class="hm-tab-btn <?= ($active_hm_tab === 'ringkasan') ? 'active' : ''; ?>" onclick="switchHmTab('ringkasan', this)">
                    🏛️ Ringkasan Eksekutif (Makro)
                </button>
                <button type="button" id="btn-tab-analitik" class="hm-tab-btn <?= ($active_hm_tab === 'analitik') ? 'active' : ''; ?>" onclick="switchHmTab('analitik', this)">
                    📊 Analitik Perpustakaan & Status Denda
                    <?php if ($total_terlambat > 0): ?>
                        <span style="background: #dc2626; color: #fff; font-size: 0.72rem; padding: 2px 7px; border-radius: 999px; margin-left: 6px;"><?= $total_terlambat; ?> Overdue</span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- ========================================================================= -->
            <!-- TAB 1: RINGKASAN EKSEKUTIF (FOKUS KE MAKRO, BERSIH & LAPANG)              -->
            <!-- ========================================================================= -->
            <div id="hm-pane-ringkasan" class="hm-tab-pane <?= ($active_hm_tab === 'ringkasan') ? 'active' : ''; ?>">

                <!-- KARTU PERINGATAN KHUSUS (Hanya muncul jika ada buku terlambat atau denda krusial) -->
                <?php if ($total_terlambat > 0 || $total_potensi_denda_aktif > 0): ?>
                    <div class="crucial-alert-banner danger">
                        <div style="font-size: 0.9rem; color: #991b1b;">
                            ⚠️ <strong>Perhatian Eksekutif:</strong> Saat ini terdapat <strong><?= $total_terlambat; ?> buku yang terlambat dikembalikan (Overdue)</strong> dengan estimasi denda berjalan <strong>Rp <?= number_format($total_potensi_denda_aktif, 0, ',', '.'); ?></strong> (Pendapatan denda bulan ini: <strong>Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></strong>).
                        </div>
                        <button type="button" class="chart-btn active" style="background: #dc2626; border-color: #dc2626;" onclick="switchHmTab('analitik', document.getElementById('btn-tab-analitik'))">
                            Lihat Detail Analitik &rarr;
                        </button>
                    </div>
                <?php endif; ?>

                <!-- BARIS 1: 4 KARTU METRIK UTAMA -->
                <div class="stat-container" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
                    <div class="stat-card green">
                        <h4>Total Staff Terdaftar</h4>
                        <div class="number"><?= $total_staff; ?></div>
                    </div>
                    <div class="stat-card">
                        <h4>Total Stok Buku</h4>
                        <div class="number"><?= number_format($total_buku); ?></div>
                    </div>
                    <div class="stat-card" style="border-left-color: #8b5cf6;">
                        <h4>Siswa Terdaftar</h4>
                        <div class="number"><?= $total_siswa; ?></div>
                    </div>
                    <div class="stat-card orange">
                        <h4>Buku Yang Dipinjam</h4>
                        <div class="number"><?= $total_outgoing; ?></div>
                    </div>
                </div>

                <!-- BARIS 2: HANYA SATU GRAFIK UTAMA (GRAFIK MODULAR) -->
                <div class="card" style="margin-bottom: 25px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                        <h3 id="chart-title" style="margin: 0;">Analisis Buku Paling Diminati (Minggu Ini: Senin - Sabtu)</h3>
                        
                        <!-- Tombol Toggle Modular -->
                        <div class="chart-toggle-container" style="margin-bottom: 0;">
                            <button type="button" class="chart-btn active" onclick="switchChartMode('harian', this)">Aktivitas Mingguan</button>
                            <button type="button" class="chart-btn" onclick="switchChartMode('judul', this)">Berdasarkan Judul Buku</button>
                            <button type="button" class="chart-btn" onclick="switchChartMode('genre', this)">Berdasarkan Genre</button>
                        </div>
                    </div>

                    <div style="position: relative; height: 320px; width: 100%; margin-top: 15px;">
                        <canvas id="modularBarChart"></canvas>
                    </div>
                </div>

                <!-- BARIS 3: DUA TABEL PENTING BERDAMPINGAN -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- TABEL LOG AKTIVITAS STAF TERBARU -->
                    <div class="card" style="margin-bottom: 0;">
                        <h3>Log Aktivitas Staf Terbaru</h3>
                        <div class="table-responsive" style="margin-top: 15px;">
                            <table class="spreadsheet" style="font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Staf</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $q_logs = mysqli_query($koneksi, "SELECT * FROM audit_logs ORDER BY id DESC LIMIT 5");
                                    if (!$q_logs || mysqli_num_rows($q_logs) == 0):
                                    ?>
                                        <tr><td colspan="3" style="text-align: center; color: #64748b;">Belum ada log aktivitas.</td></tr>
                                    <?php else:
                                        while($log = mysqli_fetch_assoc($q_logs)):
                                    ?>
                                        <tr>
                                            <td><?= $log['waktu'] ?? '-'; ?></td>
                                            <td><strong><?= htmlspecialchars($log['staff_username']); ?></strong></td>
                                            <td><?= htmlspecialchars($log['aksi']); ?></td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TABEL BUKU TERPOPULER / SERING DIPINJAM -->
                    <div class="card" style="margin-bottom: 0;">
                        <h3>Buku Paling Sering Dipinjam</h3>
                        <div class="table-responsive" style="margin-top: 15px;">
                            <table class="spreadsheet" style="font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th>Nama Buku</th>
                                        <th>Penulis</th>
                                        <th style="text-align: center;">Total Dipinjam</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $q_populer = mysqli_query($koneksi, "SELECT buku.nama_buku, buku.penulis, COUNT(peminjaman.id) as total_pinjam 
                                                                         FROM peminjaman 
                                                                         JOIN buku ON peminjaman.buku_id = buku.id 
                                                                         GROUP BY peminjaman.buku_id 
                                                                         ORDER BY total_pinjam DESC LIMIT 5");
                                    if (!$q_populer || mysqli_num_rows($q_populer) == 0):
                                    ?>
                                        <tr><td colspan="3" style="text-align: center; color: #64748b;">Belum ada data peminjaman buku.</td></tr>
                                    <?php else:
                                        while($pop = mysqli_fetch_assoc($q_populer)):
                                    ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($pop['nama_buku']); ?></strong></td>
                                            <td><?= htmlspecialchars($pop['penulis']); ?></td>
                                            <td style="text-align: center;"><span class="badge" style="background: #e0f2fe; color: #0369a1; padding: 3px 8px; border-radius: 4px;"><?= $pop['total_pinjam']; ?>x</span></td>
                                        </tr>
                                    <?php endwhile; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div> <!-- End Tab 1: Ringkasan Makro -->


            <!-- ========================================================================= -->
            <!-- TAB 2: KHUSUS ANALITIK PERPUSTAKAAN (SIRKULASI, OVERDUE, GENRE, DENDA)    -->
            <!-- ========================================================================= -->
            <div id="hm-pane-analitik" class="hm-tab-pane <?= ($active_hm_tab === 'analitik') ? 'active' : ''; ?>">

                <!-- KARTU METRIK KHUSUS DENDA & KETERLAMBATAN -->
                <div class="stat-container" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 22px;">
                    <div class="stat-card" style="border-left-color: #059669;">
                        <h4>Pendapatan Denda (Bulan Ini)</h4>
                        <div class="number" style="color: #059669;">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></div>
                    </div>
                    <div class="stat-card" style="border-left-color: #dc2626;">
                        <h4>Buku Terlambat (Overdue)</h4>
                        <div class="number" style="color: #dc2626;"><?= $total_terlambat; ?> Buku</div>
                    </div>
                    <div class="stat-card" style="border-left-color: #d97706;">
                        <h4>Estimasi Tunggakan Denda Aktif</h4>
                        <div class="number" style="color: #d97706;">Rp <?= number_format($total_potensi_denda_aktif, 0, ',', '.'); ?></div>
                    </div>
                </div>

                <!-- 4 GRAFIK ANALITIK MENDALAM -->
                <div class="analytics-grid">
                    <!-- 1. Grafik Aktivitas Sirkulasi Harian (Peminjaman vs Pengembalian) -->
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <div>
                                <h3>📊 Sirkulasi Harian (Peminjaman vs Pengembalian)</h3>
                                <div class="analytics-subtitle">Perbandingan volume buku keluar & kembali (Senin – Sabtu)</div>
                            </div>
                            <span class="analytics-badge">Pekan Ini</span>
                        </div>
                        <div style="position: relative; height: 230px; width: 100%;">
                            <canvas id="chartSirkulasiHarian"></canvas>
                        </div>
                    </div>

                    <!-- 2. Grafik Status Keterlambatan Buku (Overdue Status Breakdown) -->
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <div>
                                <h3>⏳ Status Keterlambatan Buku (Overdue Breakdown)</h3>
                                <div class="analytics-subtitle">Proporsi ketepatan waktu & tingkat keterlambatan pinjaman aktif</div>
                            </div>
                            <span class="analytics-badge"><?= $total_outgoing; ?> Dipinjam</span>
                        </div>
                        <div style="position: relative; height: 230px; width: 100%;">
                            <canvas id="chartOverdueBreakdown"></canvas>
                        </div>
                    </div>

                    <!-- 3. Grafik Genre Buku Paling Aktif Keluar -->
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <div>
                                <h3>🔥 Top 5 Genre Buku Paling Aktif Keluar</h3>
                                <div class="analytics-subtitle">Kategori bacaan yang paling banyak beredar di tangan siswa</div>
                            </div>
                            <span class="analytics-badge">Top 5</span>
                        </div>
                        <div style="position: relative; height: 230px; width: 100%;">
                            <canvas id="chartGenreHorizontal"></canvas>
                        </div>
                    </div>

                    <!-- 4. Ringkasan Status Denda Harian (Sparkline) -->
                    <div class="analytics-card">
                        <div class="analytics-header">
                            <div>
                                <h3>💰 Ringkasan & Tren Denda Harian</h3>
                                <div class="analytics-subtitle">Akumulasi denda harian tercatat pada pekan berjalan</div>
                            </div>
                            <span class="analytics-badge" style="background: #fef2f2; color: #dc2626;">Tren Pekan Ini</span>
                        </div>

                        <div class="denda-kpi-row">
                            <div class="denda-kpi-item">
                                <div class="kpi-label">Denda Hari Ini</div>
                                <div class="kpi-val" style="color: #059669;">Rp <?= number_format($denda_terkumpul_hari_ini, 0, ',', '.'); ?></div>
                            </div>
                            <div class="denda-kpi-item">
                                <div class="kpi-label">Total Pekan Ini</div>
                                <div class="kpi-val" style="color: #0284c7;">Rp <?= number_format($total_denda_minggu_ini, 0, ',', '.'); ?></div>
                            </div>
                            <div class="denda-kpi-item">
                                <div class="kpi-label">Potensi Aktif</div>
                                <div class="kpi-val" style="color: #dc2626;">Rp <?= number_format($total_potensi_denda_aktif, 0, ',', '.'); ?></div>
                            </div>
                        </div>

                        <div style="position: relative; height: 145px; width: 100%;">
                            <canvas id="chartSparklineDenda"></canvas>
                        </div>
                    </div>
                </div>

            </div> <!-- End Tab 2: Analitik Mendalam -->

        </div>
    </div>

<!-- SCRIPT INISIALISASI TAB & CHART.JS (HIGH-DPI ANTI BLUR) -->
    <script>
        // Konfigurasi Global Chart.js agar Teks Tajam (Anti-Blur pada Layar High-DPI / Tab Tersembunyi)
        Chart.defaults.devicePixelRatio = Math.max(window.devicePixelRatio || 1, 2);
        Chart.defaults.font.family = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        Chart.defaults.color = '#334155';

        const labelsHari = <?= json_encode($labels_hari); ?>;
        const dataPinjamHarian = <?= json_encode($data_harian_pinjam); ?>;
        const dataKembaliHarian = <?= json_encode($data_harian_kembali); ?>;
        const dataDendaHarian = <?= json_encode($data_denda_harian); ?>;

        // --- GRAFIK UTAMA DI TAB 1 (MODULAR BAR CHART) ---
        const dataHarian = {
            labels: labelsHari,
            datasets: [{
                label: 'Peminjaman Buku (Senin - Sabtu)',
                data: dataPinjamHarian,
                backgroundColor: 'rgba(2, 132, 199, 0.8)',
                borderColor: '#0284c7',
                borderWidth: 1,
                borderRadius: 4
            }]
        };

        const dataJudul = {
            labels: <?= json_encode($labels_judul); ?>,
            datasets: [{
                label: 'Total Peminjaman per Judul Buku',
                data: <?= json_encode($data_val_judul); ?>,
                backgroundColor: 'rgba(5, 150, 105, 0.8)',
                borderColor: '#059669',
                borderWidth: 1,
                borderRadius: 4
            }]
        };

        const dataGenre = {
            labels: <?= json_encode($labels_genre); ?>,
            datasets: [{
                label: 'Total Peminjaman per Genre',
                data: <?= json_encode($data_val_genre); ?>,
                backgroundColor: 'rgba(139, 92, 246, 0.8)',
                borderColor: '#8b5cf6',
                borderWidth: 1,
                borderRadius: 4
            }]
        };

        const ctx = document.getElementById('modularBarChart').getContext('2d');
        let modularChart = new Chart(ctx, {
            type: 'bar',
            data: dataHarian,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 12, weight: '500' } }
                    },
                    x: {
                        ticks: { font: { size: 12, weight: '500' } }
                    }
                }
            }
        });

        function switchChartMode(mode, btnElement) {
            const buttons = document.querySelectorAll('.chart-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');

            const titleEl = document.getElementById('chart-title');

            if (mode === 'harian') {
                modularChart.data = dataHarian;
                titleEl.innerText = "Analisis Peminjaman Minggu Ini (Hari Sekolah: Senin - Sabtu)";
            } else if (mode === 'judul') {
                modularChart.data = dataJudul;
                titleEl.innerText = "Top 5 Buku Paling Diminati Berdasarkan Judul";
            } else if (mode === 'genre') {
                modularChart.data = dataGenre;
                titleEl.innerText = "Distribusi Peminjaman Berdasarkan Kategori Genre";
            }
            modularChart.update();
        }

        // --- 4 GRAFIK KHUSUS DI TAB 2 (ANALITIK PERPUSTAKAAN) ---
        // 1. Sirkulasi Harian (Peminjaman vs Pengembalian)
        const chartSirkulasi = new Chart(document.getElementById('chartSirkulasiHarian'), {
            type: 'bar',
            data: {
                labels: labelsHari,
                datasets: [
                    {
                        label: 'Buku Dipinjam',
                        data: dataPinjamHarian,
                        backgroundColor: '#0284c7',
                        borderRadius: 4
                    },
                    {
                        label: 'Buku Dikembalikan',
                        data: dataKembaliHarian,
                        backgroundColor: '#10b981',
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 12, weight: '600' }, color: '#1e293b' }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11, weight: '500' } } },
                    x: { ticks: { font: { size: 11, weight: '500' } } }
                }
            }
        });

        // 2. Status Keterlambatan Buku (Doughnut Chart)
        const chartOverdue = new Chart(document.getElementById('chartOverdueBreakdown'), {
            type: 'doughnut',
            data: {
                labels: [
                    'Masih Aman (> 2 Hari)',
                    'Mendekati Tenggat (0-2 Hari)',
                    'Terlambat Ringan (1-3 Hari)',
                    'Terlambat Berat (> 3 Hari)'
                ],
                datasets: [{
                    data: [<?= $kat_aman; ?>, <?= $kat_tenggat; ?>, <?= $kat_ringan; ?>, <?= $kat_berat; ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#dc2626'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: { boxWidth: 12, padding: 12, font: { size: 11.5, weight: '600' }, color: '#1e293b' }
                    }
                }
            }
        });

        // 3. Top 5 Genre Buku Paling Aktif Keluar (Horizontal Bar Chart)
        const chartGenreTop = new Chart(document.getElementById('chartGenreHorizontal'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($labels_genre_top5); ?>,
                datasets: [{
                    label: 'Total Dipinjam',
                    data: <?= json_encode($data_val_genre_top5); ?>,
                    backgroundColor: ['#8b5cf6', '#6366f1', '#06b6d4', '#0284c7', '#14b8a6'],
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, font: { size: 11, weight: '500' } } },
                    y: { ticks: { font: { size: 11.5, weight: '600' }, color: '#1e293b' } }
                }
            }
        });

        // 4. Sparkline Tren Denda Harian
        const chartSparkline = new Chart(document.getElementById('chartSparklineDenda'), {
            type: 'line',
            data: {
                labels: labelsHari,
                datasets: [{
                    label: 'Denda Tercatat (Rp)',
                    data: dataDendaHarian,
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.12)',
                    borderWidth: 2,
                    pointRadius: 3.5,
                    pointBackgroundColor: '#dc2626',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' Rp ' + Number(ctx.raw).toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 10000, // Mencegah sumbu Y menampilkan desimal Rp 0.0002k saat nilai 0
                        ticks: {
                            precision: 0,
                            maxTicksLimit: 5,
                            font: { size: 11, weight: '500' },
                            callback: function(value) {
                                if (value === 0) return 'Rp 0';
                                return 'Rp ' + (value >= 1000 ? (value / 1000) + 'rb' : value);
                            }
                        }
                    },
                    x: {
                        ticks: { font: { size: 11, weight: '500' } }
                    }
                }
            }
        });

        // Fungsi Pindah Tab Headmaster + Re-render Resolusi Kanvas
        function switchHmTab(tabName, btnEl) {
            document.querySelectorAll('.hm-tab-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.hm-tab-btn').forEach(b => b.classList.remove('active'));

            document.getElementById('hm-pane-' + tabName).classList.add('active');
            if (btnEl) btnEl.classList.add('active');

            // Paksa Chart.js menghitung ulang dimensi & merender tajam setelah tab tampil
            setTimeout(() => {
                if (tabName === 'analitik') {
                    chartSirkulasi.resize();
                    chartOverdue.resize();
                    chartGenreTop.resize();
                    chartSparkline.resize();
                } else {
                    modularChart.resize();
                }
            }, 20);
        }
    </script>
</body>
</html>