<?php
/**
 * Dashboard Utama BUMDes Penang
 * Sistem Rekap Keuangan Multi-Page (SPA)
 */

session_start();

// Proteksi halaman utama: jika belum login, alihkan ke login.php
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Keuangan BUMDes Penang</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Link Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="container">
        
        <!-- HEADER persistent -->
        <header class="app-header">
            <div class="logo-section">
                <h1>BUMDes Penang</h1>
                <p>Sistem Rekap Keuangan Mandiri</p>
            </div>
            
            <div class="header-right-section" style="display: flex; align-items: center; gap: 1rem;">
                <div class="status-operasional">
                    <div id="status-indicator" class="status-indicator tutup"></div>
                    <span id="status-text" class="status-text">Memuat Status...</span>
                    <span class="time-display" id="realtime-clock">00:00:00</span>
                </div>
                
                <a href="logout.php" class="btn btn-secondary btn-logout" style="max-width: fit-content; padding: 0.5rem 1rem; font-size: 0.85rem; margin: 0; box-shadow: 3px 3px 0px #000000; text-decoration: none; border: var(--border-thin);">
                    🚪 Logout
                </a>
            </div>
        </header>

        <!-- ================= PAGE 1: MENU UTAMA ================= -->
        <div id="page-menu" class="app-page active-page">
            <div class="welcome-banner">
                <h2>Selamat Datang di Portal Administrasi</h2>
                <p>Silakan pilih modul rekapitulasi keuangan yang ingin Anda jalankan di bawah ini:</p>
            </div>

            <!-- Kontrol Libur Hari Ini -->
            <div class="glass-card" style="margin-bottom: 2rem; border-top: 3px solid var(--accent-orange);">
                <div class="card-title" style="margin-bottom: 1rem; border-bottom: none; padding-bottom: 0;">
                    <span>Kontrol Libur Satu Hari Utuh</span>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div style="font-weight: 700; font-size: 0.95rem;">
                        Status Libur Hari Ini: <span id="label-status-libur-menu" class="badge" style="margin-left: 0.5rem;">Memuat...</span>
                    </div>
                    <button id="btn-toggle-libur-menu" class="btn btn-warning" style="max-width: fit-content; margin: 0; padding: 0.6rem 1.2rem; font-size: 0.9rem;">
                        🏖️ Atur Hari Libur
                    </button>
                </div>
            </div>
            
            <div class="menu-grid">
                <!-- Card 1: Rekap Harian -->
                <div class="menu-card" id="card-goto-harian">
                    <div class="menu-card-icon">📝</div>
                    <h3>Rekap Harian</h3>
                    <p>Catat transaksi kendaraan masuk (motor, mobil, dll), kelola jam operasional, set hari libur, dan unduh laporan harian.</p>
                    <span class="menu-card-btn">Buka Modul &rarr;</span>
                </div>
                
                <!-- Card 2: Rincian Rekap (Kalender) -->
                <div class="menu-card" id="card-goto-mingguan">
                    <div class="menu-card-icon">📅</div>
                    <h3>Rincian Rekap</h3>
                    <p>Lihat rekapitulasi harian menggunakan kalender interaktif 3 warna, serta unduh laporan pendapatan berdasarkan jangkauan tanggal kustom.</p>
                    <span class="menu-card-btn">Buka Modul &rarr;</span>
                </div>
                
                <!-- Card 3: Menentukan Tarif -->
                <div class="menu-card" id="card-goto-tarif">
                    <div class="menu-card-icon">⚙️</div>
                    <h3>Menentukan Tarif & Jam</h3>
                    <p>Atur rentang jam kerja aktif, buat kategori kendaraan kustom baru, edit tarif aktif, atau hapus kategori kendaraan.</p>
                    <span class="menu-card-btn">Buka Modul &rarr;</span>
                </div>
            </div>
        </div>

        <!-- ================= PAGE 2a: REKAP HARIAN ================= -->
        <div id="page-harian" class="app-page">
            <div class="page-actions-bar">
                <button class="btn btn-back btn-back-menu">&larr; Kembali ke Menu Utama</button>
                <!-- Tombol Tutup Sementara -->
                <button id="btn-toggle-tutup-sementara" class="btn btn-warning">⚠️ Tutup Sementara / Lebih Awal</button>
            </div>
            
            <div class="dashboard-grid">
                
                <!-- Kolom Kiri: Input Parkir & Statistik Hari Ini -->
                <div class="main-column">
                    <!-- 1. Tombol Pencatatan Dinamis -->
                    <div class="glass-card">
                        <div class="card-title">
                            <span>Pencatatan Parkir Lapangan</span>
                            <small id="info-jam-operasional" style="font-size: 0.8rem; color: var(--text-secondary);"></small>
                        </div>
                        
                        <!-- Grid Button Parkir Dinamis -->
                        <div id="quick-input-container" class="quick-input-section">
                            <!-- Diisi secara dinamis oleh JS -->
                            <div class="loading-text">Memuat tombol pencatatan...</div>
                        </div>
                        
                        <p style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 1rem;">
                            * Tombol hanya dapat diklik pada jam operasional kerja aktif.
                        </p>
                    </div>

                    <!-- 2. Ringkasan Pendapatan Hari Ini -->
                    <div class="glass-card">
                        <div class="card-title">
                            <span>Statistik Hari Ini</span>
                            <span class="badge badge-blue" id="hari-ini-badge"><?= date('d-m-Y'); ?></span>
                        </div>
                        
                        <div class="grand-total-card">
                            <div class="grand-label">Grand Total Pendapatan Hari Ini</div>
                            <div class="grand-value" id="grand-total">Rp 0</div>
                        </div>
                        
                        <!-- Statistik Kendaraan Dinamis -->
                        <div id="stats-container" class="stats-container">
                            <!-- Diisi secara dinamis oleh JS -->
                            <div class="loading-text">Memuat statistik...</div>
                        </div>
                    </div>
                </div>
                
                <!-- Kolom Kanan: Pendapatan Tambahan & Ekspor Harian -->
                <div class="sidebar-column">
                    <!-- Form Input Pendapatan Tambahan -->
                    <div class="glass-card">
                        <div class="card-title">Pendapatan Tambahan (Manual)</div>
                        
                        <form id="form-tambahan" autocomplete="off">
                            <div class="form-group">
                                <label for="nama-tambahan">Keperluan / Keterangan Khusus</label>
                                <input type="text" id="nama-tambahan" class="input-control" placeholder="Contoh: Toilet, Sewa Lapak, Rombongan Bus..." required>
                            </div>
                            <div class="form-group">
                                <label for="nominal-tambahan">Nominal Pendapatan (Rp)</label>
                                <input type="number" id="nominal-tambahan" class="input-control" min="1" placeholder="Contoh: 25000" required>
                            </div>
                            <button type="submit" class="btn btn-primary">💾 Simpan Pendapatan Tambahan</button>
                        </form>
                        
                        <div style="margin-top: 1.5rem;">
                            <h4 style="font-size: 0.9rem; font-weight:600; margin-bottom:0.75rem; color: var(--text-secondary);">
                                Riwayat Tambahan Hari Ini
                            </h4>
                            <div class="history-list" id="list-tambahan">
                                <!-- Diisi via Javascript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Menu Ekspor Harian -->
                    <div class="glass-card" style="border-top: 3px solid var(--accent-green);">
                        <div class="card-title">Ekspor Rekap Harian</div>
                        
                        <div class="form-group">
                            <label for="tanggal-ekspor">Pilih Tanggal Laporan</label>
                            <input type="date" id="tanggal-ekspor" class="input-control">
                        </div>
                        
                        <button id="btn-ekspor-harian" class="btn btn-success">
                            📥 Unduh Rekap Harian (CSV)
                        </button>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- ================= PAGE 2b: UNDUH REKAP MINGGUAN / KALENDER INTERAKTIF ================= -->
        <div id="page-mingguan" class="app-page">
            <div class="page-actions-bar">
                <button class="btn btn-back btn-back-menu" style="max-width: fit-content;">&larr; Kembali ke Menu Utama</button>
            </div>
            
            <div class="dashboard-grid">
                
                <!-- Kolom Kiri: Widget Kalender -->
                <div class="main-column">
                    <div class="glass-card" style="border-top: 3px solid var(--accent-teal);">
                        <div class="card-title">
                            <span>Widget Laporan Kalender</span>
                        </div>
                        
                        <!-- Calendar Wrapper -->
                        <div class="calendar-wrapper" style="margin-bottom: 1.5rem;">
                            <div class="calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                                <button id="btn-prev-month" class="btn" style="max-width: fit-content; padding: 0.5rem 1rem; margin: 0; box-shadow: 2px 2px 0px #000000;">&larr; Prev</button>
                                <h3 id="calendar-month-year" style="font-family: var(--font-heading); font-weight: 900; text-transform: uppercase; font-size: 1.2rem; letter-spacing: 0.5px;">Juli 2026</h3>
                                <button id="btn-next-month" class="btn" style="max-width: fit-content; padding: 0.5rem 1rem; margin: 0; box-shadow: 2px 2px 0px #000000;">Next &rarr;</button>
                            </div>
                            
                            <div class="calendar-grid-header" style="display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; font-weight: 800; font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.75rem; background: var(--accent-teal); border: var(--border-thin); padding: 0.5rem; border-radius: 4px; box-shadow: 2px 2px 0px #000000;">
                                <div>Sen</div>
                                <div>Sel</div>
                                <div>Rab</div>
                                <div>Kam</div>
                                <div>Jum</div>
                                <div>Sab</div>
                                <div>Min</div>
                            </div>
                            
                            <div id="calendar-days-grid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem;">
                                <!-- Rendered dynamically by JS -->
                                <div class="text-center text-muted" style="grid-column: 1 / span 7; padding: 2rem;">Memuat kalender...</div>
                            </div>
                        </div>

                        <!-- Legend -->
                        <div class="legend-container">
                            <div class="legend-item">
                                <div class="legend-color color-libur"></div>
                                <span>Libur / Tutup</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color color-terekap"></div>
                                <span>Terekap / Ada Transaksi</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color color-belum"></div>
                                <span>Belum Terekap</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Rincian & Unduh Laporan Jangkauan -->
                <div class="sidebar-column">
                    <div class="glass-card" style="border-top: 3px solid var(--accent-blue);">
                        <div class="card-title">
                            <span>Informasi Hari Terpilih</span>
                        </div>
                        
                        <div id="calendar-selection-placeholder" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted); font-weight: 700;">
                            📅 Silakan pilih tanggal pada kalender untuk melihat rincian rekapitulasi harian.
                        </div>

                        <div id="calendar-selection-details" style="display: none;">
                            <h4 id="selected-date-title" style="font-family: var(--font-heading); font-weight: 900; font-size: 1.1rem; text-transform: uppercase; margin-bottom: 1rem; border-bottom: var(--border-thin); padding-bottom: 0.5rem; color: var(--accent-blue);">
                                Rekap Tanggal: -
                            </h4>

                            <!-- Status Operasional Tanggal Terpilih -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; background: #FFFFFF; border: var(--border-thin); padding: 0.75rem; border-radius: 6px; box-shadow: 2px 2px 0px #000000;">
                                <span style="font-weight: 800; font-size: 0.85rem; text-transform: uppercase;">Status Operasional:</span>
                                <button id="btn-toggle-libur-selected" class="badge" style="cursor: pointer; font-size: 0.75rem; border: var(--border-thin); box-shadow: 1px 1px 0px #000000; font-weight: 800; text-transform: uppercase;">Memuat...</button>
                            </div>

                            <!-- Grand Total Tanggal Terpilih -->
                            <div class="grand-total-card" style="padding: 1rem; margin-bottom: 1.25rem; box-shadow: var(--shadow-hard-small);">
                                <div class="grand-label" style="font-size: 0.8rem; letter-spacing: 1px;">Total Pendapatan Harian</div>
                                <div class="grand-value" id="selected-date-grand-total" style="font-size: 1.8rem;">Rp 0</div>
                            </div>

                            <!-- Statistik Kendaraan Dinamis Tanggal Terpilih -->
                            <div id="selected-date-stats-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.5rem;">
                                <!-- Dynamic vehicle stats cards -->
                            </div>

                            <div style="border-top: var(--border-thin); padding-top: 1.25rem; margin-top: 1.25rem;">
                                <button id="btn-export-daily-selected" class="btn btn-success" style="width: 100%; margin-bottom: 1.5rem; box-shadow: var(--shadow-hard-small); padding: 0.75rem 1rem; font-size: 0.9rem;">
                                    📥 Unduh Rekap Harian Tanggal Ini
                                </button>
                            </div>

                            <!-- Form Jangkauan Kustom -->
                            <div style="border-top: var(--border-thin); padding-top: 1.25rem;">
                                <h4 style="font-family: var(--font-heading); font-weight: 900; font-size: 0.95rem; text-transform: uppercase; margin-bottom: 0.75rem;">
                                    Ekspor Jangkauan Tanggal
                                </h4>
                                
                                <div class="form-group" style="margin-bottom: 0.75rem;">
                                    <label for="range-start-date" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Dari Tanggal</label>
                                    <input type="date" id="range-start-date" class="input-control" style="padding: 0.5rem 0.75rem; font-size: 0.85rem; box-shadow: 2px 2px 0px #000000;">
                                </div>
                                <div class="form-group" style="margin-bottom: 1rem;">
                                    <label for="range-end-date" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Sampai Tanggal</label>
                                    <input type="date" id="range-end-date" class="input-control" style="padding: 0.5rem 0.75rem; font-size: 0.85rem; box-shadow: 2px 2px 0px #000000;">
                                </div>
                                
                                <button id="btn-export-range" class="btn btn-primary" style="width: 100%; box-shadow: var(--shadow-hard-small); padding: 0.75rem 1rem; font-size: 0.9rem;">
                                    📥 Unduh Laporan Jangkauan (CSV)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ================= PAGE 2c: MENENTUKAN TARIF & JAM ================= -->
        <div id="page-tarif" class="app-page">
            <div class="page-actions-bar">
                <button class="btn btn-back btn-back-menu">&larr; Kembali ke Menu Utama</button>
            </div>
            
            <div class="dashboard-grid">
                
                <!-- Kolom Kiri: Atur Jam Operasional -->
                <div class="main-column">
                    <div class="glass-card">
                        <div class="card-title">Pengaturan Jam Kerja Operasional</div>
                        
                        <form id="form-setting-operasional">
                            <div class="form-group">
                                <label for="input-operasional-mulai">Jam Mulai Kerja (Format HH:MM)</label>
                                <input type="text" id="input-operasional-mulai" class="input-control" placeholder="07:00" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="input-operasional-selesai">Jam Selesai Kerja (Format HH:MM)</label>
                                <input type="text" id="input-operasional-selesai" class="input-control" placeholder="17:00" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">💾 Simpan Jam Kerja</button>
                        </form>
                    </div>
                </div>
                
                <!-- Kolom Kanan: Pengaturan Tarif Kendaraan (Dinamis) -->
                <div class="sidebar-column">
                    <!-- Form Tambah Kendaraan & Tarif -->
                    <div class="glass-card">
                        <div class="card-title">Tambah Jenis Kendaraan & Tarif Baru</div>
                        
                        <form id="form-tambah-tarif">
                            <div class="form-group">
                                <label for="input-nama-kendaraan">Nama Kendaraan (Contoh: Truk, Bus, Sepeda)</label>
                                <input type="text" id="input-nama-kendaraan" class="input-control" placeholder="Truk" required>
                            </div>
                            <div class="form-group">
                                <label for="input-tarif-kendaraan">Tarif Parkir (Rp)</label>
                                <input type="number" id="input-tarif-kendaraan" class="input-control" min="0" placeholder="10000" required>
                            </div>
                            <button type="submit" class="btn btn-success">➕ Daftarkan Kendaraan & Tarif</button>
                        </form>
                    </div>
                    
                    <!-- List Kendaraan Terdaftar -->
                    <div class="glass-card">
                        <div class="card-title">Tarif Kendaraan Terdaftar</div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Kendaraan</th>
                                        <th>Tarif</th>
                                        <th style="text-align: center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="table-body-tarif">
                                    <!-- Diisi secara dinamis oleh JS -->
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">Memuat daftar tarif...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- ================= PAGE 3a: REKAPAN TRANSAKSI HARIAN (MOBIL & MOTOR) ================= -->
        <div id="page-rekapan-transaksi" class="app-page">
            <div class="page-actions-bar">
                <button class="btn btn-back btn-back-harian" style="max-width: fit-content;">&larr; Kembali ke Rekap Harian</button>
            </div>
            
            <div class="narrow-container" style="max-width: 800px; margin: 0 auto;">
                <div class="glass-card" style="border-top: 3px solid var(--accent-blue);">
                    <div class="card-title">
                        <span>Rekap Transaksi Parkir Hari Ini</span>
                        <span class="badge badge-blue" id="tanggal-rekapan-badge"><?= date('d-m-Y'); ?></span>
                    </div>
                    
                    <p style="color: var(--text-secondary); margin-bottom: 1.5rem; font-size: 0.95rem; font-weight: 500;">
                        Berikut adalah rincian transaksi parkir kendaraan masuk untuk hari ini. Anda dapat menghapus data jika terjadi kesalahan input atau salah tekan.
                    </p>
                    
                    <div style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; align-items: center; flex-wrap: wrap;">
                        <span style="font-weight: 800; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Filter:</span>
                        <button class="badge btn-filter active" data-filter="all" style="cursor: pointer; background: var(--accent-teal);">Semua</button>
                        <button class="badge btn-filter" data-filter="motor" style="cursor: pointer; background: #FFFFFF;">Motor</button>
                        <button class="badge btn-filter" data-filter="mobil" style="cursor: pointer; background: #FFFFFF;">Mobil</button>
                    </div>

                    <div class="table-responsive">
                        <table style="width: 100%;">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Jenis Kendaraan</th>
                                    <th>Tarif</th>
                                    <th style="text-align: center; width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="table-body-rekapan-transaksi">
                                <tr>
                                    <td colspan="4" class="text-center text-muted" style="text-align: center; padding: 2rem;">Memuat rekapan transaksi...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- TOAST NOTIFICATION CONTAINER -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Core App Logic -->
    <script src="assets/js/app.js"></script>
</body>
</html>
