<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['headmaster_login'])) { 
    header("Location: headmaster_login.php"); 
    exit; 
}

// Definisikan batas maksimal baris per halaman
define('LIMIT_PER_HALAMAN', 12);

// Ambil parameter filter urutan/kategori aksi dari URL
$filter_aksi = isset($_GET['aksi']) ? mysqli_real_escape_string($koneksi, $_GET['aksi']) : 'Semua';

// Ambil nomor halaman dari URL untuk masing-masing tabel
$page_log = isset($_GET['p_log']) ? (int)$_GET['p_log'] : 1;
$page_denda = isset($_GET['p_denda']) ? (int)$_GET['p_denda'] : 1;

$page_log = max($page_log, 1);
$page_denda = max($page_denda, 1);

$offset_log = ($page_log - 1) * LIMIT_PER_HALAMAN;
$offset_denda = ($page_denda - 1) * LIMIT_PER_HALAMAN;

// Tentukan tab aktif berdasarkan parameter URL
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'log-aktivitas';
if (isset($_GET['p_denda']) && !isset($_GET['tab'])) {
    $active_tab = 'rincian-denda';
}

// Kondisi Query SQL Berdasarkan Filter Kategori Aksi
$where_sql = "WHERE 1=1";
if ($filter_aksi === 'Terbaru') {
    $order_sql = " ORDER BY id DESC";
} elseif ($filter_aksi === 'Peminjaman Buku') {
    $where_sql .= " AND (aksi LIKE '%Pinjam%' OR aksi LIKE '%Peminjaman%')";
    $order_sql = " ORDER BY id DESC";
} elseif ($filter_aksi === 'Pengembalian Buku') {
    $where_sql .= " AND (aksi LIKE '%Kembali%' OR aksi LIKE '%Pengembalian%')";
    $order_sql = " ORDER BY id DESC";
} elseif ($filter_aksi === 'Penambahan Buku') {
    $where_sql .= " AND aksi LIKE '%Tambah Buku%'";
    $order_sql = " ORDER BY id DESC";
} elseif ($filter_aksi === 'Edit Buku') {
    $where_sql .= " AND (aksi LIKE '%Edit%' OR aksi LIKE '%Perbarui%')";
    $order_sql = " ORDER BY id DESC";
} elseif ($filter_aksi === 'Penghapusan Buku') {
    $where_sql .= " AND aksi LIKE '%Hapus Buku%'";
    $order_sql = " ORDER BY id DESC";
} elseif ($filter_aksi === 'Pemrosesan Denda') {
    $where_sql .= " AND (aksi LIKE '%Denda%' OR aksi LIKE '%Pembayaran%')";
    $order_sql = " ORDER BY id DESC";
} else {
    $order_sql = " ORDER BY id DESC";
}

// Hitung total log sesuai filter & total pendapatan denda
$q_cnt = mysqli_query($koneksi, "SELECT COUNT(*) as c FROM audit_logs $where_sql");
$cnt_audit = mysqli_fetch_assoc($q_cnt)['c'] ?? 0;

$q_rev = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total_pendapatan FROM denda_payments");
$total_pendapatan = mysqli_fetch_assoc($q_rev)['total_pendapatan'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Audit & Pendapatan - Administrator</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Navbar berbentuk balok persegi empat di atas */
        .audit-navbar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .audit-nav-box {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 15px 20px;
            flex: 1;
            min-width: 200px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            border-top: 4px solid #0284c7;
        }
        .audit-nav-box.revenue { border-top-color: #10b981; }
        .audit-nav-box h5 { font-size: 0.8rem; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .audit-nav-box .value { font-size: 1.25rem; font-weight: bold; color: #1e293b; }

        /* Tombol Tab Navigasi Pemisah Data Audit */
        .audit-tab-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
        }
        .tab-btn {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .tab-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .tab-btn.active {
            background: #b45309; 
            color: #ffffff;
            border-color: #b45309;
            box-shadow: 0 2px 4px rgba(180, 83, 9, 0.2);
        }

        /* Konten Tab yang disembunyikan */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        /* Styling Paginasi Modern & Rapi */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        .pagination-btn {
            background: #ffffff;
            color: #b45309;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination-btn:hover {
            background: #fdf2f8;
            border-color: #b45309;
        }
        .pagination-btn.active {
            background: #b45309;
            color: #ffffff;
            border-color: #b45309;
        }
        .pagination-jump {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-left: 15px;
            font-size: 0.85rem;
            color: #475569;
        }
        .pagination-jump input {
            width: 50px;
            padding: 5px;
            text-align: center;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            font-size: 0.85rem;
        }
        .pagination-jump button {
            background: #f1f5f9;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .pagination-jump button:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR HEADMASTER -->
    <div class="sidebar">
        <div class="sidebar-brand" style="background:#b45309;">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li><a href="headmaster_dashboard.php">Dashboard Eksekutif</a></li>
            <li><a href="headmaster_staff.php">Kelola Akun Staff</a></li>
            <li class="active"><a href="headmaster_audit.php">Audit & Pendapatan</a></li>
        </ul>
        <div class="sidebar-footer"><span>Administrator</span></div>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <div class="topbar">
            <h2>Audit Aktivitas Staff & Laporan Pendapatan</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600;">Logout</a>
        </div>

        <div class="container">

            <!-- METRIK/STATISTIK UTAMA -->
            <div class="audit-navbar">
                <div class="audit-nav-box">
                    <h5>Total Log Audit Tercatat</h5>
                    <div class="value"><?= number_format($cnt_audit); ?></div>
                </div>
                <div class="audit-nav-box revenue">
                    <h5>Total Pendapatan Denda Masuk</h5>
                    <div class="value" style="color: #059669;">Rp <?= number_format($total_pendapatan, 0, ',', '.'); ?></div>
                </div>
            </div>

            <!-- TOMBOL TAB PEMISAH DATA -->
            <div class="audit-tab-container">
                <button type="button" class="tab-btn <?= ($active_tab == 'log-aktivitas') ? 'active' : ''; ?>" onclick="switchTab('log-aktivitas', this)">
                    📋 Log Aktivitas & Pemrosesan Staff
                </button>
                <button type="button" class="tab-btn <?= ($active_tab == 'rincian-denda') ? 'active' : ''; ?>" onclick="switchTab('rincian-denda', this)">
                    💰 Rincian Masuk Pembayaran Denda
                </button>
            </div>

            <!-- TAB 1: LOG AKTIVITAS STAFF -->
            <div id="log-aktivitas" class="tab-content <?= ($active_tab == 'log-aktivitas') ? 'active' : ''; ?>">
                <div class="card">
                    <h3>Log Aktivitas & Pemrosesan Staff</h3>
                    
                    <!-- Form Filter Urutan / Kategori Aksi -->
                    <form method="GET" action="headmaster_audit.php" style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
                        <input type="hidden" name="tab" value="log-aktivitas">
                        <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Urutkan / Filter Aksi:</label>
                        <select name="aksi" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-size: 0.9rem; min-width: 220px;">
                            <?php 
                            $opsi_filter = [
                                'Semua', 
                                'Terbaru', 
                                'Peminjaman Buku', 
                                'Pengembalian Buku', 
                                'Penambahan Buku', 
                                'Edit Buku', 
                                'Penghapusan Buku', 
                                'Pemrosesan Denda'
                            ];
                            foreach ($opsi_filter as $opt) {
                                $selected = ($filter_aksi === $opt) ? 'selected' : '';
                                $label_tampil = ($opt === 'Semua') ? 'Semua (Default)' : $opt;
                                echo "<option value=\"$opt\" $selected>$label_tampil</option>";
                            }
                            ?>
                        </select>
                        <?php if ($filter_aksi !== 'Semua'): ?>
                            <a href="headmaster_audit.php?tab=log-aktivitas" class="btn" style="background: #64748b; text-decoration: none; padding: 8px 14px; font-size: 0.85rem; display: inline-block;">Reset Filter</a>
                        <?php endif; ?>
                    </form>

                    <div class="table-responsive">
                        <table class="spreadsheet">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 160px;">Waktu</th>
                                    <th style="width: 140px;">Username Staff</th>
                                    <th style="width: 180px;">Aksi</th>
                                    <th>Detail Transaksi & Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q_tot_log = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM audit_logs $where_sql");
                                $tot_data_log = mysqli_fetch_assoc($q_tot_log)['total'];
                                $tot_halaman_log = ceil($tot_data_log / LIMIT_PER_HALAMAN);

                                $no = $offset_log + 1;
                                $res_log = mysqli_query($koneksi, "SELECT * FROM audit_logs $where_sql $order_sql LIMIT $offset_log, " . LIMIT_PER_HALAMAN);
                                
                                if (mysqli_num_rows($res_log) == 0): ?>
                                    <tr><td colspan="5" style="text-align: center; color: #64748b;">Belum ada log audit aktivitas staff yang cocok dengan kriteria ini.</td></tr>
                                <?php else: 
                                    while ($l = mysqli_fetch_assoc($res_log)):
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td><?= $l['waktu']; ?></td>
                                        <td><strong><?= htmlspecialchars($l['staff_username']); ?></strong></td>
                                        <td><span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight: 600;"><?= htmlspecialchars($l['aksi']); ?></span></td>
                                        <td><?= htmlspecialchars($l['detail']); ?></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginasi Rapi dengan Panah & Input Manual untuk Log Aktivitas -->
                    <?php if ($tot_halaman_log > 1): ?>
                        <div class="pagination-container">
                            <!-- Tombol Panah Kiri (Previous) -->
                            <?php if ($page_log > 1): ?>
                                <a href="headmaster_audit.php?p_log=<?= ($page_log - 1); ?>&p_denda=<?= $page_denda; ?>&tab=log-aktivitas&aksi=<?= urlencode($filter_aksi); ?>" class="pagination-btn">« Prev</a>
                            <?php endif; ?>

                            <!-- Nomor Halaman (Maksimal menampilkan 5 nomor di sekitar halaman aktif) -->
                            <?php 
                            $start_p = max(1, $page_log - 2);
                            $end_p = min($tot_halaman_log, $page_log + 2);
                            if ($start_p > 1) {
                                echo '<a href="headmaster_audit.php?p_log=1&p_denda='.$page_denda.'&tab=log-aktivitas&aksi='.urlencode($filter_aksi).'" class="pagination-btn">1</a>';
                                if ($start_p > 2) echo '<span style="padding: 0 4px; color: #64748b;">...</span>';
                            }
                            for ($i = $start_p; $i <= $end_p; $i++): 
                            ?>
                                <a href="headmaster_audit.php?p_log=<?= $i; ?>&p_denda=<?= $page_denda; ?>&tab=log-aktivitas&aksi=<?= urlencode($filter_aksi); ?>" class="pagination-btn <?= ($i == $page_log) ? 'active' : ''; ?>">
                                    <?= $i; ?>
                                </a>
                            <?php endfor; 
                            if ($end_p < $tot_halaman_log) {
                                if ($end_p < $tot_halaman_log - 1) echo '<span style="padding: 0 4px; color: #64748b;">...</span>';
                                echo '<a href="headmaster_audit.php?p_log='.$tot_halaman_log.'&p_denda='.$page_denda.'&tab=log-aktivitas&aksi='.urlencode($filter_aksi).'" class="pagination-btn">'.$tot_halaman_log.'</a>';
                            }
                            ?>

                            <!-- Tombol Panah Kanan (Next) -->
                            <?php if ($page_log < $tot_halaman_log): ?>
                                <a href="headmaster_audit.php?p_log=<?= ($page_log + 1); ?>&p_denda=<?= $page_denda; ?>&tab=log-aktivitas&aksi=<?= urlencode($filter_aksi); ?>" class="pagination-btn">Next »</a>
                            <?php endif; ?>

                            <!-- Form Input Manual Lompat Halaman -->
                            <form method="GET" action="headmaster_audit.php" class="pagination-jump">
                                <input type="hidden" name="tab" value="log-aktivitas">
                                <input type="hidden" name="aksi" value="<?= htmlspecialchars($filter_aksi); ?>">
                                <input type="hidden" name="p_denda" value="<?= $page_denda; ?>">
                                <span>Hal:</span>
                                <input type="number" name="p_log" min="1" max="<?= $tot_halaman_log; ?>" value="<?= $page_log; ?>">
                                <button type="submit">Go</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 2: RINCIAN PEMBAYARAN DENDA -->
            <div id="rincian-denda" class="tab-content <?= ($active_tab == 'rincian-denda') ? 'active' : ''; ?>">
                <div class="card">
                    <h3>Rincian Masuk Pembayaran Denda</h3>
                    <div class="table-responsive">
                        <table class="spreadsheet">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 130px;">Tgl Bayar</th>
                                    <th style="width: 140px;">NPM Siswa</th>
                                    <th style="width: 140px;">Staff Melayani</th>
                                    <th style="width: 150px;">Nominal Masuk</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q_tot_denda = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM denda_payments");
                                $tot_data_denda = mysqli_fetch_assoc($q_tot_denda)['total'];
                                $tot_halaman_denda = ceil($tot_data_denda / LIMIT_PER_HALAMAN);

                                $no2 = $offset_denda + 1;
                                $res_p = mysqli_query($koneksi, "SELECT * FROM denda_payments ORDER BY id DESC LIMIT $offset_denda, " . LIMIT_PER_HALAMAN);

                                if (mysqli_num_rows($res_p) == 0): ?>
                                    <tr><td colspan="6" style="text-align: center; color: #64748b;">Belum ada transaksi pembayaran denda masuk.</td></tr>
                                <?php else: 
                                    while ($p = mysqli_fetch_assoc($res_p)):
                                ?>
                                    <tr>
                                        <td><?= $no2++; ?></td>
                                        <td><?= $p['tgl_bayar']; ?></td>
                                        <td><?= htmlspecialchars($p['npm']); ?></td>
                                        <td><strong><?= htmlspecialchars($p['staff_username']); ?></strong></td>
                                        <td><strong style="color: #059669;">Rp <?= number_format($p['jumlah_bayar'], 0, ',', '.'); ?></strong></td>
                                        <td><?= htmlspecialchars($p['keterangan']); ?></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginasi Rapi dengan Panah & Input Manual untuk Rincian Denda -->
                    <?php if ($tot_halaman_denda > 1): ?>
                        <div class="pagination-container">
                            <!-- Tombol Panah Kiri (Previous) -->
                            <?php if ($page_denda > 1): ?>
                                <a href="headmaster_audit.php?p_log=<?= $page_log; ?>&p_denda=<?= ($page_denda - 1); ?>&tab=rincian-denda&aksi=<?= urlencode($filter_aksi); ?>" class="pagination-btn">« Prev</a>
                            <?php endif; ?>

                            <!-- Nomor Halaman -->
                            <?php 
                            $start_pd = max(1, $page_denda - 2);
                            $end_pd = min($tot_halaman_denda, $page_denda + 2);
                            if ($start_pd > 1) {
                                echo '<a href="headmaster_audit.php?p_log='.$page_log.'&p_denda=1&tab=rincian-denda&aksi='.urlencode($filter_aksi).'" class="pagination-btn">1</a>';
                                if ($start_pd > 2) echo '<span style="padding: 0 4px; color: #64748b;">...</span>';
                            }
                            for ($j = $start_pd; $j <= $end_pd; $j++): 
                            ?>
                                <a href="headmaster_audit.php?p_log=<?= $page_log; ?>&p_denda=<?= $j; ?>&tab=rincian-denda&aksi=<?= urlencode($filter_aksi); ?>" class="pagination-btn <?= ($j == $page_denda) ? 'active' : ''; ?>">
                                    <?= $j; ?>
                                </a>
                            <?php endfor; 
                            if ($end_pd < $tot_halaman_denda) {
                                if ($end_pd < $tot_halaman_denda - 1) echo '<span style="padding: 0 4px; color: #64748b;">...</span>';
                                echo '<a href="headmaster_audit.php?p_log='.$page_log.'&p_denda='.$tot_halaman_denda.'&tab=rincian-denda&aksi='.urlencode($filter_aksi).'" class="pagination-btn">'.$tot_halaman_denda.'</a>';
                            }
                            ?>

                            <!-- Tombol Panah Kanan (Next) -->
                            <?php if ($page_denda < $tot_halaman_denda): ?>
                                <a href="headmaster_audit.php?p_log=<?= $page_log; ?>&p_denda=<?= ($page_denda + 1); ?>&tab=rincian-denda&aksi=<?= urlencode($filter_aksi); ?>" class="pagination-btn">Next »</a>
                            <?php endif; ?>

                            <!-- Form Input Manual Lompat Halaman -->
                            <form method="GET" action="headmaster_audit.php" class="pagination-jump">
                                <input type="hidden" name="tab" value="rincian-denda">
                                <input type="hidden" name="aksi" value="<?= htmlspecialchars($filter_aksi); ?>">
                                <input type="hidden" name="p_log" value="<?= $page_log; ?>">
                                <span>Hal:</span>
                                <input type="number" name="p_denda" min="1" max="<?= $tot_halaman_denda; ?>" value="<?= $page_denda; ?>">
                                <button type="submit">Go</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- SCRIPT TABS NAVIGASI -->
    <script>
    function switchTab(tabId, btnElement) {
        const contents = document.querySelectorAll('.tab-content');
        contents.forEach(content => content.classList.remove('active'));

        const buttons = document.querySelectorAll('.tab-btn');
        buttons.forEach(btn => btn.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        btnElement.classList.add('active');
    }
    </script>

</body>
</html>