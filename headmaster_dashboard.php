\<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['headmaster_login'])) { header("Location: headmaster_login.php"); exit; }

// Statistik Utama
$q_staff = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE username != 'headmaster'");
$total_staff = mysqli_fetch_assoc($q_staff)['total'] ?? 0;

$q_buku = mysqli_query($koneksi, "SELECT SUM(stok) as total FROM buku");
$total_buku = mysqli_fetch_assoc($q_buku)['total'] ?? 0;

$q_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM students");
$total_siswa = mysqli_fetch_assoc($q_siswa)['total'] ?? 0;

$q_out = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Dipinjam'");
$total_outgoing = mysqli_fetch_assoc($q_out)['total'] ?? 0;

// Statistik Tambahan: Pendapatan Denda Bulan Ini
$bulan_ini = date('m');
$tahun_ini = date('Y');
$q_pendapatan = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total FROM denda_payments WHERE MONTH(tgl_bayar) = '$bulan_ini' AND YEAR(tgl_bayar) = '$tahun_ini'");
$total_pendapatan = mysqli_fetch_assoc($q_pendapatan)['total'] ?? 0;

// Statistik Tambahan: Buku Terlambat (> 7 Hari)
$q_terlambat = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Dipinjam' AND DATEDIFF(CURDATE(), tgl_pinjam) > 7");
$total_terlambat = mysqli_fetch_assoc($q_terlambat)['total'] ?? 0;

// Data untuk Grafik Tren (6 Bulan Terakhir)
$labels_grafik = [];
$data_pinjam = [];
$data_kembali = [];

for (function_exists('mysqli_fetch_assoc') ? $i = 5 : $i = 5; $i >= 0; $i--) {
    $bulan_loop = date('Y-m', strtotime("-$i months"));
    $nama_bulan = date('M Y', strtotime("-$i months"));
    $labels_grafik[] = $nama_bulan;

    // Hitung peminjaman di bulan tersebut
    $q_p_bulan = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE DATE_FORMAT(tgl_pinjam, '%Y-%m') = '$bulan_loop'");
    $data_pinjam[] = mysqli_fetch_assoc($q_p_bulan)['total'] ?? 0;

    // Hitung pengembalian di bulan tersebut
    $q_k_bulan = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Dikembalikan' AND DATE_FORMAT(tgl_kembali, '%Y-%m') = '$bulan_loop'");
    $data_kembali[] = mysqli_fetch_assoc($q_k_bulan)['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrator - DLWM</title>
    <link rel="stylesheet" href="style.css">
    <!-- Pustaka Chart.js untuk Grafik Tren -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <!-- KARTU STATISTIK EKSEKUTIF -->
            <div class="stat-container" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <div class="stat-card green"><h4>Total Staff Terdaftar</h4><div class="number"><?= $total_staff; ?></div></div>
                <div class="stat-card"><h4>Total Stok Buku</h4><div class="number"><?= number_format($total_buku); ?></div></div>
                <div class="stat-card" style="border-left-color: #8b5cf6;"><h4>Siswa Terdaftar</h4><div class="number"><?= $total_siswa; ?></div></div>
                <div class="stat-card orange"><h4>Buku Yang Dipinjam</h4><div class="number"><?= $total_outgoing; ?></div></div>
                <div class="stat-card" style="border-left-color: #059669;"><h4>Pendapatan Denda (Bulan Ini)</h4><div class="number" style="font-size: 1.3rem;">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></div></div>
                <div class="stat-card" style="border-left-color: #dc2626;"><h4>Buku Terlambat (Overdue)</h4><div class="number" style="color: #dc2626;"><?= $total_terlambat; ?></div></div>
            </div>

            <!-- GRAFIK TREN PEMINJAMAN & PENGEMBALIAN -->
            <div class="card" style="margin-bottom: 25px;">
                <h3>Grafik Tren Peminjaman & Pengembalian Buku (6 Bulan Terakhir)</h3>
                <div style="position: relative; height: 320px; width: 100%; margin-top: 15px;">
                    <canvas id="trenChart"></canvas>
                </div>
            </div>

            

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
                                if (mysqli_num_rows($q_logs) == 0):
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
                                if (mysqli_num_rows($q_populer) == 0):
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

        </div>
    </div>

    <!-- SCRIPT INISIALISASI CHART.JS -->
    <script>
        const ctx = document.getElementById('trenChart').getContext('2d');
        const trenChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($labels_grafik); ?>,
                datasets: [
                    {
                        label: 'Peminjaman Buku',
                        data: <?= json_encode($data_pinjam); ?>,
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Pengembalian Buku',
                        data: <?= json_encode($data_kembali); ?>,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>