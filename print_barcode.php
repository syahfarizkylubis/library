<?php
session_start();
if (!isset($_SESSION['login'])) {
    exit("Akses ditolak.");
}

$code = isset($_GET['code']) ? htmlspecialchars($_GET['code']) : '0000';
$nama = isset($_GET['nama']) ? htmlspecialchars($_GET['nama']) : 'Buku Perpustakaan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Barcode - <?= $code; ?></title>
    <!-- Library JsBarcode untuk merender garis barcode asli -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f1f5f9;
        }
        .config-box {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            width: 340px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .config-box label {
            display: block;
            font-size: 0.85rem;
            font-weight: bold;
            color: #334155;
            margin-bottom: 5px;
        }
        .config-box input {
            width: 100%;
            padding: 8px 10px;
            font-size: 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            outline: none;
        }
        .ticket-container {
            background: #fff;
            border: 2px solid #000;
            border-radius: 8px;
            padding: 20px 30px;
            text-align: center;
            width: 340px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .header-title {
            font-size: 0.9rem;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .book-title {
            font-size: 0.8rem;
            color: #475569;
            margin-bottom: 12px;
            word-wrap: break-word;
        }
        svg#barcode {
            max-width: 100%;
            height: auto;
        }
        .btn-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #fff;
        }
        .btn-print { background-color: #0284c7; }
        .btn-close { background-color: #64748b; }

        /* Sembunyikan form konfigurasi & tombol saat cetak */
        @media print {
            body {
                background-color: #fff;
            }
            .config-box, .btn-group {
                display: none !important;
            }
            .ticket-container {
                border: 1px solid #000;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

    <!-- Panel Pengubahan Label Institusi (Otomatis Tersimpan) -->
    <div class="config-box">
        <label for="inputInstitusi">Label Nama Institusi / Perpustakaan:</label>
        <input type="text" id="inputInstitusi" placeholder="Contoh: SMA Negeri 1 Jakarta / Universitas X">
    </div>

    <!-- Tampilan Barcode yang Akan Dicetak -->
    <div class="ticket-container">
        <div class="header-title" id="displayInstitusi">DIGITAL LIBRARY WEB MANAGER</div>
        <div class="book-title"><?= $nama; ?></div>
        
        <!-- Elemen SVG Barcode -->
        <svg id="barcode"></svg>
    </div>

    <div class="btn-group">
        <button onclick="window.print()" class="btn btn-print">Cetak / Print</button>
        <button onclick="window.close()" class="btn btn-close">Tutup Halaman</button>
    </div>

    <script>
        const inputInstitusi = document.getElementById('inputInstitusi');
        const displayInstitusi = document.getElementById('displayInstitusi');
        const DEFAULT_LABEL = "DIGITAL LIBRARY WEB MANAGER";

        // 1. Muat label default tersimpan dari LocalStorage jika ada
        const savedLabel = localStorage.getItem('dlwm_institution_label');
        if (savedLabel) {
            inputInstitusi.value = savedLabel;
            displayInstitusi.textContent = savedLabel;
        } else {
            inputInstitusi.value = DEFAULT_LABEL;
            displayInstitusi.textContent = DEFAULT_LABEL;
        }

        // 2. Event listener: update tampilan & simpan otomatis ke LocalStorage saat diketik
        inputInstitusi.addEventListener('input', function() {
            const val = this.value.trim() !== "" ? this.value : DEFAULT_LABEL;
            displayInstitusi.textContent = val;
            localStorage.setItem('dlwm_institution_label', val);
        });

        // 3. Render Barcode Code128
        JsBarcode("#barcode", "<?= $code; ?>", {
            format: "CODE128",
            lineColor: "#000",
            width: 2.5,
            height: 65,
            displayValue: true,
            fontSize: 16,
            fontOptions: "bold",
            margin: 5
        });
    </script>

</body>
</html>