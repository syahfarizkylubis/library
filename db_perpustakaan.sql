CREATE DATABASE IF NOT EXISTS db_perpustakaan;
USE db_perpustakaan;

-- Tabel Users (Staff)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL
);

-- Default Akun Staff (Username: staff, Password: password123)
INSERT INTO users (username, password, nama_lengkap) 
VALUES ('staff', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1D23iE4x33p4m1O.y39N/3S2W4m/J2K', 'Staff Perpustakaan');

-- Tabel Buku
CREATE TABLE buku (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(4) NOT NULL UNIQUE,
    nama_buku VARCHAR(100) NOT NULL,
    penulis VARCHAR(50) NOT NULL,
    tahun_terbit INT(4) NOT NULL,
    stok INT NOT NULL DEFAULT 0
);

-- Tabel Peminjaman
CREATE TABLE peminjaman (
    id INT AUTO_INCREMENT PRIMARY KEY,
    npm VARCHAR(12) NOT NULL,
    nama_siswa VARCHAR(50) NOT NULL,
    buku_id INT NOT NULL,
    tgl_pinjam DATE NOT NULL,
    tgl_kembali DATE DEFAULT NULL,
    status ENUM('Dipinjam', 'Dikembalikan') DEFAULT 'Dipinjam',
    FOREIGN KEY (buku_id) REFERENCES buku(id) ON DELETE CASCADE
);

