<?php
session_start();
require 'koneksi_db.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$staff_username = $_SESSION['user'] ?? 'staff';

$daftar_genre = [
    'Pendidikan Kejuruan', 
    'Fiksi dan Hiburan', 
    'Biografi', 
    'Pendidikan Agama', 
    'Kewirausahaan', 
    'Pendidikan Umum', 
    'Referensi dan Jurnal'
];

// Tentukan tab aktif (jika sedang mode edit, otomatis ke tab 'tambah')
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'tambah';
if (isset($_GET['edit_buku'])) {
    $tab = 'tambah';
}

// Tambah Buku
if (isset($_POST['tambah_buku'])) {
    $nama_buku = mysqli_real_escape_string($koneksi, $_POST['nama_buku']);
    $penulis = mysqli_real_escape_string($koneksi, $_POST['penulis']);
    $penerbit = mysqli_real_escape_string($koneksi, $_POST['penerbit']);
    $tahun_terbit = (int)$_POST['tahun_terbit'];
    $jumlah_halaman = (int)$_POST['jumlah_halaman'];
    $genre = mysqli_real_escape_string($koneksi, $_POST['genre']);
    $stok = (int)$_POST['stok'];

    // Handle Upload Cover Buku
    $nama_file_cover = '';
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['cover']['tmp_name'];
        $file_name = $_FILES['cover']['name'];
        $ekstensi = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $ekstensi_boleh = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ekstensi, $ekstensi_boleh)) {
            $nama_file_cover = 'cover_' . time() . '.' . $ekstensi;
            move_uploaded_file($file_tmp, 'uploads/' . $nama_file_cover);
        }
    }

    $q_max = mysqli_query($koneksi, "SELECT MAX(barcode) as max_bc FROM buku");
    $d_max = mysqli_fetch_assoc($q_max);

    if ($d_max && $d_max['max_bc'] !== null) {
        $next_code = (int)$d_max['max_bc'] + 1;
        $barcode = str_pad($next_code, 4, '0', STR_PAD_LEFT);
    } else {
        $barcode = '1000';
    }

    // Eksekusi Insert Buku
    mysqli_query($koneksi, "INSERT INTO buku (barcode, cover, nama_buku, penulis, penerbit, tahun_terbit, jumlah_halaman, genre, stok) VALUES ('$barcode', '$nama_file_cover', '$nama_buku', '$penulis', '$penerbit', $tahun_terbit, $jumlah_halaman, '$genre', $stok)");

    // Catat ke Audit Logs
    $log_tambah_raw = "Menambahkan buku baru '$nama_buku' (Barcode: $barcode, Genre: $genre, Stok: $stok)";
    $log_tambah = mysqli_real_escape_string($koneksi, $log_tambah_raw);
    mysqli_query($koneksi, "INSERT INTO audit_logs (staff_username, aksi, detail) VALUES ('$staff_username', 'Tambah Buku Baru', '$log_tambah')");

    header("Location: kelola_buku.php?tab=daftar");
    exit;
}

// Edit Buku
if (isset($_POST['edit_buku'])) {
    $id = (int)$_POST['id_buku'];
    $nama_buku = mysqli_real_escape_string($koneksi, $_POST['nama_buku']);
    $penulis = mysqli_real_escape_string($koneksi, $_POST['penulis']);
    $penerbit = mysqli_real_escape_string($koneksi, $_POST['penerbit']);
    $tahun_terbit = (int)$_POST['tahun_terbit'];
    $jumlah_halaman = (int)$_POST['jumlah_halaman'];
    $genre = mysqli_real_escape_string($koneksi, $_POST['genre']);
    $stok = (int)$_POST['stok'];

    // Cek apakah ada upload cover baru
    $update_cover_sql = "";
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['cover']['tmp_name'];
        $file_name = $_FILES['cover']['name'];
        $ekstensi = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $ekstensi_boleh = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ekstensi, $ekstensi_boleh)) {
            $nama_file_cover = 'cover_' . time() . '.' . $ekstensi;
            move_uploaded_file($file_tmp, 'uploads/' . $nama_file_cover);

            // Hapus cover lama jika ada
            $q_lama = mysqli_query($koneksi, "SELECT cover FROM buku WHERE id = $id");
            $d_lama = mysqli_fetch_assoc($q_lama);
            if (!empty($d_lama['cover']) && file_exists('uploads/' . $d_lama['cover'])) {
                unlink('uploads/' . $d_lama['cover']);
            }

            $update_cover_sql = ", cover='$nama_file_cover'";
        }
    }

    // Eksekusi Update Buku
    mysqli_query($koneksi, "UPDATE buku SET nama_buku='$nama_buku', penulis='$penulis', penerbit='$penerbit', tahun_terbit=$tahun_terbit, jumlah_halaman=$jumlah_halaman, genre='$genre', stok=$stok $update_cover_sql WHERE id=$id");

    // Catat ke Audit Logs
    $log_edit_raw = "Memperbarui data buku '$nama_buku' (ID: $id, Genre: $genre, Stok: $stok)";
    $log_edit = mysqli_real_escape_string($koneksi, $log_edit_raw);
    mysqli_query($koneksi, "INSERT INTO audit_logs (staff_username, aksi, detail) VALUES ('$staff_username', 'Edit Data Buku', '$log_edit')");

    header("Location: kelola_buku.php?tab=daftar");
    exit;
}

// Hapus Buku
if (isset($_GET['hapus_buku'])) {
    $id = (int)$_GET['hapus_buku'];
    $q_lama = mysqli_query($koneksi, "SELECT nama_buku, cover FROM buku WHERE id = $id");
    $d_lama = mysqli_fetch_assoc($q_lama);
    $nama_buku_hapus = $d_lama['nama_buku'] ?? 'Buku ID ' . $id;

    if (!empty($d_lama['cover']) && file_exists('uploads/' . $d_lama['cover'])) {
        unlink('uploads/' . $d_lama['cover']);
    }

    // Eksekusi Delete Buku
    mysqli_query($koneksi, "DELETE FROM buku WHERE id = $id");

    // Catat ke Audit Logs
    $log_hapus_raw = "Menghapus buku '$nama_buku_hapus' (ID: $id) dari sistem katalog";
    $log_hapus = mysqli_real_escape_string($koneksi, $log_hapus_raw);
    mysqli_query($koneksi, "INSERT INTO audit_logs (staff_username, aksi, detail) VALUES ('$staff_username', 'Hapus Buku', '$log_hapus')");

    header("Location: kelola_buku.php?tab=daftar");
    exit;
}

// Ambil parameter pencarian dan filter genre untuk tab daftar
$cari = isset($_GET['cari']) ? mysqli_real_escape_string($koneksi, trim($_GET['cari'])) : '';
$genre_filter = isset($_GET['genre_filter']) ? mysqli_real_escape_string($koneksi, trim($_GET['genre_filter'])) : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Buku - DLWM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand">SMK RAYA MERDEKA JAKARTA</div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php">Dashboard Utama</a></li>
            <li class="active"><a href="kelola_buku.php">Kelola Stok & Buku</a></li>
            <li><a href="kelola_siswa.php">Kelola Akun Siswa</a></li>
            <li><a href="kelola_akun.php">Kelola Akun Staff</a></li>
        </ul>
        <div class="sidebar-footer">
            <span>Staff: <strong><?= htmlspecialchars($_SESSION['user']); ?></strong></span>
        </div>
    </div>

    <div class="main-content">
        <div class="topbar">
            <h2 style="font-size: 1.2rem; color: #1e293b;">Manajemen Katalog Buku & Stok</h2>
            <a href="logout.php" style="color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.9rem;">Logout</a>
        </div>

        <div class="container">
            
            <!-- TAB NAVIGATION BUTTONS -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <a href="kelola_buku.php?tab=tambah" class="btn" style="background: <?= ($tab == 'tambah') ? '#0284c7' : '#64748b'; ?>; text-decoration: none;">Tambah Buku Baru</a>
                <a href="kelola_buku.php?tab=daftar" class="btn" style="background: <?= ($tab == 'daftar') ? '#0284c7' : '#64748b'; ?>; text-decoration: none;">Daftar Katalog Buku</a>
            </div>

            <?php if ($tab == 'tambah'): ?>
                <?php
                $edit_mode = false;
                $val_id = $val_nama = $val_penulis = $val_penerbit = $val_tahun = $val_halaman = $val_genre = $val_stok = $val_cover = "";
                if (isset($_GET['edit_buku'])) {
                    $id_e = (int)$_GET['edit_buku'];
                    $q_e = mysqli_query($koneksi, "SELECT * FROM buku WHERE id = $id_e");
                    if (mysqli_num_rows($q_e) > 0) {
                        $edit_mode = true;
                        $e_data = mysqli_fetch_assoc($q_e);
                        $val_id = $e_data['id'];
                        $val_nama = $e_data['nama_buku'];
                        $val_penulis = $e_data['penulis'];
                        $val_penerbit = $e_data['penerbit'];
                        $val_tahun = $e_data['tahun_terbit'];
                        $val_halaman = $e_data['jumlah_halaman'];
                        $val_genre = $e_data['genre'];
                        $val_stok = $e_data['stok'];
                        $val_cover = $e_data['cover'];
                    }
                }
                ?>

                <div class="card">
                    <h3><?= $edit_mode ? 'Edit Data / Stok & Cover Buku' : 'Tambah Buku Baru & Upload Cover'; ?></h3>
                    <form action="" method="POST" enctype="multipart/form-data" style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id_buku" value="<?= $val_id; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group" style="flex: 2; min-width: 250px;">
                            <label>Nama Buku</label>
                            <input type="text" name="nama_buku" value="<?= htmlspecialchars($val_nama); ?>" required>
                        </div>

                        <div class="form-group" style="flex: 2; min-width: 200px;">
                            <label>Penulis</label>
                            <input type="text" name="penulis" value="<?= htmlspecialchars($val_penulis); ?>" required>
                        </div>

                        <div class="form-group" style="flex: 2; min-width: 200px;">
                            <label>Penerbit</label>
                            <input type="text" name="penerbit" value="<?= htmlspecialchars($val_penerbit); ?>" required>
                        </div>

                        <div class="form-group" style="flex: 1; min-width: 110px;">
                            <label>Tahun Terbit</label>
                            <input type="number" name="tahun_terbit" value="<?= $val_tahun; ?>" required>
                        </div>

                        <div class="form-group" style="flex: 1; min-width: 120px;">
                            <label>Jumlah Halaman</label>
                            <input type="number" name="jumlah_halaman" value="<?= $val_halaman; ?>" required min="1">
                        </div>

                        <div class="form-group" style="flex: 2; min-width: 200px;">
                            <label>Genre Buku</label>
                            <select name="genre" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;">
                                <option value="">-- Pilih Genre --</option>
                                <?php foreach ($daftar_genre as $g): ?>
                                    <option value="<?= $g; ?>" <?= ($val_genre == $g) ? 'selected' : ''; ?>><?= $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group" style="flex: 1; min-width: 90px;">
                            <label>Jumlah Stok</label>
                            <input type="number" name="stok" value="<?= $val_stok; ?>" required min="0">
                        </div>

                        <div class="form-group" style="flex: 2; min-width: 220px;">
                            <label>Cover Buku (JPG/PNG/WEBP)</label>
                            <input type="file" name="cover" accept=".jpg, .jpeg, .png, .webp" style="padding: 6px;">
                            <?php if (!empty($val_cover)): ?>
                                <small style="color: #0284c7; display: block; margin-top: 4px;">Cover saat ini: <?= $val_cover; ?></small>
                            <?php endif; ?>
                        </div>

                        <div style="flex: 100%;">
                            <?php if ($edit_mode): ?>
                                <button type="submit" name="edit_buku" class="btn btn-warning">Update Buku</button>
                                <a href="kelola_buku.php?tab=daftar" class="btn" style="background:#64748b; text-decoration: none; display: inline-block;">Batal</a>
                            <?php else: ?>
                                <button type="submit" name="tambah_buku" class="btn">Simpan Buku & Generate Barcode</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- TABEL KATALOG BUKU -->
                <div class="card">
                    <h3>Daftar Katalog Buku & Cetak Barcode</h3>
                    
                    <!-- Form Pencarian & Filter Genre -->
                    <form action="kelola_buku.php" method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end;">
                        <input type="hidden" name="tab" value="daftar">
                        <div style="flex: 2; min-width: 220px;">
                            <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px;">Cari Buku (Nama, Barcode, Penulis, Penerbit)</label>
                            <input type="text" name="cari" value="<?= htmlspecialchars($cari); ?>" placeholder="Masukkan kata kunci..." style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; box-sizing: border-box;">
                        </div>
                        <div style="flex: 2; min-width: 220px;">
                            <label style="font-size: 0.85rem; font-weight: 600; display: block; margin-bottom: 4px;">Filter Berdasarkan Genre</label>
                            <select name="genre_filter" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; box-sizing: border-box;">
                                <option value="">-- Semua Genre --</option>
                                <?php foreach ($daftar_genre as $g): ?>
                                    <option value="<?= $g; ?>" <?= ($genre_filter == $g) ? 'selected' : ''; ?>><?= $g; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn" style="padding: 9px 16px;">Cari / Filter</button>
                            <?php if (!empty($cari) || !empty($genre_filter)): ?>
                                <a href="kelola_buku.php?tab=daftar" class="btn" style="background: #64748b; text-decoration: none; padding: 9px 16px; display: inline-block;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="spreadsheet">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">No</th>
                                    <th style="width: 80px;">Cover</th>
                                    <th style="width: 110px;">No. Inventory</th>
                                    <th>Nama Buku</th>
                                    <th>Penulis</th>
                                    <th>Penerbit</th>
                                    <th style="width: 70px;">Tahun</th>
                                    <th style="width: 80px;">Halaman</th>
                                    <th style="width: 190px;">Genre</th>
                                    <th style="width: 60px;">Stok</th>
                                    <th style="width: 120px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                
                                // Query dinamis dengan pencarian dan filter genre
                                $sql_b = "SELECT * FROM buku WHERE 1=1";
                                if (!empty($cari)) {
                                    $sql_b .= " AND (nama_buku LIKE '%$cari%' OR barcode LIKE '%$cari%' OR penulis LIKE '%$cari%' OR penerbit LIKE '%$cari%')";
                                }
                                if (!empty($genre_filter)) {
                                    $sql_b .= " AND genre = '$genre_filter'";
                                }
                                $sql_b .= " ORDER BY id DESC";

                                $res_b = mysqli_query($koneksi, $sql_b);
                                if (mysqli_num_rows($res_b) == 0): ?>
                                    <tr><td colspan="11" style="text-align: center; color: #64748b;">Tidak ada data buku yang cocok dengan kriteria pencarian/filter.</td></tr>
                                <?php else: 
                                    while ($b = mysqli_fetch_assoc($res_b)):
                                ?>
                                    <tr>
                                        <td><?= $no++; ?></td>
                                        <td>
                                            <?php if (!empty($b['cover']) && file_exists('uploads/' . $b['cover'])): ?>
                                                <img src="uploads/<?= $b['cover']; ?>" alt="Cover" style="width: 50px; height: 70px; object-fit: cover; border-radius: 4px; border: 1px solid #cbd5e1;">
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; color: #94a3b8;">No Cover</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="font-family: monospace;"><?= $b['barcode']; ?></strong><br>
                                            <a href="print_barcode.php?code=<?= $b['barcode']; ?>&nama=<?= urlencode($b['nama_buku']); ?>" target="_blank" class="btn" style="background:#0284c7; padding:2px 6px; font-size:0.75rem; margin-top:4px; display:inline-block;">Cetak</a>
                                        </td>
                                        <td><?= htmlspecialchars($b['nama_buku']); ?></td>
                                        <td><?= htmlspecialchars($b['penulis']); ?></td>
                                        <td><?= htmlspecialchars($b['penerbit']); ?></td>
                                        <td><?= $b['tahun_terbit']; ?></td>
                                        <td><?= $b['jumlah_halaman']; ?> hlm</td>
                                        <td>
                                            <span class="badge" style="display: inline-block; background:#f1f5f9; color:#0369a1; border:1px solid #cbd5e1; padding:4px 8px; border-radius:4px; font-size:0.78rem; text-align: left; line-height: 1.3; white-space: normal; word-break: break-word;"><?= htmlspecialchars($b['genre']); ?></span>
                                        </td>
                                        <td><?= $b['stok']; ?></td>
                                        <td>
                                            <a href="kelola_buku.php?edit_buku=<?= $b['id']; ?>&tab=tambah" class="btn btn-warning" style="padding:4px 8px; font-size:0.75rem;">Edit</a>
                                            <a href="kelola_buku.php?hapus_buku=<?= $b['id']; ?>" class="btn" style="background:#ef4444; padding:4px 8px; font-size:0.75rem;" onclick="return confirm('Hapus buku ini?')">Hapus</a>
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