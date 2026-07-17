<?php
/**
 * API Ekspor Rekap Mingguan ke CSV (Excel)
 * BUMDes Penang
 */

// Proteksi: Pastikan user sudah login
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    die('Akses ditolak. Silakan login terlebih dahulu.');
}

require_once __DIR__ . '/../config/db.php';

// Ambil tanggal (default: hari ini)
$tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : date('Y-m-d');

// Validasi format tanggal YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    die("Format tanggal tidak valid. Gunakan format YYYY-MM-DD.");
}

function formatHariIndo($day_name) {
    $map = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu'
    ];
    return $map[$day_name] ?? $day_name;
}

try {
    $time = strtotime($tanggal);
    $day_of_week = date('N', $time); // 1 (Senin) s.d. 7 (Minggu)
    
    // Tentukan hari Senin dan Minggu dari minggu terpilih
    $monday_time = $time - (($day_of_week - 1) * 86400);
    $sunday_time = $monday_time + (6 * 86400);
    
    $monday = date('Y-m-d', $monday_time);
    $sunday = date('Y-m-d', $sunday_time);
    
    $today = date('Y-m-d');
    
    // Batasan: jika hari Minggu dari minggu terpilih di masa depan, tolak unduhan
    if ($sunday > $today) {
        http_response_code(400);
        die("Gagal mengunduh: Minggu ini belum berakhir. Laporan hanya dapat diunduh setelah akhir minggu (" . date('d F Y', $sunday_time) . ").");
    }
    
    $filename = "Rekap_Mingguan_" . date('Ymd', $monday_time) . "_ke_" . date('Ymd', $sunday_time) . ".csv";
    
    // Set header untuk unduhan file CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Buka standard output stream
    $output = fopen('php://output', 'w');
    
    // Tulis UTF-8 BOM untuk kompatibilitas Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // --- HEADER LAPORAN ---
    fputcsv($output, ["BUMDES PENANG - LAPORAN REKAP PENDAPATAN MINGGUAN"]);
    fputcsv($output, ["Rentang Rekap", date('d F Y', $monday_time) . " s.d. " . date('d F Y', $sunday_time)]);
    fputcsv($output, ["Diunduh Pada", date('d-m-Y H:i:s')]);
    fputcsv($output, []); // Baris kosong
    
    // Ambil daftar kendaraan dari pengaturan
    $stmt_tarif = $pdo->query("SELECT DISTINCT REPLACE(kunci, 'tarif_', '') as jenis_kendaraan FROM pengaturan WHERE kunci LIKE 'tarif_%'");
    $unique_vehicles = [];
    while ($row = $stmt_tarif->fetch()) {
        $unique_vehicles[] = $row['jenis_kendaraan'];
    }
    if (empty($unique_vehicles)) {
        $unique_vehicles = ['motor', 'mobil'];
    }
    
    // --- SECTION 1: RINGKASAN PENDAPATAN HARIAN ---
    fputcsv($output, ["I. RINGKASAN HARIAN MINGGU INI"]);
    
    // Header tabel ringkasan
    $header_row = ["Hari", "Tanggal"];
    foreach ($unique_vehicles as $jenis) {
        $header_row[] = "Parkir " . ucfirst($jenis) . " (Rp)";
    }
    $header_row[] = "Tambahan (Rp)";
    $header_row[] = "Total Harian (Rp)";
    fputcsv($output, $header_row);
    
    $grand_total_week = 0;
    $vehicle_totals = array_fill_keys($unique_vehicles, 0);
    $tambahan_total_week = 0;
    
    for ($i = 0; $i < 7; $i++) {
        $current_day_time = $monday_time + ($i * 86400);
        $current_day = date('Y-m-d', $current_day_time);
        
        $day_name = formatHariIndo(date('l', $current_day_time));
        $day_date = date('d-m-Y', $current_day_time);
        
        $row_data = [$day_name, $day_date];
        $day_total = 0;
        
        // Pendapatan kendaraan
        foreach ($unique_vehicles as $jenis) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(tarif), 0) as total 
                FROM log_transaksi 
                WHERE jenis_kendaraan = ? AND DATE(created_at) = DATE(?)
            ");
            $stmt->execute([$jenis, $current_day]);
            $res = $stmt->fetch();
            $total = intval($res['total']);
            
            $row_data[] = $total;
            $day_total += $total;
            $vehicle_totals[$jenis] += $total;
        }
        
        // Pendapatan tambahan
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(nominal), 0) as total 
            FROM pendapatan_tambahan 
            WHERE DATE(created_at) = DATE(?)
        ");
        $stmt->execute([$current_day]);
        $res = $stmt->fetch();
        $tambahan = intval($res['total']);
        
        $row_data[] = $tambahan;
        $day_total += $tambahan;
        $tambahan_total_week += $tambahan;
        
        $row_data[] = $day_total;
        $grand_total_week += $day_total;
        
        fputcsv($output, $row_data);
    }
    
    // Baris Grand Total
    $total_row = ["TOTAL REKAP", "-"];
    foreach ($unique_vehicles as $jenis) {
        $total_row[] = $vehicle_totals[$jenis];
    }
    $total_row[] = $tambahan_total_week;
    $total_row[] = $grand_total_week;
    fputcsv($output, $total_row);
    fputcsv($output, []); // Baris kosong
    
    // --- SECTION 2: DETAIL TRANSAKSI PARKIR MINGGU INI ---
    fputcsv($output, ["II. DETAIL TRANSAKSI PARKIR MINGGU INI"]);
    fputcsv($output, ["No", "Tanggal & Waktu", "Jenis Kendaraan", "Tarif (Rp)"]);
    
    $stmt_detail_parkir = $pdo->prepare("
        SELECT created_at, jenis_kendaraan, tarif 
        FROM log_transaksi 
        WHERE DATE(created_at) BETWEEN DATE(?) AND DATE(?)
        ORDER BY created_at ASC
    ");
    $stmt_detail_parkir->execute([$monday, $sunday]);
    
    $no = 1;
    while ($row = $stmt_detail_parkir->fetch()) {
        fputcsv($output, [
            $no++,
            $row['created_at'],
            ucfirst($row['jenis_kendaraan']),
            $row['tarif']
        ]);
    }
    
    if ($no === 1) {
        fputcsv($output, ["-", "Tidak ada transaksi parkir pada minggu ini.", "-", "-"]);
    }
    fputcsv($output, []); // Baris kosong
    
    // --- SECTION 3: DETAIL PENDAPATAN TAMBAHAN MINGGU INI ---
    fputcsv($output, ["III. DETAIL PENDAPATAN TAMBAHAN MINGGU INI"]);
    fputcsv($output, ["No", "Tanggal & Waktu", "Keterangan Keperluan", "Nominal (Rp)"]);
    
    $stmt_detail_tambahan = $pdo->prepare("
        SELECT created_at, nama_item, nominal 
        FROM pendapatan_tambahan 
        WHERE DATE(created_at) BETWEEN DATE(?) AND DATE(?)
        ORDER BY created_at ASC
    ");
    $stmt_detail_tambahan->execute([$monday, $sunday]);
    
    $no_t = 1;
    while ($row = $stmt_detail_tambahan->fetch()) {
        fputcsv($output, [
            $no_t++,
            $row['created_at'],
            $row['nama_item'],
            $row['nominal']
        ]);
    }
    
    if ($no_t === 1) {
        fputcsv($output, ["-", "Tidak ada pendapatan tambahan pada minggu ini.", "-", "-"]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Gagal mengekspor data mingguan: " . $e->getMessage());
}
