-- Skrip Inisialisasi Database BUMDES SUMBER REZEKI
-- Buat database jika belum ada (opsional, sesuaikan saat di cPanel)
CREATE DATABASE IF NOT EXISTS `bumdes_sumber_rezeki`;
USE `bumdes_sumber_rezeki`;

-- 1. TABEL PENGATURAN (Konfigurasi Sistem)
CREATE TABLE IF NOT EXISTS `pengaturan` (
    `kunci` VARCHAR(50) NOT NULL PRIMARY KEY,
    `nilai` VARCHAR(255) NOT NULL,
    `keterangan` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Isi default tarif dan jam operasional
INSERT INTO `pengaturan` (`kunci`, `nilai`, `keterangan`) VALUES
('tarif_motor', '2000', 'Tarif parkir untuk sepeda motor (Rupiah)'),
('tarif_mobil', '5000', 'Tarif parkir untuk mobil/kendaraan roda empat (Rupiah)'),
('operasional_mulai', '07:00', 'Jam mulai operasional aktif (HH:MM)'),
('operasional_selesai', '17:00', 'Jam selesai operasional aktif (HH:MM)')
ON DUPLICATE KEY UPDATE `nilai` = VALUES(`nilai`);

-- 2. TABEL LOG TRANSAKSI PARKIR
CREATE TABLE IF NOT EXISTS `log_transaksi` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `jenis_kendaraan` VARCHAR(20) NOT NULL, -- 'motor' atau 'mobil'
    `tarif` INT NOT NULL, -- Tarif yang berlaku saat transaksi dicatat (untuk histori yang konsisten)
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indeks untuk mempercepat query rekap harian & mingguan
CREATE INDEX `idx_created_at` ON `log_transaksi` (`created_at`);
CREATE INDEX `idx_jenis_kendaraan` ON `log_transaksi` (`jenis_kendaraan`);

-- 3. TABEL PENDAPATAN TAMBAHAN (Toilet, Sewa Lapak, dsb)
CREATE TABLE IF NOT EXISTS `pendapatan_tambahan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_item` VARCHAR(255) NOT NULL, -- Nama keperluan / item pemasukan
    `nominal` INT NOT NULL, -- Jumlah pendapatan dalam Rupiah
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Indeks untuk mempermudah pencarian berdasarkan waktu
CREATE INDEX `idx_tambahan_created_at` ON `pendapatan_tambahan` (`created_at`);

-- 4. TABEL HARI LIBUR (Tracking tanggal libur per-hari untuk kalender)
CREATE TABLE IF NOT EXISTS `hari_libur` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tanggal` DATE NOT NULL UNIQUE,
    `keterangan` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tambahkan pengaturan status operasional default
INSERT INTO `pengaturan` (`kunci`, `nilai`, `keterangan`) VALUES
('status_libur', '0', 'Status libur hari ini (0=aktif, 1=libur)'),
('status_tutup_sementara', '0', 'Status tutup sementara (0=buka, 1=tutup)')
ON DUPLICATE KEY UPDATE `nilai` = VALUES(`nilai`);
