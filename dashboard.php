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

/**
 * Fungsi Menghitung Hari Keterlambatan Tanpa Hari Minggu
 * Keterlambatan dihitung mulai sehari setelah batas pinjam (H+7) hingga tanggal kembali/sekarang,
 * dengan mengabaikan hari Minggu (format 'N' == 7).
 */
function hitungTerlambatTanpaMinggu($tgl_pinjam_str, $tgl_akhir_str) {
    $tgl_pinjam = new DateTime($tgl_pinjam_str);
    $tgl_akhir = new DateTime($tgl_akhir_str);

    // Tentukan tanggal jatuh tempo (tgl_pinjam + BATAS_PINJAM_HARI)
    $tgl_jatuh_tempo = clone $tgl_pinjam;
    $tgl_jatuh_tempo->modify('+' . BATAS_PINJAM_HARI . ' days');

    // Jika dikembalikan sebelum atau tepat pada tanggal jatuh tempo, tidak terlambat
    if ($tgl_akhir <= $tgl_jatuh_tempo) {
        return 0;
    }

    $hari_terlambat = 0;
    $periode_mulai = clone $tgl_jatuh_tempo;
    $periode_mulai->modify('+1 day'); // Hari pertama terhitung terlambat

    $periode_akhir = clone $tgl_akhir;
    $periode_akhir->modify('+1 day'); // Agar tanggal akhir ikut terhitung dalam DatePeriod

    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($periode_mulai, $interval, $periode_akhir);

    foreach ($daterange as $date) {
        // format('N'): 1 (Senin) sampai 7 (Minggu)
        if ((int)$date->format('N') !== 7) {
            $hari_terlambat++;
        }
    }

    return $hari_terlambat;
}

/**
 * Fungsi Helper Render Navigasi Halaman (Panah Kiri/Kanan, Angka Halaman, & Input Halaman Manual)
 */
function renderPaginationBar($current_page, $total_pages, $param_name, $other_params = []) {
    if ($total_pages <= 1) return;

    $current_page = max(1, min($current_page, $total_pages));

    // Helper pembuat URL
    $buildUrl = function($page_num) use ($param_name, $other_params) {
        $params = array_merge($other_params, [$param_name => $page_num]);
        return 'dashboard.php?' . http_build_query($params);
    };

    echo '<div class="pagination-wrapper">';
    
    // Bagian Kiri: Info Halaman
    echo '<div class="pagination-info">Halaman <strong>' . $current_page . '</strong> dari <strong>' . $total_pages . '</strong></div>';

    // Bagian Tengah: Tombol Panah Kiri, Nomor Halaman, Panah Kanan
    echo '<div class="pagination-controls">';

    // Tombol Panah Kiri (Sebelumnya)
    if ($current_page > 1) {
        echo '<a href="' . htmlspecialchars($buildUrl($current_page - 1)) . '" class="pagination-btn nav-arrow" title="Halaman Sebelumnya">&lsaquo;</a>';
    } else {
        echo '<span class="pagination-btn nav-arrow disabled" title="Halaman Pertama">&lsaquo;</span>';
    }

    // Rentang Angka Halaman dengan Elipsis (...)
    $range = 2; // Jumlah angka di kiri & kanan halaman aktif
    $start = max(1, $current_page - $range);
    $end = min($total_pages, $current_page + $range);

    if ($start > 1) {
        echo '<a href="' . htmlspecialchars($buildUrl(1)) . '" class="pagination-btn">1</a>';
        if ($start > 2) {
            echo '<span class="pagination-ellipsis">...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active_class = ($i == $current_page) ? 'active' : '';
        echo '<a href="' . htmlspecialchars($buildUrl($i)) . '" class="pagination-btn ' . $active_class . '">' . $i . '</a>';
    }

    if ($end < $total_pages) {
        if ($end < $total_pages - 1) {
            echo '<span class="pagination-ellipsis">...</span>';
        }
        echo '<a href="' . htmlspecialchars($buildUrl($total_pages)) . '" class="pagination-btn">' . $total_pages . '</a>';
    }

    // Tombol Panah Kanan (Selanjutnya)
    if ($current_page < $total_pages) {
        echo '<a href="' . htmlspecialchars($buildUrl($current_page + 1)) . '" class="pagination-btn nav-arrow" title="Halaman Selanjutnya">&rsaquo;</a>';
    } else {
        echo '<span class="pagination-btn nav-arrow disabled" title="Halaman Terakhir">&rsaquo;</span>';
    }

    echo '</div>'; // End .pagination-controls

    // Bagian Kanan: Form Input Manual Cari Halaman
    echo '<form method="GET" action="dashboard.php" class="pagination-jump-form">';
    foreach ($other_params as $k => $v) {
        echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
    }
    echo '<label>Ke Hal:</label>';
    echo '<input type="number" name="' . htmlspecialchars($param_name) . '" min="1" max="' . $total_pages . '" value="' . $current_page . '" class="pagination-jump-input" required>';
    echo '<button type="submit" class="pagination-jump-btn">Go</button>';
    echo '</form>';

    echo '</div>'; // End .pagination-wrapper
}

// Ambil nomor halaman, tab aktif, serta parameter pencarian & urutan dari URL
// DEFAULT TAB SEKARANG ADALAH 'analitik'
$page_aktif = isset($_GET['p_aktif']) ? (int)$_GET['p_aktif'] : 1;
$page_riwayat = isset($_GET['p_riwayat']) ? (int)$_GET['p_riwayat'] : 1;
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'analitik'; 

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
    $tgl_input = $_POST['tgl_pinjam'] ?? '';
    $tgl_hari_ini = date('Y-m-d');

    // 0. Validasi Tanggal Pinjam (Tidak boleh tanggal lampau atau masa depan)
    if ($tgl_input !== $tgl_hari_ini) {
        echo "<script>alert('GAGAL: Tanggal peminjaman tidak valid! Tanggal peminjaman hanya boleh diisi dengan tanggal hari ini ({$tgl_hari_ini}).'); window.location='dashboard.php?tab=form-pinjam';</script>";
        exit;
    }
    $tgl_pinjam = $tgl_hari_ini; // Kunci tanggal pinjam menggunakan tanggal server hari ini

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

    // 3. Validasi Blokir jika Akumulasi Denda Mencapai / Melewati Rp 250.000 (Tanpa Hari Minggu)
    $q_denda_aktif = mysqli_query($koneksi, "SELECT tgl_pinjam FROM peminjaman WHERE npm = '$npm' AND status = 'Dipinjam'");
    $total_denda_berjalan = 0;
    $tgl_sekarang_str = date('Y-m-d');

    while ($r_p = mysqli_fetch_assoc($q_denda_aktif)) {
        $terlambat = hitungTerlambatTanpaMinggu($r_p['tgl_pinjam'], $tgl_sekarang_str);
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
if (isset($_POST['proses_pengembalian'])) {
    $id_pinjam = (int)$_POST['id_pinjam'];
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? 'Cash';
    $tgl_sekarang = date('Y-m-d');

    $q = mysqli_query($koneksi, "SELECT peminjaman.*, buku.nama_buku FROM peminjaman JOIN buku ON peminjaman.buku_id = buku.id WHERE peminjaman.id = $id_pinjam");
    $data = mysqli_fetch_assoc($q);

    if ($data) {
        $buku_id = $data['buku_id'];
        $npm = $data['npm'];
        $nama_siswa = $data['nama_siswa'];
        $nama_buku = $data['nama_buku'];
        $tgl_pinjam = $data['tgl_pinjam'];

        // Hitung keterlambatan tanpa hari Minggu
        $terlambat_hari = hitungTerlambatTanpaMinggu($tgl_pinjam, $tgl_sekarang);
        $denda = 0;

        if ($terlambat_hari > 0) {
            $denda = $terlambat_hari * DENDA_PER_HARI;
            if ($denda > MAKS_DENDA) {
                $denda = MAKS_DENDA;
            }

            if ($metode_pembayaran === 'Cash') {
                $nominal_bayar = (float)$_POST['nominal_bayar'];
                if ($nominal_bayar < $denda) {
                    echo "<script>alert('Gagal! Nominal pembayaran cash kurang dari total denda (Rp " . number_format($denda, 0, ',', '.') . ").'); window.location='dashboard.php?tab=buku-dipinjam';</script>";
                    exit;
                }
                $ket_bayar = mysqli_real_escape_string($koneksi, "Pelunasan Denda Cash keterlambatan $terlambat_hari hari (Buku: $nama_buku)");
            } else {
                $ket_bayar = mysqli_real_escape_string($koneksi, "Pelunasan Denda Virtual Account / Digital keterlambatan $terlambat_hari hari (Buku: $nama_buku)");
            }
            
            // Simpan ke tabel pembayaran denda dengan keterangan metode
            mysqli_query($koneksi, "INSERT INTO denda_payments (npm, staff_username, jumlah_bayar, tgl_bayar, keterangan) VALUES ('$npm', '$staff_username', $denda, '$tgl_sekarang', '$ket_bayar')");

            // TERINTEGRASI DENGAN AUDIT HEADMASTER: Catat detail metode pembayaran secara eksplisit ke audit_logs
            $log_denda_raw = "Menerima pembayaran denda sebesar Rp " . number_format($denda, 0, ',', '.') . " via " . strtoupper($metode_pembayaran) . " dari siswa $nama_siswa (NIS: $npm)";
            $log_denda = mysqli_real_escape_string($koneksi, $log_denda_raw);
            mysqli_query($koneksi, "INSERT INTO audit_logs (staff_username, aksi, detail) VALUES ('$staff_username', 'Pemrosesan Pembayaran Denda', '$log_denda')");
        }

        // Update status peminjaman menjadi dikembalikan
        mysqli_query($koneksi, "UPDATE peminjaman SET status = 'Dikembalikan', tgl_kembali = '$tgl_sekarang', staff_kembali = '$staff_username' WHERE id = $id_pinjam");
        mysqli_query($koneksi, "UPDATE buku SET stok = stok + 1 WHERE id = $buku_id");

        // Log audit pengembalian buku
        $log_kembali_raw = "Menerima pengembalian buku '$nama_buku' dari siswa $nama_siswa (NIS: $npm)";
        $log_kembali = mysqli_real_escape_string($koneksi, $log_kembali_raw);
        mysqli_query($koneksi, "INSERT INTO audit_logs (staff_username, aksi, detail) VALUES ('$staff_username', 'Pemrosesan Pengembalian Buku', '$log_kembali')");
    }

    header("Location: dashboard.php?tab=buku-dipinjam");
    exit;
}

// =========================================================================
// HITUNG STATISTIK UTAMA & DATA ANALITIK GRAFIK DASHBOARD STAFF
// =========================================================================
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

// --- 1. DATA GRAFIK AKTIVITAS SIRKULASI HARIAN (SENIN - SABTU PEKAN INI) ---
$hari_ini_obj = new DateTime(date('Y-m-d'));
$hari_ke = (int)$hari_ini_obj->format('N'); // 1 (Senin) s/d 7 (Minggu)
$senin_pekan_ini = clone $hari_ini_obj;
$senin_pekan_ini->modify('-' . ($hari_ke - 1) . ' days');

$label_hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$data_pinjam_harian = [];
$data_kembali_harian = [];
$data_denda_harian = []; // Untuk Fitur 4: Sparkline Denda Harian Pekan Ini

for ($idx = 0; $idx < 6; $idx++) {
    $tgl_cek_obj = clone $senin_pekan_ini;
    $tgl_cek_obj->modify("+{$idx} days");
    $tgl_cek = $tgl_cek_obj->format('Y-m-d');

    // Jumlah peminjaman pada hari tersebut
    $qp = mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM peminjaman WHERE tgl_pinjam = '$tgl_cek'");
    $data_pinjam_harian[] = (int)mysqli_fetch_assoc($qp)['jml'];

    // Jumlah pengembalian pada hari tersebut
    $qk = mysqli_query($koneksi, "SELECT COUNT(*) as jml FROM peminjaman WHERE status = 'Dikembalikan' AND tgl_kembali = '$tgl_cek'");
    $data_kembali_harian[] = (int)mysqli_fetch_assoc($qk)['jml'];

    // Akumulasi denda tercatat pada hari tersebut (dari tabel denda_payments)
    $qd = mysqli_query($koneksi, "SELECT SUM(jumlah_bayar) as total_denda FROM denda_payments WHERE tgl_bayar = '$tgl_cek'");
    $denda_tercatat = (int)(mysqli_fetch_assoc($qd)['total_denda'] ?? 0);
    $data_denda_harian[] = $denda_tercatat;
}

// --- 2. DATA GRAFIK STATUS KETERLAMBATAN BUKU (OVERDUE STATUS BREAKDOWN) ---
$kat_aman = 0;
$kat_tenggat = 0;
$kat_ringan = 0;
$kat_berat = 0;
$total_potensi_denda_aktif = 0;

$q_all_aktif = mysqli_query($koneksi, "SELECT tgl_pinjam FROM peminjaman WHERE status = 'Dipinjam'");
$tgl_sekarang_str = date('Y-m-d');
$tgl_sekarang_dt = new DateTime($tgl_sekarang_str);

while ($r_akt = mysqli_fetch_assoc($q_all_aktif)) {
    $tp_dt = new DateTime($r_akt['tgl_pinjam']);
    $tempo_dt = clone $tp_dt;
    $tempo_dt->modify('+' . BATAS_PINJAM_HARI . ' days');

    if ($tgl_sekarang_dt <= $tempo_dt) {
        $sisa_hr = $tgl_sekarang_dt->diff($tempo_dt)->days;
        if ($sisa_hr > 2) {
            $kat_aman++;
        } else {
            // Jatuh tempo hari ini, besok, atau lusa (<= 2 hari)
            $kat_tenggat++;
        }
    } else {
        $telat_hr = hitungTerlambatTanpaMinggu($r_akt['tgl_pinjam'], $tgl_sekarang_str);
        if ($telat_hr == 0) {
            // Jatuh tempo Sabtu & hari ini Minggu
            $kat_tenggat++;
        } elseif ($telat_hr >= 1 && $telat_hr <= 3) {
            $kat_ringan++;
        } else {
            $kat_berat++;
        }

        if ($telat_hr > 0) {
            $nom_denda = min($telat_hr * DENDA_PER_HARI, MAKS_DENDA);
            $total_potensi_denda_aktif += $nom_denda;
        }
    }
}

// --- 3. DATA GRAFIK GENRE / KATEGORI BUKU PALING AKTIF KELUAR SAAT INI ---
$kolom_genre = null;
$kandidat_kolom = ['kategori', 'genre', 'jenis_buku', 'subjek'];
foreach ($kandidat_kolom as $kol) {
    $cek_kol = mysqli_query($koneksi, "SHOW COLUMNS FROM buku LIKE '$kol'");
    if ($cek_kol && mysqli_num_rows($cek_kol) > 0) {
        $kolom_genre = $kol;
        break;
    }
}

$label_genre_top = [];
$data_genre_top = [];

if ($kolom_genre) {
    $q_top_genre = mysqli_query($koneksi, "
        SELECT COALESCE(NULLIF(TRIM(buku.$kolom_genre), ''), 'Umum / Lainnya') AS nama_genre, COUNT(peminjaman.id) AS total_keluar
        FROM peminjaman
        JOIN buku ON peminjaman.buku_id = buku.id
        WHERE peminjaman.status = 'Dipinjam' OR MONTH(peminjaman.tgl_pinjam) = MONTH(CURDATE())
        GROUP BY nama_genre
        ORDER BY total_keluar DESC
        LIMIT 5
    ");
} else {
    $q_top_genre = mysqli_query($koneksi, "
        SELECT buku.nama_buku AS nama_genre, COUNT(peminjaman.id) AS total_keluar
        FROM peminjaman
        JOIN buku ON peminjaman.buku_id = buku.id
        WHERE peminjaman.status = 'Dipinjam' OR MONTH(peminjaman.tgl_pinjam) = MONTH(CURDATE())
        GROUP BY buku.id
        ORDER BY total_keluar DESC
        LIMIT 5
    ");
}

if ($q_top_genre) {
    while ($rg = mysqli_fetch_assoc($q_top_genre)) {
        $label_genre_top[] = $rg['nama_genre'];
        $data_genre_top[] = (int)$rg['total_keluar'];
    }
}
if (empty($label_genre_top)) {
    $label_genre_top = ['Belum Ada Data'];
    $data_genre_top = [0];
}

// --- 4. RINGKASAN DENDA MINGGU INI & HARI INI ---
$total_denda_minggu_ini = array_sum($data_denda_harian);
$denda_hari_ini_idx = min(max($hari_ke - 1, 0), 5);
$denda_terkumpul_hari_ini = $data_denda_harian[$denda_hari_ini_idx] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Digital Library Web Manager (DLWM)</title>
    <link rel="stylesheet" href="style.css">
    <!-- Load Chart.js untuk Visualisasi Analitik Dashboard Staff -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Grid Visualisasi Analitik Staff */
        .analytics-section {
            margin-top: 5px;
            margin-bottom: 25px;
        }
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }
        @media (max-width: 1024px) {
            .analytics-grid {
                grid-template-columns: 1fr;
            }
        }
        .analytics-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
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
        .analytics-header h4 {
            margin: 0;
            font-size: 0.98rem;
            color: #1e293b;
            font-weight: 700;
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
            background: #f1f5f9;
            color: #475569;
            white-space: nowrap;
        }
        .chart-box {
            position: relative;
            height: 220px;
            width: 100%;
        }

        /* Kartu Khusus Sparkline Denda Harian */
        .denda-kpi-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }
        .denda-kpi-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
        }
        .denda-kpi-item .kpi-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 600;
        }
        .denda-kpi-item .kpi-val {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 4px;
        }

        .dashboard-tab-container {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            flex-wrap: wrap;
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

        /* Styling Pagination Modern (Angka, Panah Kiri/Kanan, & Lompat Halaman Manual) */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        .pagination-info {
            font-size: 0.85rem;
            color: #64748b;
        }
        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.15s ease;
            box-sizing: border-box;
        }
        .pagination-btn:hover:not(.disabled):not(.active) {
            background: #f1f5f9;
            border-color: #0284c7;
            color: #0284c7;
        }
        .pagination-btn.active {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
            box-shadow: 0 1px 3px rgba(2, 132, 199, 0.25);
        }
        .pagination-btn.nav-arrow {
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1;
        }
        .pagination-btn.disabled {
            background: #f8fafc;
            color: #cbd5e1;
            border-color: #e2e8f0;
            cursor: not-allowed;
        }
        .pagination-ellipsis {
            color: #94a3b8;
            font-weight: 700;
            padding: 0 4px;
            font-size: 0.9rem;
        }
        .pagination-jump-form {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 0;
        }
        .pagination-jump-form label {
            font-size: 0.85rem;
            color: #475569;
            font-weight: 600;
            margin: 0;
        }
        .pagination-jump-input {
            width: 58px !important;
            height: 34px;
            padding: 4px 8px !important;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.85rem;
            text-align: center;
            font-weight: 600;
            color: #1e293b;
            box-sizing: border-box;
        }
        .pagination-jump-input:focus {
            outline: none;
            border-color: #0284c7;
        }
        .pagination-jump-btn {
            height: 34px;
            padding: 0 12px;
            background: #0f172a;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        .pagination-jump-btn:hover {
            background: #0284c7;
        }

        /* Styling Modal Pembayaran Denda */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .modal-card h3 {
            margin-top: 0;
            color: #1e293b;
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

            <!-- KARTU STATISTIK RINGKAS -->
            <div class="stat-container">
                <div class="stat-card">
                    <h4>Total Stok Buku Tersedia</h4>
                    <div class="number"><?= number_format($total_stok_buku); ?></div>
                </div>
                <div class="stat-card" style="border-left-color: #06b6d4;">
                    <h4>Jumlah Jenis Buku</h4>
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

            <!-- TOMBOL TAB NAVIGASI HORIZONTAL (ANALITIK SEBAGAI TAB PERTAMA & DEFAULT) -->
            <div class="dashboard-tab-container">
                <button type="button" class="dashboard-tab-btn <?= ($active_tab == 'analitik') ? 'active' : ''; ?>" onclick="switchDashboardTab('analitik', this)">
                    📊 Analitik & Statistik
                </button>
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


            <!-- ================= TAB 0 (DEFAULT): ANALITIK & STATISTIK ================= -->
            <div id="analitik" class="dashboard-tab-content <?= ($active_tab == 'analitik') ? 'active' : ''; ?>">
                <div class="analytics-section">
                    <div class="analytics-grid">
                        
                        <!-- 1. Grafik Aktivitas Sirkulasi Harian (Peminjaman vs Pengembalian) -->
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <div>
                                    <h4>📊 Aktivitas Sirkulasi Harian (Senin – Sabtu)</h4>
                                    <div class="analytics-subtitle">Perbandingan jumlah peminjaman & pengembalian buku pekan berjalan</div>
                                </div>
                                <span class="analytics-badge">Pekan Ini</span>
                            </div>
                            <div class="chart-box">
                                <canvas id="chartSirkulasiHarian"></canvas>
                            </div>
                        </div>

                        <!-- 2. Grafik Status Keterlambatan Buku (Overdue Status Breakdown) -->
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <div>
                                    <h4>⏳ Proporsi Status Keterlambatan Buku</h4>
                                    <div class="analytics-subtitle">Pemetaan status buku aktif di tangan siswa saat ini</div>
                                </div>
                                <span class="analytics-badge"><?= $total_outgoing; ?> Pinjaman Aktif</span>
                            </div>
                            <div class="chart-box">
                                <canvas id="chartStatusKeterlambatan"></canvas>
                            </div>
                        </div>

                        <!-- 3. Grafik Genre / Kategori Buku Paling Aktif Keluar -->
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <div>
                                    <h4>🔥 Top 5 <?= $kolom_genre ? 'Genre / Kategori' : 'Koleksi Buku'; ?> Paling Aktif Keluar</h4>
                                    <div class="analytics-subtitle">Preferensi bacaan yang paling banyak beredar bulan ini</div>
                                </div>
                                <span class="analytics-badge">Real-Time</span>
                            </div>
                            <div class="chart-box">
                                <canvas id="chartGenrePopuler"></canvas>
                            </div>
                        </div>

                        <!-- 4. Ringkasan Status Denda Harian (Sparkline & Indikator Tren) -->
                        <div class="analytics-card">
                            <div class="analytics-header">
                                <div>
                                    <h4>💰 Ringkasan & Tren Status Denda Harian</h4>
                                    <div class="analytics-subtitle">Pantauan pelunasan denda harian & potensi denda berjalan</div>
                                </div>
                                <span class="analytics-badge" style="background: #fef2f2; color: #dc2626;">Maks Rp 250rb/Siswa</span>
                            </div>

                            <div class="denda-kpi-row">
                                <div class="denda-kpi-item">
                                    <div class="kpi-label">Denda Masuk Hari Ini</div>
                                    <div class="kpi-val" style="color: #059669;">Rp <?= number_format($denda_terkumpul_hari_ini, 0, ',', '.'); ?></div>
                                </div>
                                <div class="denda-kpi-item">
                                    <div class="kpi-label">Akumulasi Pekan Ini</div>
                                    <div class="kpi-val" style="color: #0284c7;">Rp <?= number_format($total_denda_minggu_ini, 0, ',', '.'); ?></div>
                                </div>
                                <div class="denda-kpi-item">
                                    <div class="kpi-label">Potensi Denda Aktif</div>
                                    <div class="kpi-val" style="color: #dc2626;">Rp <?= number_format($total_potensi_denda_aktif, 0, ',', '.'); ?></div>
                                </div>
                            </div>

                            <div class="chart-box" style="height: 135px;">
                                <canvas id="chartSparklineDenda"></canvas>
                            </div>
                        </div>

                    </div>
                </div>
            </div>


            <!-- ================= TAB 1: FORM PEMINJAMAN ================= -->
            <div id="form-pinjam" class="dashboard-tab-content <?= ($active_tab == 'form-pinjam') ? 'active' : ''; ?>">
                <div class="card">
                    <h3>Proses Peminjaman Buku</h3>
                    <form action="" method="POST" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
                        
                        <div class="form-group" style="flex: 2; min-width: 230px; margin-bottom: 0;">
                            <label style="display: block; margin-bottom: 6px; white-space: nowrap;">Cari Siswa (Ketik NIS atau Nama)</label>
                            <input type="text" id="cari_siswa" list="list_students" placeholder="Ketik untuk mencari..." autocomplete="off" required style="width: 100%; height: 38px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem; box-sizing: border-box;">
                            
                            <datalist id="list_students">
                                <?php
                                $res_s = mysqli_query($koneksi, "SELECT * FROM students ORDER BY nama_lengkap ASC");
                                while ($s = mysqli_fetch_assoc($res_s)) {
                                    echo "<option value='{$s['npm']} - {$s['nama_lengkap']}' data-npm='{$s['npm']}' data-nama='{$s['nama_lengkap']}'></option>";
                                }
                                ?>
                            </datalist>
                        </div>

                        <div class="form-group" style="flex: 1; min-width: 140px; margin-bottom: 0;">
                            <label style="display: block; margin-bottom: 6px; white-space: nowrap;">NIS Siswa</label>
                            <input type="text" name="npm" id="input_npm" readonly required placeholder="Otomatis terisi" style="width: 100%; height: 38px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem; background-color: #f8fafc; box-sizing: border-box;">
                        </div>

                        <div class="form-group" style="flex: 1.5; min-width: 190px; margin-bottom: 0;">
                            <label style="display: block; margin-bottom: 6px; white-space: nowrap;">Nama Lengkap</label>
                            <input type="text" name="nama_siswa" id="input_nama" readonly required placeholder="Otomatis terisi" style="width: 100%; height: 38px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem; background-color: #f8fafc; box-sizing: border-box;">
                        </div>

                        <div class="form-group" style="flex: 2; min-width: 250px; margin-bottom: 0;">
                            <label style="display: block; margin-bottom: 6px; white-space: nowrap;">Pilih Buku Spesifik (Ketik Judul / No. Inventory)</label>
                            <input type="text" id="cari_buku" list="list_buku" placeholder="Ketik judul buku / barcode..." autocomplete="off" required style="width: 100%; height: 38px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem; box-sizing: border-box;">
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

                        <div class="form-group" style="flex: 1; min-width: 175px; margin-bottom: 0;">
                            <label style="display: block; margin-bottom: 6px; white-space: nowrap;">Tanggal Pinjam (Hari Ini)</label>
                            <input type="date" name="tgl_pinjam" value="<?= date('Y-m-d'); ?>" min="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" readonly required style="width: 100%; height: 38px; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.9rem; background-color: #f8fafc; cursor: not-allowed; box-sizing: border-box;">
                        </div>

                        <div style="flex: 100%; margin-top: 5px;">
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
                                    <th class="col-date">Masa Jatuh Tempo</th>
                                    <th class="col-status">Status / Keterlambatan</th>
                                    <th class="col-denda">Denda</th>
                                    <th class="col-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $where_clause = "WHERE peminjaman.status = 'Dipinjam'";
                                if (!empty($cari_peminjam)) {
                                    $where_clause .= " AND (peminjaman.nama_siswa LIKE '%$cari_peminjam%' OR peminjaman.npm LIKE '%$cari_peminjam%')";
                                }
                                
                                // Jika filter "Memiliki Denda" dipilih, saring ID yang benar-benar terlambat (mengecualikan hari Minggu)
                                if ($urutan_aktif === 'Memiliki Denda') {
                                    $ids_denda = [];
                                    $q_cek_denda = mysqli_query($koneksi, "SELECT id, tgl_pinjam FROM peminjaman WHERE status = 'Dipinjam'");
                                    $tgl_hari_ini = date('Y-m-d');
                                    while ($r_cd = mysqli_fetch_assoc($q_cek_denda)) {
                                        if (hitungTerlambatTanpaMinggu($r_cd['tgl_pinjam'], $tgl_hari_ini) > 0) {
                                            $ids_denda[] = (int)$r_cd['id'];
                                        }
                                    }
                                    if (!empty($ids_denda)) {
                                        $where_clause .= " AND peminjaman.id IN (" . implode(',', $ids_denda) . ")";
                                    } else {
                                        $where_clause .= " AND 1=0";
                                    }
                                }

                                $order_clause = " ORDER BY peminjaman.tgl_pinjam DESC";

                                $q_tot_aktif = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman JOIN buku ON peminjaman.buku_id = buku.id $where_clause");
                                $tot_data_aktif = mysqli_fetch_assoc($q_tot_aktif)['total'];
                                $tot_halaman_aktif = (int)ceil($tot_data_aktif / LIMIT_PER_HALAMAN);

                                // Koreksi jika user mengetik halaman melebihi total halaman
                                if ($tot_halaman_aktif > 0 && $page_aktif > $tot_halaman_aktif) {
                                    $page_aktif = $tot_halaman_aktif;
                                    $offset_aktif = ($page_aktif - 1) * LIMIT_PER_HALAMAN;
                                }

                                $sql_p = "SELECT peminjaman.*, buku.nama_buku 
                                          FROM peminjaman 
                                          JOIN buku ON peminjaman.buku_id = buku.id 
                                          $where_clause 
                                          $order_clause 
                                          LIMIT $offset_aktif, " . LIMIT_PER_HALAMAN;
                                $res_p = mysqli_query($koneksi, $sql_p);

                                if (mysqli_num_rows($res_p) == 0): ?>
                                    <tr><td colspan="8" style="text-align: center; color: #64748b;">Tidak ada data peminjaman aktif yang cocok dengan kriteria pencarian/urutan.</td></tr>
                                <?php else: 
                                    $tgl_sekarang_str = date('Y-m-d');
                                    $tgl_sekarang_obj = new DateTime($tgl_sekarang_str);

                                    while ($row = mysqli_fetch_assoc($res_p)):
                                        // Hitung Tanggal Jatuh Tempo (Tgl Pinjam + 7 Hari)
                                        $tgl_pinjam_obj = new DateTime($row['tgl_pinjam']);
                                        $tgl_tempo_obj = clone $tgl_pinjam_obj;
                                        $tgl_tempo_obj->modify('+' . BATAS_PINJAM_HARI . ' days');
                                        $tgl_tempo_str = $tgl_tempo_obj->format('Y-m-d');

                                        // Hitung keterlambatan tanpa hari Minggu
                                        $terlambat_hari = hitungTerlambatTanpaMinggu($row['tgl_pinjam'], $tgl_sekarang_str);
                                        $denda = 0;

                                        // Tentukan tampilan Masa Jatuh Tempo & Status Keterlambatan
                                        if ($tgl_sekarang_obj <= $tgl_tempo_obj) {
                                            $sisa_hari = $tgl_sekarang_obj->diff($tgl_tempo_obj)->days;
                                            if ($sisa_hari == 0) {
                                                $info_tempo = "<strong>{$tgl_tempo_str}</strong><br><small style='color: #d97706; font-weight: 600;'>(Hari Terakhir)</small>";
                                            } else {
                                                $info_tempo = "<strong>{$tgl_tempo_str}</strong><br><small style='color: #0284c7; font-weight: 600;'>(Sisa {$sisa_hari} Hari)</small>";
                                            }
                                            $status_keterlambatan = "<span class='badge badge-success'>Tepat Waktu</span>";
                                        } else {
                                            // Sudah melewati tanggal jatuh tempo
                                            if ($terlambat_hari > 0) {
                                                $denda = $terlambat_hari * DENDA_PER_HARI;
                                                if ($denda > MAKS_DENDA) {
                                                    $denda = MAKS_DENDA;
                                                }
                                                $info_tempo = "<strong style='color: #dc2626;'>{$tgl_tempo_str}</strong><br><small style='color: #dc2626; font-weight: 600;'>(Menunggak)</small>";
                                                $status_keterlambatan = "<span class='badge badge-danger'>Terlambat {$terlambat_hari} Hari</span>";
                                            } else {
                                                // Jatuh tempo kemarin (Sabtu) dan hari ini Minggu (Bebas Denda)
                                                $info_tempo = "<strong style='color: #d97706;'>{$tgl_tempo_str}</strong><br><small style='color: #059669; font-weight: 600;'>(Libur Minggu)</small>";
                                                $status_keterlambatan = "<span class='badge badge-success'>Tepat Waktu</span>";
                                            }
                                        }
                                ?>
                                    <tr>
                                        <td class="col-npm"><?= htmlspecialchars($row['npm']); ?></td>
                                        <td class="col-nama"><?= htmlspecialchars($row['nama_siswa']); ?></td>
                                        <td><?= htmlspecialchars($row['nama_buku']); ?></td>
                                        <td><?= $row['tgl_pinjam']; ?></td>
                                        <td><?= $info_tempo; ?></td>
                                        <td><?= $status_keterlambatan; ?></td>
                                        <td><strong>Rp <?= number_format($denda, 0, ',', '.'); ?></strong></td>
                                        <td>
                                            <?php if ($denda > 0): ?>
                                                <!-- Jika ada denda, buka modal pembayaran -->
                                                <button type="button" class="btn btn-sm btn-warning" onclick="bukaModalDenda(<?= $row['id']; ?>, '<?= htmlspecialchars($row['nama_siswa']); ?>', '<?= htmlspecialchars($row['nama_buku']); ?>', <?= $denda; ?>)">Selesaikan</button>
                                            <?php else: ?>
                                                <!-- Jika tidak ada denda, langsung kembalikan -->
                                                <form action="" method="POST" style="display:inline;">
                                                    <input type="hidden" name="id_pinjam" value="<?= $row['id']; ?>">
                                                    <input type="hidden" name="metode_pembayaran" value="Cash">
                                                    <button type="submit" name="proses_pengembalian" class="btn btn-sm btn-warning" onclick="return confirm('Proses pengembalian buku ini?')">Selesaikan</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Navigasi Halaman (Pagination) Buku Dipinjam -->
                    <?php
                    renderPaginationBar($page_aktif, $tot_halaman_aktif, 'p_aktif', [
                        'p_riwayat'     => $page_riwayat,
                        'tab'           => 'buku-dipinjam',
                        'cari_peminjam' => $cari_peminjam,
                        'urutan'        => $urutan_aktif
                    ]);
                    ?>
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
                                    <th style="width: 100px;">NIS</th>
                                    <th style="width: 150px;">Siswa Meminjam</th>
                                    <th style="width: 150px;">Buku</th>
                                    <th style="width: 90px;">Tgl Pinjam</th>
                                    <th style="width: 90px;">Tgl Kembali</th>
                                    <th style="width: 120px;">Kriteria</th>
                                    <th style="width: 110px;">Denda</th>
                                    <th style="width: 110px;">Metode Bayar</th>
                                    <th style="width: 120px;">Staff</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // 1. Inisialisasi perhitungan total data dan halaman riwayat
                                $q_tot_riwayat = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'Dikembalikan'");
                                $tot_data_riwayat = mysqli_fetch_assoc($q_tot_riwayat)['total'] ?? 0;
                                $tot_halaman_riwayat = (int)ceil($tot_data_riwayat / LIMIT_PER_HALAMAN);

                                // Koreksi jika user mengetik halaman melebihi total halaman riwayat
                                if ($tot_halaman_riwayat > 0 && $page_riwayat > $tot_halaman_riwayat) {
                                    $page_riwayat = $tot_halaman_riwayat;
                                    $offset_riwayat = ($page_riwayat - 1) * LIMIT_PER_HALAMAN;
                                }

                                // 2. Inisialisasi variabel penomoran baris ($no_r) agar tidak undefined
                                $no_r = $offset_riwayat + 1;

                                // 3. Inisialisasi variabel kueri SQL riwayat ($sql_riwayat)
                                $sql_riwayat = "SELECT peminjaman.*, buku.nama_buku 
                                                FROM peminjaman 
                                                JOIN buku ON peminjaman.buku_id = buku.id 
                                                WHERE peminjaman.status = 'Dikembalikan' 
                                                ORDER BY peminjaman.tgl_kembali DESC, peminjaman.id DESC 
                                                LIMIT $offset_riwayat, " . LIMIT_PER_HALAMAN;
                                
                                $res_riwayat = mysqli_query($koneksi, $sql_riwayat);

                                if (!$res_riwayat || mysqli_num_rows($res_riwayat) == 0): ?>
                                    <tr><td colspan="10" style="text-align: center; color: #64748b;">Belum ada riwayat peminjaman yang selesai dikembalikan.</td></tr>
                                <?php else: 
                                    while ($rw = mysqli_fetch_assoc($res_riwayat)):
                                        // Hitung keterlambatan riwayat tanpa hari Minggu
                                        $telat_hist = hitungTerlambatTanpaMinggu($rw['tgl_pinjam'], $rw['tgl_kembali']);
                                        $denda_hist = 0;
                                        $metode_tampil = '-';

                                        if ($telat_hist > 0) {
                                            $denda_hist = $telat_hist * DENDA_PER_HARI;
                                            if ($denda_hist > MAKS_DENDA) $denda_hist = MAKS_DENDA;
                                            $kriteria_txt = "<span class='badge badge-danger'>Telat {$telat_hist} Hari</span>";
                                            
                                            // Deteksi metode pembayaran dari tabel denda_payments
                                            $q_det_bayar = mysqli_query($koneksi, "SELECT keterangan FROM denda_payments WHERE npm = '{$rw['npm']}' AND tgl_bayar = '{$rw['tgl_kembali']}' LIMIT 1");
                                            if ($q_det_bayar && mysqli_num_rows($q_det_bayar) > 0) {
                                                $d_bayar = mysqli_fetch_assoc($q_det_bayar);
                                                if (strpos(strtolower($d_bayar['keterangan']), 'virtual account') !== false || strpos(strtolower($d_bayar['keterangan']), 'digital') !== false) {
                                                    $metode_tampil = "<span class='badge' style='background:#8b5cf6; color:#fff;'>Digital (VA)</span>";
                                                } else {
                                                    $metode_tampil = "<span class='badge' style='background:#059669; color:#fff;'>Cash (Tunai)</span>";
                                                }
                                            } else {
                                                $metode_tampil = "Cash (Tunai)";
                                            }
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
                                        <td><?= $metode_tampil; ?></td>
                                        <td><strong><?= htmlspecialchars($staff_petugas); ?></strong></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Navigasi Halaman (Pagination) Riwayat -->
                    <?php
                    renderPaginationBar($page_riwayat, $tot_halaman_riwayat, 'p_riwayat', [
                        'p_aktif'       => $page_aktif,
                        'tab'           => 'riwayat',
                        'cari_peminjam' => $cari_peminjam,
                        'urutan'        => $urutan_aktif
                    ]);
                    ?>
                </div>
            </div>

        </div> <!-- End Container -->
    </div> <!-- End Main Content -->

    <!-- MODAL PEMBAYARAN DENDA -->
    <div id="modalDenda" class="modal-overlay">
        <div class="modal-card">
            <h3>Konfirmasi Pembayaran Denda</h3>
            <p id="infoDendaBuku" style="font-size: 0.9rem; color: #475569; margin-bottom: 15px;"></p>
            
            <form action="" method="POST">
                <input type="hidden" name="id_pinjam" id="modal_id_pinjam">
                
                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Pilih Metode Pembayaran</label>
                    <select name="metode_pembayaran" id="pilihan_metode" onchange="ubahMetodePembayaran()" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        <option value="Cash">Cash (Tunai)</option>
                        <option value="Digital">Digital (Virtual Account)</option>
                    </select>
                </div>

                <!-- Bagian Form Khusus Cash -->
                <div id="wrapper_cash" class="form-group" style="margin-bottom: 15px;">
                    <label style="font-size: 0.85rem; font-weight: 600;">Nominal Pembayaran</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="number" name="nominal_bayar" id="input_nominal_bayar" placeholder="Masukkan nominal..." style="flex: 1; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        <button type="button" class="btn" style="background: #059669; padding: 8px 12px; font-size: 0.85rem;" onclick="isiLunas()">Lunas</button>
                    </div>
                </div>

                <!-- Bagian Info Khusus Digital (VA) -->
                <div id="wrapper_digital" style="display: none; margin-bottom: 15px; background: #f8fafc; padding: 10px; border: 1px dashed #cbd5e1; border-radius: 4px;">
                    <p style="font-size: 0.85rem; margin: 0; color: #1e293b;"><strong>Virtual Account (VA):</strong> 88812345<span id="va_npm"></span></p>
                    <p style="font-size: 0.8rem; margin: 5px 0 0 0; color: #64748b;">Silakan instruksikan siswa untuk membayar via m-Banking sejumlah total denda.</p>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" style="background: #64748b;" onclick="tutupModalDenda()">Batal</button>
                    <button type="submit" name="proses_pengembalian" class="btn">Konfirmasi & Selesaikan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- SCRIPT JS UNTUK TAB, MODAL, DAN CHART ANALITIK -->
    <script>
    // Konfigurasi Ketajaman Teks Grafik (High-DPI Anti-Blur)
    Chart.defaults.devicePixelRatio = Math.max(window.devicePixelRatio || 1, 2);
    Chart.defaults.font.family = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    Chart.defaults.color = '#334155';

    function switchDashboardTab(tabId, btnElement) {
        const contents = document.querySelectorAll('.dashboard-tab-content');
        contents.forEach(content => content.classList.remove('active'));

        const buttons = document.querySelectorAll('.dashboard-tab-btn');
        buttons.forEach(btn => btn.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');
        btnElement.classList.add('active');
    }

    let globalTotalDenda = 0;

    function bukaModalDenda(idPinjam, namaSiswa, namaBuku, totalDenda) {
        globalTotalDenda = totalDenda;
        document.getElementById('modal_id_pinjam').value = idPinjam;
        document.getElementById('infoDendaBuku').innerHTML = `Siswa <strong>${namaSiswa}</strong> meminjam buku <em>"${namaBuku}"</em> memiliki tunggakan denda sebesar <strong>Rp ${totalDenda.toLocaleString('id-ID')}</strong>.`;
        document.getElementById('input_nominal_bayar').value = '';
        document.getElementById('pilihan_metode').value = 'Cash';
        ubahMetodePembayaran();
        
        document.getElementById('modalDenda').style.display = 'flex';
    }

    function tutupModalDenda() {
        document.getElementById('modalDenda').style.display = 'none';
    }

    function isiLunas() {
        document.getElementById('input_nominal_bayar').value = globalTotalDenda;
    }

    function ubahMetodePembayaran() {
        const metode = document.getElementById('pilihan_metode').value;
        const wrapperCash = document.getElementById('wrapper_cash');
        const wrapperDigital = document.getElementById('wrapper_digital');
        const inputNominal = document.getElementById('input_nominal_bayar');

        if (metode === 'Digital') {
            wrapperCash.style.display = 'none';
            wrapperDigital.style.display = 'block';
            inputNominal.value = globalTotalDenda;
        } else {
            wrapperCash.style.display = 'block';
            wrapperDigital.style.display = 'none';
            inputNominal.value = '';
        }
    }

    // =========================================================================
    // INISIALISASI 4 GRAFIK ANALITIK STAFF (CHART.JS)
    // =========================================================================
    const labelHari = <?= json_encode($label_hari); ?>;
    const dataPinjamHarian = <?= json_encode($data_pinjam_harian); ?>;
    const dataKembaliHarian = <?= json_encode($data_kembali_harian); ?>;
    const dataDendaHarian = <?= json_encode($data_denda_harian); ?>;

    // 1. Bar Chart: Aktivitas Sirkulasi Harian (Senin - Sabtu)
    new Chart(document.getElementById('chartSirkulasiHarian'), {
        type: 'bar',
        data: {
            labels: labelHari,
            datasets: [
                {
                    label: 'Buku Dipinjam',
                    data: dataPinjamHarian,
                    backgroundColor: '#0284c7',
                    borderRadius: 5
                },
                {
                    label: 'Buku Dikembalikan',
                    data: dataKembaliHarian,
                    backgroundColor: '#10b981',
                    borderRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { boxWidth: 12, font: { size: 11, weight: '600' } } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // 2. Doughnut Chart: Status Keterlambatan Buku (Overdue Status Breakdown)
    new Chart(document.getElementById('chartStatusKeterlambatan'), {
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
            cutout: '62%',
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11, weight: '600' } } }
            }
        }
    });

    // 3. Horizontal Bar Chart: Top 5 Genre / Koleksi Buku Paling Aktif Keluar
    new Chart(document.getElementById('chartGenrePopuler'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($label_genre_top); ?>,
            datasets: [{
                label: 'Frekuensi Dipinjam',
                data: <?= json_encode($data_genre_top); ?>,
                backgroundColor: ['#8b5cf6', '#6366f1', '#06b6d4', '#0284c7', '#14b8a6'],
                borderRadius: 5
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
                x: { beginAtZero: true, ticks: { precision: 0 } }
            }
        }
    });

    // 4. Sparkline Line Chart: Tren Akumulasi Denda Harian Pekan Ini
    new Chart(document.getElementById('chartSparklineDenda'), {
        type: 'line',
        data: {
            labels: labelHari,
            datasets: [{
                label: 'Denda Masuk (Rp)',
                data: dataDendaHarian,
                borderColor: '#dc2626',
                backgroundColor: 'rgba(220, 38, 38, 0.12)',
                borderWidth: 2,
                pointRadius: 3,
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
                    suggestedMax: 10000,
                    ticks: {
                        precision: 0,
                        maxTicksLimit: 5,
                        font: { size: 10 },
                        callback: function(value) {
                            if (value === 0) return 'Rp 0';
                            return 'Rp ' + (value >= 1000 ? (value / 1000) + 'rb' : value);
                        }
                    }
                },
                x: {
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
    </script>

</body>
</html>