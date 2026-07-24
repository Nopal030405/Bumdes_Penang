<?php
/**
 * API Ekspor Rekap Harian ke CSV (Excel) - Dinamis
 * BUMDES SUMBER REZEKI
 */

// Proteksi: Pastikan user sudah login
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    die('Akses ditolak. Silakan login terlebih dahulu.');
}

require_once __DIR__ . '/../config/db.php';

// Ambil tanggal pencarian (default: hari ini)
$tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : date('Y-m-d');

// Validasi format tanggal YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    die("Format tanggal tidak valid. Gunakan format YYYY-MM-DD.");
}

try {
    // Pecah tanggal untuk penamaan file dinamis
    $time = strtotime($tanggal);
    $bulan = date('n', $time);   // 1 s.d. 12
    $hari = date('j', $time);    // 1 s.d. 31
    $tahun = date('Y', $time);   // Contoh: 2026
    
    $filename = "Rekap_Harian_{$bulan}-{$hari}-{$tahun}.csv";
    
    // Set header untuk unduhan file CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Buka standard output stream
    $output = fopen('php://output', 'w');
    
    // Tulis UTF-8 BOM untuk kompatibilitas Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Tulis pemisah (separator) khusus agar rapi di Microsoft Excel versi apapun
    fprintf($output, "sep=,\n");
    
    // --- HEADER LAPORAN ---
    fputcsv($output, ["BUMDES SUMBER REZEKI - LAPORAN REKAP PENDAPATAN HARIAN"]);
    fputcsv($output, ["Tanggal Laporan", date('d F Y', $time)]);
    fputcsv($output, ["Diunduh Pada", date('d-m-Y H:i:s')]);
    fputcsv($output, []); // Baris kosong
    
    // --- SECTION 1: RINGKASAN PENDAPATAN ---
    fputcsv($output, ["I. RINGKASAN PENDAPATAN"]);
    fputcsv($output, ["Kategori Pemasukan", "Jumlah Transaksi", "Total Pendapatan (Rp)"]);
    
    // Ambil daftar jenis kendaraan yang bertransaksi hari ini atau yang ada di pengaturan
    $stmt_jenis = $pdo->prepare("
        SELECT DISTINCT jenis_kendaraan FROM log_transaksi WHERE DATE(created_at) = DATE(?)
        UNION
        SELECT DISTINCT REPLACE(kunci, 'tarif_', '') as jenis_kendaraan FROM pengaturan WHERE kunci LIKE 'tarif_%'
    ");
    $stmt_jenis->execute([$tanggal]);
    $daftar_jenis = [];
    while ($row = $stmt_jenis->fetch()) {
        $jenis_nama = trim(strtolower($row['jenis_kendaraan']));
        if ($jenis_nama !== '') {
            $daftar_jenis[$jenis_nama] = true;
        }
    }
    
    $total_kendaraan_rupiah = 0;
    
    foreach (array_keys($daftar_jenis) as $jenis) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as jumlah, COALESCE(SUM(tarif), 0) as total 
            FROM log_transaksi 
            WHERE jenis_kendaraan = ? AND DATE(created_at) = DATE(?)
        ");
        $stmt->execute([$jenis, $tanggal]);
        $res = $stmt->fetch();
        
        fputcsv($output, ["Parkir " . ucfirst($jenis), $res['jumlah'], $res['total']]);
        
        $total_kendaraan_rupiah += intval($res['total']);
    }
    
    // Ambil data Pendapatan Tambahan
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as jumlah, COALESCE(SUM(nominal), 0) as total 
        FROM pendapatan_tambahan 
        WHERE DATE(created_at) = DATE(?)
    ");
    $stmt->execute([$tanggal]);
    $tambahan = $stmt->fetch();
    fputcsv($output, ["Pendapatan Tambahan (Sewa/Toilet/dll)", $tambahan['jumlah'], $tambahan['total']]);
    
    // Grand Total
    $grand_total = $total_kendaraan_rupiah + intval($tambahan['total']);
    fputcsv($output, ["GRAND TOTAL PENDAPATAN", "-", $grand_total]);
    fputcsv($output, []); // Baris kosong
    
    // --- SECTION 2: DETAIL TRANSAKSI PARKIR ---
    fputcsv($output, ["II. DETAIL TRANSAKSI PARKIR"]);
    fputcsv($output, ["No", "Waktu Transaksi", "Jenis Kendaraan", "Tarif (Rp)"]);
    
    $stmt_detail_parkir = $pdo->prepare("
        SELECT TIME(created_at) as waktu, jenis_kendaraan, tarif 
        FROM log_transaksi 
        WHERE DATE(created_at) = DATE(?)
        ORDER BY created_at ASC
    ");
    $stmt_detail_parkir->execute([$tanggal]);
    
    $no = 1;
    while ($row = $stmt_detail_parkir->fetch()) {
        fputcsv($output, [
            $no++,
            $row['waktu'],
            ucfirst($row['jenis_kendaraan']),
            $row['tarif']
        ]);
    }
    
    if ($no === 1) {
        fputcsv($output, ["-", "Tidak ada transaksi parkir pada tanggal ini.", "-", "-"]);
    }
    fputcsv($output, []); // Baris kosong
    
    // --- SECTION 3: DETAIL PENDAPATAN TAMBAHAN ---
    fputcsv($output, ["III. DETAIL PENDAPATAN TAMBAHAN"]);
    fputcsv($output, ["No", "Waktu Transaksi", "Keterangan Keperluan", "Nominal (Rp)"]);
    
    $stmt_detail_tambahan = $pdo->prepare("
        SELECT TIME(created_at) as waktu, nama_item, nominal 
        FROM pendapatan_tambahan 
        WHERE DATE(created_at) = DATE(?)
        ORDER BY created_at ASC
    ");
    $stmt_detail_tambahan->execute([$tanggal]);
    
    $no_t = 1;
    while ($row = $stmt_detail_tambahan->fetch()) {
        fputcsv($output, [
            $no_t++,
            $row['waktu'],
            $row['nama_item'],
            $row['nominal']
        ]);
    }
    
    if ($no_t === 1) {
        fputcsv($output, ["-", "Tidak ada pendapatan tambahan pada tanggal ini.", "-", "-"]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Gagal mengekspor data: " . $e->getMessage());
}
