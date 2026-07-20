<?php
/**
 * API Transaksi Parkir Dinamis
 * BUMDes Penang
 */

header('Content-Type: application/json');

// Proteksi: Pastikan user sudah login
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Silakan login terlebih dahulu.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = isset($_GET['action']) ? trim($_GET['action']) : '';
    if ($action === 'list_today') {
        $tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : date('Y-m-d');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }
        try {
            $stmt = $pdo->prepare("
                SELECT id, jenis_kendaraan, tarif, TIME(created_at) as waktu 
                FROM log_transaksi 
                WHERE DATE(created_at) = DATE(?) 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$tanggal]);
            $daftar = $stmt->fetchAll();
            echo json_encode([
                'status' => 'success',
                'data' => $daftar
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal memuat list transaksi: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    if ($action === 'month_status') {
        $year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
        $month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
        
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        $end_date = date('Y-m-t', strtotime($start_date));
        
        try {
            // 1. Ambil tanggal yang memiliki transaksi
            $stmt = $pdo->prepare("
                SELECT DISTINCT DATE(created_at) as tanggal 
                FROM log_transaksi 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $transaksi_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 2. Ambil tanggal tambahan
            $stmt = $pdo->prepare("
                SELECT DISTINCT DATE(created_at) as tanggal 
                FROM pendapatan_tambahan 
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $tambahan_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $terekap_dates = array_unique(array_merge($transaksi_dates, $tambahan_dates));
            
            // 3. Ambil tanggal libur
            $stmt = $pdo->prepare("
                SELECT DISTINCT tanggal 
                FROM hari_libur 
                WHERE tanggal BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $libur_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'terekap' => array_values($terekap_dates),
                    'libur' => array_values($libur_dates)
                ]
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Gagal mengambil status kalender: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    $tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $tanggal = date('Y-m-d');
    }

    try {
        // Ambil info jam operasional
        $operasional = cek_jam_operasional($pdo);
        
        // Ambil semua tipe kendaraan dari pengaturan (kunci berawalan tarif_)
        $stmt_tarif = $pdo->query("SELECT kunci, nilai FROM pengaturan WHERE kunci LIKE 'tarif_%'");
        $kendaraan_tarif = [];
        while ($row = $stmt_tarif->fetch()) {
            $nama_kendaraan = str_replace('tarif_', '', $row['kunci']);
            $kendaraan_tarif[$nama_kendaraan] = intval($row['nilai']);
        }
        
        // Default jika kosong
        if (empty($kendaraan_tarif)) {
            $kendaraan_tarif['motor'] = 2000;
            $kendaraan_tarif['mobil'] = 5000;
        }
        
        // Hitung statistik hari ini untuk masing-masing kendaraan
        $kendaraan_stats = [];
        $total_kendaraan = 0;
        
        foreach ($kendaraan_tarif as $jenis => $tarif) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as jumlah, COALESCE(SUM(tarif), 0) as total 
                FROM log_transaksi 
                WHERE jenis_kendaraan = ? AND DATE(created_at) = DATE(?)
            ");
            $stmt->execute([$jenis, $tanggal]);
            $res = $stmt->fetch();
            
            $kendaraan_stats[$jenis] = [
                'jumlah' => intval($res['jumlah']),
                'total' => intval($res['total']),
                'tarif' => $tarif
            ];
            
            $total_kendaraan += intval($res['total']);
        }
        
        // Query total pendapatan tambahan hari ini
        $stmt_tambahan = $pdo->prepare("
            SELECT COALESCE(SUM(nominal), 0) as total 
            FROM pendapatan_tambahan 
            WHERE DATE(created_at) = DATE(?)
        ");
        $stmt_tambahan->execute([$tanggal]);
        $tambahan = $stmt_tambahan->fetch();
        
        $grand_total = $total_kendaraan + intval($tambahan['total']);
        
        // Cek apakah tanggal terpilih diset libur
        $stmt_libur_date = $pdo->prepare("SELECT keterangan FROM hari_libur WHERE tanggal = ?");
        $stmt_libur_date->execute([$tanggal]);
        $row_libur = $stmt_libur_date->fetch();
        
        $tanggal_terpilih_libur = false;
        $keterangan_libur = '';
        if ($row_libur !== false) {
            $tanggal_terpilih_libur = true;
            $keterangan_libur = $row_libur['keterangan'] ?? '';
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'operasional' => $operasional,
                'hari_ini' => $tanggal,
                'hari_ini_format' => date('d F Y', strtotime($tanggal)),
                'kendaraan' => $kendaraan_stats,
                'tambahan' => [
                    'total' => intval($tambahan['total'])
                ],
                'grand_total' => $grand_total,
                'is_libur' => $tanggal_terpilih_libur,
                'keterangan_libur' => $keterangan_libur
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal memuat statistik harian: ' . $e->getMessage()
        ]);
    }
    exit;
}

if ($method === 'POST') {
    // Ambil data input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $jenis = isset($input['jenis_kendaraan']) ? trim(strtolower($input['jenis_kendaraan'])) : '';
    
    if ($jenis === '') {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Jenis kendaraan tidak boleh kosong.'
        ]);
        exit;
    }
    
    // Validasi Jam Operasional
    $operasional = cek_jam_operasional($pdo);
    if (!$operasional['aktif']) {
        http_response_code(403);
        $status_ket = 'Di luar jam operasional (' . $operasional['mulai'] . ' - ' . $operasional['selesai'] . ').';
        if (isset($operasional['libur']) && $operasional['libur']) {
            $status_ket = 'Hari ini diset sebagai HARI LIBUR UTUH.';
        } else if (isset($operasional['tutup_sementara']) && $operasional['tutup_sementara']) {
            $status_ket = 'Operasional ditutup sementara atau tutup lebih awal.';
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Transaksi ditolak. ' . $status_ket . ' Jam sekarang: ' . $operasional['sekarang']
        ]);
        exit;
    }
    
    try {
        // Ambil tarif aktif dari database untuk kendaraan tersebut
        $kunci_tarif = 'tarif_' . $jenis;
        $tarif_db = dapatkan_pengaturan($pdo, $kunci_tarif, null);
        
        if ($tarif_db === null) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Jenis kendaraan "' . $jenis . '" tidak terdaftar di sistem.'
            ]);
            exit;
        }
        
        $tarif = intval($tarif_db);
        
        // Simpan transaksi
        $stmt = $pdo->prepare("INSERT INTO log_transaksi (jenis_kendaraan, tarif) VALUES (?, ?)");
        $stmt->execute([$jenis, $tarif]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Transaksi ' . ucfirst($jenis) . ' berhasil dicatat!',
            'data' => [
                'jenis_kendaraan' => $jenis,
                'tarif' => $tarif,
                'waktu' => date('H:i:s')
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal mencatat transaksi: ' . $e->getMessage()
        ]);
    }
    exit;
}

if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $id = isset($input['id']) ? intval($input['id']) : 0;
    if ($id <= 0) {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    }
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'ID transaksi tidak valid.'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM log_transaksi WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Transaksi berhasil dihapus!'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus transaksi: ' . $e->getMessage()
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode([
    'status' => 'error',
    'message' => 'Metode HTTP tidak diizinkan.'
]);
