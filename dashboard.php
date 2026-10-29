<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$staff_username = $_SESSION['user'] ?? 'staff';

// Aturan Denda & Batas Maksimal
define('BATAS_PINJAM_HARI', 7);
define('DENDA_PER_HARI', 2000);
define('MAKS_DENDA', 250000);
define('MAKS_BUKU_PINJAM', 2);
define('LIMIT_PER_HALAMAN', 12); // Batas maksimal 12 baris per halaman

// Ambil nomor halaman, tab aktif, serta parameter pencarian & urutan dari URL
$page_aktif = isset($_GET['p_aktif']) ? (int)$_GET['p_aktif'] : 1;
$page_riwayat = isset($_GET['p_riwayat']) ? (int)$_GET['p_riwayat'] : 1;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'form-pinjam'; 

$cari_peminjam = isset($_GET['cari_peminjam']) ? mysqli_real_escape_string($koneksi, trim($_GET['cari_peminjam'])) : '';
$urutan_aktif = isset($_GET['urutan']) ? $_GET['urutan'] : 'Terbaru';

$page_aktif = max($page_aktif, 1);
$page_riwayat = max($page_riwayat, 1);

$offset_aktif = ($page_aktif - 1) * LIMIT_PER_HALAMAN;
$offset_riwayat = ($page_riwayat - 1) * LIMIT_PER_HALAMAN;

// --- HANDLER PROSES PEMINJAMAN BUKU ---
if (isset($_POST['tambah_peminjaman'])) {
    $npm = mysqli_real_escape_string($koneksi, $_POST['npm']);
    $nama_siswa = mysqli_real_escape_string($koneksi, $_POST['nama_siswa']);
    $buku_id = (int)$_POST['buku_id'];
    $tgl_pinjam = $_POST['tgl_pinjam'];

    // 1. Validasi Validitas Nama dan NPM
    $cek_npm = mysqli_query($koneksi, "SELECT nama_siswa FROM peminjaman WHERE npm = '$npm' LIMIT 1");
    if (mysqli_num_rows($cek_npm) > 0) {
        $data_existing = mysqli_fetch_assoc($cek_npm);
        if (strcasecmp(trim($data_existing['nama_siswa']), trim($nama_siswa)) !== 0) {
            echo "<script>alert('Gagal! NIS {$npm} sudah terdaftar atas nama \"{$data_existing['nama_siswa']}\". Nama tidak boleh berbeda.'); window.location='dashboard.php?tab=form-pinjam';</script>";
            exit;
        }
    }

    // 2. Validasi Maksimal 2 Buku Aktif
    $q_jml = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE npm = '$npm' AND status = 'Dipinjam'");
    $d_jml = mysqli_fetch_assoc($q_jml);
    if ($d_jml['total'] >= MAKS_BUKU_PINJAM) {
        echo "<script>alert('GAGAL: Siswa ini sudah meminjam 2 buku maksimal! Wajib mengembalikan setidaknya satu buku terlebih dahulu.'); window.location='dashboard.php?tab=form-pinjam';</script>";
        exit;
    }

    // 3. Validasi Blokir jika Akumulasi Denda Mencapai / Melewati Rp 250.000
    $q_denda_aktif = mysqli_query($koneksi, "SELECT tgl_pinjam FROM peminjaman WHERE npm = '$npm' AND status = 'Dipinjam'");
    $total_denda_berjalan = 0;
    $tgl_sekarang_obj = new DateTime(date('Y-m-d'));

    while ($r_p = mysqli_fetch_assoc($q_denda_aktif)) {
        $tgl_p_obj = new DateTime($r_p['tgl_pinjam']);
        $selisih = $tgl_p_obj->diff($tgl_sekarang_obj)->days;
        $terlambat = $selisih - BATAS_PINJAM_HARI;
        if ($terlambat > 0) {
            $df = $terlambat * DENDA_PER_HARI;
            if ($df > MAKS_DENDA) $df = MAKS_DENDA;
            $total_denda_berjalan += $df;
        }
    }

    if ($total_denda_berjalan >= MAKS_DENDA) {
        echo "<script>alert('GAGAL: Akumulasi denda siswa ini telah mencapai atau melewati batas Rp 250.000! Peminjaman diblokir sampai denda dilunasi.'); window.location='dashboard.php?tab=form-pinjam';</script>";
        exit;
    }

    // 4. Cek Stok Buku
    $cek_stok = mysqli_query($koneksi, "SELECT nama_buku, stok FROM buku WHERE id = $buku_id");
    $data_buku = mysqli_fetch_assoc($cek_stok);

    if ($data_buku && $data_buku['stok'] > 0) {
        mysqli_query($koneksi, "INSERT INTO peminjaman (npm, nama_siswa, buku_id, tgl_pinjam, status, staff_pinjam) VALUES ('$npm', '$nama_siswa', $buku_id, '$tgl_pinjam', 'Dipinjam', '$staff_username')");
        mysqli_query($koneksi, "UPDATE buku SET stok = stok - 1 WHERE id = $buku_id");

        $nama_buku_txt = $data_buku['nama_buku'];
        $detail_raw = "Meminjamkan buku '$nama_buku_txt' kepada siswa $nama_siswa (NIS: $npm)";
        $detail_log = mysqli_real_escape_string($koneksi, $detail_raw);
        mysqli_query($koneksi, "INSERT INTO audit_logs (staff_username, aksi, detail) VALUES ('$staff_username', 'Pemrosesan Pinjaman Buku', '$detail_log')");

        header("Location: dashboard.php?tab=buku-dipinjam");
        exit;
    } else {
        echo "<script>alert('Stok buku habis!'); window.location='dashboard.php?tab=form-pinjam';</script>";
        exit;
    }
}

// --- HANDLER PENGEMBALIAN BUKU & PELUNASAN DENDA ---
if (isset($_GET['kembalikan'])) {
    $id_pinjam = (int)$_GET['kembalikan'];
    $tgl_sekarang = date('Y-m-d');

    $q = mysqli_query($koneksi, "SELECT peminjaman.*, buku.nama_buku FROM peminjaman JOIN buku ON peminjaman.buku_id = buku.id WHERE peminjaman.id = $id_pinjam");
    $data = mysqli_fetch_assoc($q);

    if ($data) {
        $buku_id = $data['buku_id'];
        $npm = $data['npm'];
        $nama_siswa = $data['nama_siswa'];
        $nama_buku = $data['nama_buku'];
        $tgl_pinjam = $data['tgl_pinjam'];

        $tgl_pinjam_obj = new DateTime($tgl_pinjam);
        $tgl_sekarang_obj = new DateTime($tgl_sekarang);
        $selisih = $tgl_pinjam_obj->diff($tgl_sekarang_obj)->days;
        $terlambat_hari = $selisih - BATAS_PINJAM_HARI;
        $denda = 0;

        if ($terlambat_hari > 0) {
            $denda = $terlambat_hari * DENDA_PER_HARI;
            if ($denda > MAKS_DENDA) {
                $denda = MAKS_DENDA;
            }
            
            $ket_bayar = mysqli_real_escape_string($koneksi, "Pelunasan Late Fees keterlambatan $terlambat_hari hari (Buku: $nama_buku)");
            mysqli_query($koneksi, "INSERT INTO denda_payments (npm, staff_username, jumlah_bayar, tgl_bayar, keterangan) VALUES ('$npm', '$staff_username', $denda, '$tgl_sekarang', '$ket_bayar')");

            $log_denda_raw = "Menerima pembayaran Late Fees sebesar Rp " . number_format($denda, 0, ',', '.') . " dari $nama_siswa (NIS: $npm)";
            $log_denda = mysqli_real_escape_string($koneksi, $log_denda_raw);
            mysqli_query($koneksi, "INSERT INTO audit_logs (staff_username, aksi, detail) VALUES ('$staff_username', 'Pemrosesan Pembayaran Denda', '$log_denda')");
        }

        mysqli_query($koneksi, "UPDATE peminjaman SET status = 'Dikembalikan', tgl_kembali = '$tgl_sekarang', staff_kembali = '$staff_username' WHERE id = $id_pinjam");
        mysqli_query($koneksi, "UPDATE buku SET stok = stok + 1 WHERE id = $buku_id");

        $log_kembali_raw = "Menerima pengembalian buku '$nama_buku' dari siswa $nama_siswa (NIS: $npm)";
        $log_kembali = mysqli_real_escape_string($koneksi, $log_kembali_raw);
        mysqli_query($koneksi, "INSERT INTO audit_logs (staff_username, aksi, detail) VALUES ('$staff_username', 'Pemrosesan Pengembalian Buku', '$log_kembali')");
    }

    header("Location: dashboard.php?tab=buku-dipinjam");
    exit;
}

// Hitung Statistik Dashboard
$q_stok = mysqli_query($koneksi, "SELECT SUM(stok) as total_stok FROM buku");
$total_stok_buku = mysqli_fetch_assoc($q_stok)['total_stok'] ?? 0;

$q_jenis = mysqli_query($koneksi, "SELECT COUNT(*) as total_jenis FROM buku");
$total_jenis_buku = mysqli_fetch_assoc($q_jenis)['total_jenis'] ?? 0;

$q_staff = mysqli_query($koneksi, "SELECT COUNT(*) as total_staff FROM users WHERE username != 'headmaster'");
$total_staff = mysqli_fetch_assoc($q_staff)['total_staff'] ?? 0;

$q_out = mysqli_query($koneksi, "SELECT COUNT(*) as total_outgoing FROM peminjaman WHERE status = 'Dipinjam'");
$total_outgoing = mysqli_fetch_assoc($q_out)['total_outgoing'] ?? 0;

$q_siswa = mysqli_query($koneksi, "SELECT COUNT(*) as total_siswa FROM students");
$total_siswa = mysqli_fetch_assoc($q_siswa)['total_siswa'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Digital Library Web Manager (DLWM)</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-tab-container {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
        }
        .dashboard-tab-btn {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 10px 20px;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .dashboard-tab-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
        .dashboard-tab-btn.active {
            background: #0284c7; 
            color: #ffffff;
            border-color: #0284c7;
            box-shadow: 0 2px 4px rgba(2, 132, 199, 0.2);
        }

        .dashboard-tab-content {
            display: none;
        }
        .dashboard-tab-content.active {
            display: block;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            margin-top: 20px;
        }
        .pagination-btn {
            background: #ffffff;
            color: #0284c7;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 4px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination-btn:hover {
            background: #f1f5f9;
            border-color: #0284c7;
        }
        .pagination-btn.active {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li class="active"><a href="dashboard.php">Dashboard Utama</a></li>
            <li><a href="kelola_buku.php">Kelola Stok & Buku</a></li>
            <li><a href="kelola_siswa.php">Kelola Akun Siswa</a></li>
            <li><a href="kelola_akun.php">Kelola Akun Staff</a></li>
        </ul>
        <div class="sidebar-footer">
            <span>Staff: <strong><?= htmlspecialchars($_SESSION['user']); ?></strong></span>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h2 style="font-size: 1.2rem; color: #1e293b;">Dashboard & Transaksi Peminjaman</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Logout</a>
        </div>

        <div class="container">

            <div class="stat-container">
                <div class="stat-card">
                    <h4>Total Stok Buku Tersedia</h4>
                    <div class="number"><?= number_format($total_stok_buku); ?></div>
                </div>
                <div class="stat-card" style="border-left-color: #06b6d4;">
                    <h4>Jenis Buku</h4>
                    <div class="number"><?= number_format($total_jenis_buku); ?></div>
                </div>
                <div class="stat-card green">
                    <h4>Akun Staff Terdaftar</h4>
                    <div class="number"><?= $total_staff; ?></div>
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

            <!-- TOMBOL TAB NAVIGASI HORIZONTAL -->
            <div class="dashboard-tab-container">
                <button type="button" class="dashboard-tab-btn <?= ($active_tab == 'form-pinjam') ? 'active' : ''; ?>" onclick="switchDashboardTab('form-pinjam', this)">
                    📖 Form Peminjaman
                </button>
                <button type="button" class="dashboard-tab-btn <?= ($active_tab == 'buku-dipinjam') ? 'active' : ''; ?>" onclick="switchDashboardTab('buku-dipinjam', this)">
                    📚 Buku yang Dipinjam
                </button>
                <button type="button" class="dashboard-tab-btn <?= ($active_tab == 'riwayat') ? 'active' : ''; ?>" onclick="switchDashboardTab('riwayat', this)">
                    🗂️ Riwayat Peminjaman (Arsip)
                </button>
            </div>


            <!-- ================= TAB 1: FORM PEMINJAMAN ================= -->
            <div id="form-pinjam" class="dashboard-tab-content <?= ($active_tab == 'form-pinjam') ? 'active' : ''; ?>">
                <div class="card">
                    <h3>Proses Peminjaman Buku</h3>
                    <form action="" method="POST" style="display: flex; gap: 15px; flex-wrap: wrap;">
                        
                        <div class="form-group" style="flex: 2; min-width: 250px;">
                            <label>Cari Siswa (Ketik NIS atau Nama)</label>
                            <input type="text" id="cari_siswa" list="list_students" placeholder="Ketik untuk mencari..." autocomplete="off" required style="width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem;">
                            
                            <datalist id="list_students">
                                <?php
                                $res_s = mysqli_query($koneksi, "SELECT * FROM students ORDER BY nama_lengkap ASC");
                                while ($s = mysqli_fetch_assoc($res_s)) {
                                    echo "<option value='{$s['npm']} - {$s['nama_lengkap']}' data-npm='{$s['npm']}' data-nama='{$s['nama_lengkap']}'></option>";
                                }
                                ?>
                            </datalist>
                        </div>

                        <div class="form-group" style="flex: 1; min-width: 160px;">
                            <label>NIS Siswa</label>
                            <input type="text" name="npm" id="input_npm" readonly required placeholder="Otomatis terisi" style="background-color: #f8fafc;">
                        </div>

                        <div class="form-group" style="flex: 2; min-width: 220px;">
                            <label>Nama Lengkap</label>
                            <input type="text" name="nama_siswa" id="input_nama" readonly required placeholder="Otomatis terisi" style="background-color: #f8fafc;">
                        </div>

                        <div class="form-group" style="flex: 2; min-width: 220px;">
                            <label>Pilih Buku Spesifik (Ketik Judul / Barcode)</label>
                            <input type="text" id="cari_buku" list="list_buku" placeholder="Ketik judul buku / barcode..." autocomplete="off" required style="width: 100%; padding: 9px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem;">
                            <input type="hidden" name="buku_id" id="input_buku_id" required>

                            <datalist id="list_buku">
                                <?php
                                $buku_opt = mysqli_query($koneksi, "SELECT * FROM buku WHERE stok > 0 ORDER BY nama_buku ASC");
                                while ($b = mysqli_fetch_assoc($buku_opt)) {
                                    echo "<option value='[Barcode: {$b['barcode']}] {$b['nama_buku']} (Stok: {$b['stok']})' data-id='{$b['id']}'></option>";
                                }
                                ?>
                            </datalist>
                        </div>

                        <div class="form-group" style="flex: 1; min-width: 150px;">
                            <label>Tanggal Pinjam</label>
                            <input type="date" name="tgl_pinjam" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div style="flex: 100%;">
                            <button type="submit" name="tambah_peminjaman" class="btn">Proses Peminjaman</button>
                        </div>
                    </form>
                </div>

                <script>
                // Script untuk Auto-fill Siswa
                const inputCari = document.getElementById('cari_siswa');
                const inputNpm = document.getElementById('input_npm');
                const inputNama = document.getElementById('input_nama');
                const datalist = document.getElementById('list_students');

                inputCari.addEventListener('input', function() {
                    const val = this.value;
                    let found = false;
                    
                    for (let option of datalist.options) {
                        if (option.value === val) {
                            inputNpm.value = option.getAttribute('data-npm');
                            inputNama.value = option.getAttribute('data-nama');
                            found = true;
                            break;
                        }
                    }
                    
                    if (!found) {
                        inputNpm.value = '';
                        inputNama.value = '';
                    }
                });

                // Script untuk Dropdown/Search Buku Spesifik
                const inputCariBuku = document.getElementById('cari_buku');
                const inputBukuId = document.getElementById('input_buku_id');
                const datalistBuku = document.getElementById('list_buku');

                inputCariBuku.addEventListener('input', function() {
                    const val = this.value;
                    let foundId = '';
                    
                    for (let option of datalistBuku.options) {
                        if (option.value === val) {
                            foundId = option.getAttribute('data-id');
                            break;
                        }
                    }
                    inputBukuId.value = foundId;
                });
                </script>
            </div>


            <!-- ================= TAB 2: BUKU YANG DIPINJAM ================= -->
            <div id="buku-dipinjam" class="dashboard-tab-content <?= ($active_tab == 'buku-dipinjam') ? 'active' : ''; ?>">
                <div class="card">
                    <h3>Spreadsheet Siswa Sedang Meminjam & Perhitungan Denda</h3>
                    
                    <!-- Form Pencarian Peminjam Aktif & Fitur Urutan -->
                    <form method="GET" action="dashboard.php" style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px; flex-wrap: wrap;">
                        <input type="hidden" name="tab" value="buku-dipinjam">
                        
                        <div style="flex: 2; min-width: 240px;">
                            <input type="text" name="cari_peminjam" value="<?= htmlspecialchars($cari_peminjam); ?>" list="list_peminjam_aktif" placeholder="Cari nama atau NIS siswa peminjam..." autocomplete="off" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; box-sizing: border-box;">
                            
                            <datalist id="list_peminjam_aktif">
                                <?php
                                $res_auto = mysqli_query($koneksi, "SELECT DISTINCT npm, nama_siswa FROM peminjaman WHERE status = 'Dipinjam' ORDER BY nama_siswa ASC");
                                while ($auto = mysqli_fetch_assoc($res_auto)) {
                                    echo "<option value=\"" . htmlspecialchars($auto['nama_siswa']) . "\">NIS: {$auto['npm']}</option>";
                                }
                                ?>
                            </datalist>
                        </div>

                        <div style="flex: 1; min-width: 180px;">
                            <select name="urutan" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-size: 0.9rem; box-sizing: border-box;">
                                <option value="Terbaru" <?= ($urutan_aktif === 'Terbaru') ? 'selected' : ''; ?>>Terbaru (Default)</option>
                                <option value="Memiliki Denda" <?= ($urutan_aktif === 'Memiliki Denda') ? 'selected' : ''; ?>>Memiliki Denda</option>
                            </select>
                        </div>

                        <div>
                            <button type="submit" class="btn" style="padding: 8px 16px; font-size: 0.9rem;">Cari / Urutkan</button>
                            <?php if (!empty($cari_peminjam) || $urutan_aktif !== 'Terbaru'): ?>
                                <a href="dashboard.php?tab=buku-dipinjam" class="btn" style="background: #64748b; text-decoration: none; padding: 8px 14px; font-size: 0.9rem; display: inline-block;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="spreadsheet">
                            <thead>
                                <tr>
                                    <th class="col-npm">NIS</th>
                                    <th class="col-nama">Nama Lengkap</th>
                                    <th>Buku Dipinjam</th>
                                    <th class="col-date">Tgl Pinjam</th>
                                    <th class="col-status">Keterlambatan</th>
                                    <th class="col-denda">Denda</th>
                                    <th class="col-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Menyusun Query SQL Berdasarkan Filter Pencarian dan Urutan
                                $where_clause = "WHERE peminjaman.status = 'Dipinjam'";
                                if (!empty($cari_peminjam)) {
                                    $where_clause .= " AND (peminjaman.nama_siswa LIKE '%$cari_peminjam%' OR peminjaman.npm LIKE '%$cari_peminjam%')";
                                }
                                if ($urutan_aktif === 'Memiliki Denda') {
                                    $where_clause .= " AND DATEDIFF(CURDATE(), peminjaman.tgl_pinjam) > 7";
                                }

                                $order_clause = " ORDER BY peminjaman.tgl_pinjam DESC";

                                $q_tot_aktif = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman JOIN buku ON peminjaman.buku_id = buku.id $where_clause");
                                $tot_data_aktif = mysqli_fetch_assoc($q_tot_aktif)['total'];
                                $tot_halaman_aktif = ceil($tot_data_aktif / LIMIT_PER_HALAMAN);

                                $sql_p = "SELECT peminjaman.*, buku.nama_buku 
                                         FROM peminjaman 
                                         JOIN buku ON peminjaman.buku_id = buku.id 
                                         $where_clause 
                                         $order_clause 
                                         LIMIT $offset_aktif, " . LIMIT_PER_HALAMAN;
                                $res_p = mysqli_query($koneksi, $sql_p);

                                if (mysqli_num_rows($res_p) == 0): ?>
                                    <tr><td colspan="7" style="text-align: center; color: #64748b;">Tidak ada data peminjaman aktif yang cocok dengan kriteria pencarian/urutan.</td></tr>
                                <?php else: 
                                    while ($row = mysqli_fetch_assoc($res_p)):
                                        $tgl_pinjam = new DateTime($row['tgl_pinjam']);
                                        $tgl_sekarang = new DateTime(date('Y-m-d'));
                                        $selisih = $tgl_pinjam->diff($tgl_sekarang)->days;

                                        $terlambat_hari = $selisih - BATAS_PINJAM_HARI;
                                        $denda = 0;

                                        if ($terlambat_hari > 0) {
                                            $denda = $terlambat_hari * DENDA_PER_HARI;
                                            if ($denda > MAKS_DENDA) {
                                                $denda = MAKS_DENDA;
                                            }
                                            $status_keterlambatan = "<span class='badge badge-danger'>Terlambat {$terlambat_hari} Hari</span>";
                                        } else {
                                            $status_keterlambatan = "<span class='badge badge-success'>Tepat Waktu</span>";
                                        }

                                        if ($denda > 0) {
                                            $pesan_konfirmasi = "PERINGATAN: Siswa ini memiliki denda Rp " . number_format($denda, 0, ',', '.') . ". Pastikan Anda telah menerima pembayaran tunai yang cukup sebelum memproses pengembalian!";
                                        } else {
                                            $pesan_konfirmasi = "Proses pengembalian buku ini?";
                                        }
                                ?>
                                    <tr>
                                        <td class="col-npm"><?= htmlspecialchars($row['npm']); ?></td>
                                        <td class="col-nama"><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                        <td><?= htmlspecialchars($row['nama_buku']); ?></td>
                                        <td><?= $row['tgl_pinjam']; ?></td>
                                        <td><?= $status_keterlambatan; ?></td>
                                        <td><strong>Rp <?= number_format($denda, 0, ',', '.'); ?></strong></td>
                                        <td>
                                            <a href="dashboard.php?kembalikan=<?= $row['id']; ?>&tab=buku-dipinjam" class="btn btn-sm btn-warning" onclick="return confirm('<?= $pesan_konfirmasi; ?>')">Selesaikan</a>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Navigasi Halaman (Pagination) Buku Dipinjam -->
                    <?php if ($tot_halaman_aktif > 1): ?>
                        <div class="pagination-container">
                            <?php for ($i = 1; $i <= $tot_halaman_aktif; $i++): ?>
                                <a href="dashboard.php?p_aktif=<?= $i; ?>&p_riwayat=<?= $page_riwayat; ?>&tab=buku-dipinjam&cari_peminjam=<?= urlencode($cari_peminjam); ?>&urutan=<?= urlencode($urutan_aktif); ?>" class="pagination-btn <?= ($i == $page_aktif) ? 'active' : ''; ?>">
                                    Halaman <?= $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


            <!-- ================= TAB 3: RIWAYAT PEMINJAMAN (ARSIP) ================= -->
            <div id="riwayat" class="dashboard-tab-content <?= ($active_tab == 'riwayat') ? 'active' : ''; ?>">
                <div class="card">
                    <h3>Log Riwayat Pengembalian & Audit Transaksi</h3>
                    <div class="table-responsive">
                        <table class="spreadsheet">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 120px;">NIS</th>
                                    <th style="width: 180px;">Siswa Meminjam</th>
                                    <th style="width: 180px;">Buku</th>
                                    <th style="width: 110px;">Tgl Pinjam</th>
                                    <th style="width: 110px;">Tgl Kembali</th>
                                    <th style="width: 140px;">Kriteria Pengembalian</th>
                                    <th style="width: 130px;">Denda Terakumulasi</th>
                                    <th style="width: 140px;">Staff Memproses</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $q_tot_riwayat = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Dikembalikan'");
                                $tot_data_riwayat = mysqli_fetch_assoc($q_tot_riwayat)['total'];
                                $tot_halaman_riwayat = ceil($tot_data_riwayat / LIMIT_PER_HALAMAN);

                                $no_r = $offset_riwayat + 1;

                                $sql_riwayat = "SELECT peminjaman.*, buku.nama_buku 
                                                FROM peminjaman 
                                                JOIN buku ON peminjaman.buku_id = buku.id 
                                                WHERE peminjaman.status = 'Dikembalikan' 
                                                ORDER BY peminjaman.tgl_kembali DESC, peminjaman.id DESC 
                                                LIMIT $offset_riwayat, " . LIMIT_PER_HALAMAN;
                                $res_riwayat = mysqli_query($koneksi, $sql_riwayat);

                                if (mysqli_num_rows($res_riwayat) == 0): ?>
                                    <tr><td colspan="9" style="text-align: center; color: #64748b;">Belum ada riwayat peminjaman yang selesai dikembalikan.</td></tr>
                                <?php else: 
                                    while ($rw = mysqli_fetch_assoc($res_riwayat)):
                                        $tp_obj = new DateTime($rw['tgl_pinjam']);
                                        $tk_obj = new DateTime($rw['tgl_kembali']);
                                        $sel_hari = $tp_obj->diff($tk_obj)->days;
                                        $telat_hist = $sel_hari - BATAS_PINJAM_HARI;
                                        $denda_hist = 0;

                                        if ($telat_hist > 0) {
                                            $denda_hist = $telat_hist * DENDA_PER_HARI;
                                            if ($denda_hist > MAKS_DENDA) $denda_hist = MAKS_DENDA;
                                            $kriteria_txt = "<span class='badge badge-danger'>Telat {$telat_hist} Hari</span>";
                                        } else {
                                            $kriteria_txt = "<span class='badge badge-success'>Tepat Waktu</span>";
                                        }

                                        $staff_petugas = !empty($rw['staff_kembali']) ? $rw['staff_kembali'] : ($rw['staff_pinjam'] ?? '-');
                                ?>
                                    <tr>
                                        <td><?= $no_r++; ?></td>
                                        <td><?= htmlspecialchars($rw['npm']); ?></td>
                                        <td><?= htmlspecialchars($rw['nama_siswa']); ?></td>
                                        <td><?= htmlspecialchars($rw['nama_buku']); ?></td>
                                        <td><?= $rw['tgl_pinjam']; ?></td>
                                        <td><?= $rw['tgl_kembali']; ?></td>
                                        <td><?= $kriteria_txt; ?></td>
                                        <td><strong style="color: #059669;">Rp <?= number_format($denda_hist, 0, ',', '.'); ?></strong></td>
                                        <td><strong><?= htmlspecialchars($staff_petugas); ?></strong></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Navigasi Halaman (Pagination) Riwayat -->
                    <?php if ($tot_halaman_riwayat > 1): ?>
                        <div class="pagination-container">
                            <?php for ($j = 1; $j <= $tot_halaman_riwayat; $j++): ?>
                                <a href="dashboard.php?p_aktif=<?= $page_aktif; ?>&p_riwayat=<?= $j; ?>&tab=riwayat" class="pagination-btn <?= ($j == $page_riwayat) ? 'active' : ''; ?>">
                                    Halaman <?= $j; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- SCRIPT JS UNTUK TAB -->
    <script>
    function switchDashboardTab(tabId, btnElement) {
        const contents = document.querySelectorAll('.dashboard-tab-content');
        contents.forEach(content => content.classList.remove('active'));

        const buttons = document.querySelectorAll('.dashboard-tab-btn');
        buttons.forEach(btn => btn.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        btnElement.classList.add('active');
    }
    </script>

</body>
</html>