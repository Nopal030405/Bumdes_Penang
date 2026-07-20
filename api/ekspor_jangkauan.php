<?php
/**
 * API Ekspor Rekap Jangkauan Custom ke CSV (Excel)
 * BUMDes Penang
 */

// Proteksi: Pastikan user sudah login
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    die('Akses ditolak. Silakan login terlebih dahulu.');
}

require_once __DIR__ . '/../config/db.php';

$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');

// Validasi format tanggal YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    die("Format tanggal tidak valid. Gunakan format YYYY-MM-DD.");
}

$start_time = strtotime($start_date);
$end_time = strtotime($end_date);

if ($end_time < $start_time) {
    // swap
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
    
    $temp_time = $start_time;
    $start_time = $end_time;
    $end_time = $temp_time;
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
    $filename = "Rekap_Jangkauan_" . date('Ymd', $start_time) . "_ke_" . date('Ymd', $end_time) . ".csv";
    
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
    fputcsv($output, ["BUMDES PENANG - LAPORAN REKAP PENDAPATAN JANGKAUAN CUSTOM"]);
    fputcsv($output, ["Rentang Rekap", date('d F Y', $start_time) . " s.d. " . date('d F Y', $end_time)]);
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
    
    // --- SECTION 1: RINGKASAN HARIAN ---
    fputcsv($output, ["I. RINGKASAN HARIAN"]);
    
    $header_row = ["Hari", "Tanggal"];
    foreach ($unique_vehicles as $jenis) {
        $header_row[] = "Parkir " . ucfirst($jenis) . " (Rp)";
    }
    $header_row[] = "Tambahan (Rp)";
    $header_row[] = "Total Harian (Rp)";
    fputcsv($output, $header_row);
    
    $grand_total_range = 0;
    $vehicle_totals = array_fill_keys($unique_vehicles, 0);
    $tambahan_total_range = 0;
    
    $curr_time = $start_time;
    while ($curr_time <= $end_time) {
        $current_day = date('Y-m-d', $curr_time);
        $day_name = formatHariIndo(date('l', $curr_time));
        $day_date = date('d-m-Y', $curr_time);
        
        $row_data = [$day_name, $day_date];
        $day_total = 0;
        
        // Cek apakah hari ini diset libur
        $stmt_libur = $pdo->prepare("SELECT COUNT(*) FROM hari_libur WHERE tanggal = ?");
        $stmt_libur->execute([$current_day]);
        $is_libur = intval($stmt_libur->fetchColumn()) > 0;
        
        if ($is_libur) {
            foreach ($unique_vehicles as $jenis) {
                $row_data[] = "0 (LIBUR)";
            }
            $row_data[] = "0 (LIBUR)";
            $row_data[] = "0 (LIBUR)";
        } else {
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
            $tambahan_total_range += $tambahan;
            
            $row_data[] = $day_total;
            $grand_total_range += $day_total;
        }
        
        fputcsv($output, $row_data);
        $curr_time += 86400; // next day
    }
    
    // Baris Grand Total
    $total_row = ["TOTAL REKAP", "-"];
    foreach ($unique_vehicles as $jenis) {
        $total_row[] = $vehicle_totals[$jenis];
    }
    $total_row[] = $tambahan_total_range;
    $total_row[] = $grand_total_range;
    fputcsv($output, $total_row);
    fputcsv($output, []); // Baris kosong
    
    // --- SECTION 2: DETAIL TRANSAKSI PARKIR ---
    fputcsv($output, ["II. DETAIL TRANSAKSI PARKIR"]);
    fputcsv($output, ["No", "Tanggal & Waktu", "Jenis Kendaraan", "Tarif (Rp)"]);
    
    $stmt_detail_parkir = $pdo->prepare("
        SELECT created_at, jenis_kendaraan, tarif 
        FROM log_transaksi 
        WHERE DATE(created_at) BETWEEN DATE(?) AND DATE(?)
        ORDER BY created_at ASC
    ");
    $stmt_detail_parkir->execute([$start_date, $end_date]);
    
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
        fputcsv($output, ["-", "Tidak ada transaksi parkir pada jangka waktu ini.", "-", "-"]);
    }
    fputcsv($output, []); // Baris kosong
    
    // --- SECTION 3: DETAIL PENDAPATAN TAMBAHAN ---
    fputcsv($output, ["III. DETAIL PENDAPATAN TAMBAHAN"]);
    fputcsv($output, ["No", "Tanggal & Waktu", "Keterangan Keperluan", "Nominal (Rp)"]);
    
    $stmt_detail_tambahan = $pdo->prepare("
        SELECT created_at, nama_item, nominal 
        FROM pendapatan_tambahan 
        WHERE DATE(created_at) BETWEEN DATE(?) AND DATE(?)
        ORDER BY created_at ASC
    ");
    $stmt_detail_tambahan->execute([$start_date, $end_date]);
    
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
        fputcsv($output, ["-", "Tidak ada pendapatan tambahan pada jangka waktu ini.", "-", "-"]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    die("Gagal mengekspor data jangkauan: " . $e->getMessage());
}
