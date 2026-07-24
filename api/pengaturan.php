<?php
/**
 * API Pengaturan Sistem
 * BUMDES SUMBER REZEKI
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
    try {
        // Ambil semua pengaturan
        $stmt = $pdo->query("SELECT kunci, nilai FROM pengaturan");
        $pengaturan = [];
        while ($row = $stmt->fetch()) {
            $pengaturan[$row['kunci']] = $row['nilai'];
        }
        
        // Pastikan beberapa default key ada jika kosong
        if (!isset($pengaturan['tarif_motor'])) $pengaturan['tarif_motor'] = '2000';
        if (!isset($pengaturan['tarif_mobil'])) $pengaturan['tarif_mobil'] = '5000';
        if (!isset($pengaturan['operasional_mulai'])) $pengaturan['operasional_mulai'] = '07:00';
        if (!isset($pengaturan['operasional_selesai'])) $pengaturan['operasional_selesai'] = '17:00';
        if (!isset($pengaturan['status_libur'])) $pengaturan['status_libur'] = '0';
        if (!isset($pengaturan['status_tutup_sementara'])) $pengaturan['status_tutup_sementara'] = '0';
        
        echo json_encode([
            'status' => 'success',
            'data' => $pengaturan
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal mengambil pengaturan: ' . $e->getMessage()
        ]);
    }
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $action = isset($input['action']) ? trim($input['action']) : '';
    
    // --- 1. Aksi Simpan Jam Operasional ---
    if ($action === 'save_general') {
        $operasional_mulai = isset($input['operasional_mulai']) ? trim($input['operasional_mulai']) : null;
        $operasional_selesai = isset($input['operasional_selesai']) ? trim($input['operasional_selesai']) : null;
        
        $time_pattern = '/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/';
        if ($operasional_mulai !== null && !preg_match($time_pattern, $operasional_mulai)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Format jam mulai tidak valid (harus HH:MM).']);
            exit;
        }
        if ($operasional_selesai !== null && !preg_match($time_pattern, $operasional_selesai)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Format jam selesai tidak valid (harus HH:MM).']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            if ($operasional_mulai !== null) {
                $stmt->execute(['operasional_mulai', $operasional_mulai]);
            }
            if ($operasional_selesai !== null) {
                $stmt->execute(['operasional_selesai', $operasional_selesai]);
            }
            $pdo->commit();
            
            echo json_encode(['status' => 'success', 'message' => 'Jam operasional berhasil diperbarui!']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan jam operasional: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // --- 2. Aksi Simpan/Tambah Tarif Baru ---
    if ($action === 'save_tarif') {
        $jenis = isset($input['jenis_kendaraan']) ? trim(strtolower($input['jenis_kendaraan'])) : '';
        $tarif = isset($input['tarif']) ? intval($input['tarif']) : null;
        
        if ($jenis === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nama kendaraan tidak boleh kosong.']);
            exit;
        }
        
        // Filter nama kunci agar hanya alphanumeric & underscore
        $jenis_key = preg_replace('/[^a-z0-9_]/', '', $jenis);
        if ($jenis_key === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nama kendaraan tidak valid.']);
            exit;
        }
        
        if ($tarif === null || $tarif < 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tarif tidak boleh kosong atau bernilai negatif.']);
            exit;
        }
        
        $kunci = 'tarif_' . $jenis_key;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO pengaturan (kunci, nilai, keterangan) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            $stmt->execute([$kunci, $tarif, 'Tarif parkir untuk ' . $jenis]);
            
            echo json_encode(['status' => 'success', 'message' => 'Tarif ' . ucfirst($jenis) . ' berhasil disimpan!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan tarif: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // --- 3. Aksi Hapus Tarif ---
    if ($action === 'delete_tarif') {
        $jenis = isset($input['jenis_kendaraan']) ? trim(strtolower($input['jenis_kendaraan'])) : '';
        if ($jenis === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nama kendaraan tidak boleh kosong.']);
            exit;
        }
        
        $kunci = 'tarif_' . $jenis;
        
        try {
            $stmt = $pdo->prepare("DELETE FROM pengaturan WHERE kunci = ?");
            $stmt->execute([$kunci]);
            
            echo json_encode(['status' => 'success', 'message' => 'Tarif ' . ucfirst($jenis) . ' berhasil dihapus!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus tarif: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // --- 4. Aksi Toggle Libur ---
    if ($action === 'toggle_libur') {
        $status_libur = isset($input['status_libur']) ? trim($input['status_libur']) : '0';
        $keterangan = isset($input['keterangan']) ? trim($input['keterangan']) : 'Libur';
        
        if ($status_libur !== '1' && $status_libur !== '0') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Status libur tidak valid.']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO pengaturan (kunci, nilai, keterangan) VALUES ('status_libur', ?, 'Status libur') ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            $stmt->execute([$status_libur]);
            
            if ($status_libur === '1') {
                $stmt_libur = $pdo->prepare("INSERT INTO hari_libur (tanggal, keterangan) VALUES (CURDATE(), ?) ON DUPLICATE KEY UPDATE keterangan = VALUES(keterangan)");
                $stmt_libur->execute([$keterangan]);
            } else {
                $stmt_libur = $pdo->prepare("DELETE FROM hari_libur WHERE tanggal = CURDATE()");
                $stmt_libur->execute();
            }
            $pdo->commit();
            
            $msg = ($status_libur === '1') ? 'Status operasional diatur libur!' : 'Status operasional diaktifkan kembali!';
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status libur: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // --- 4b. Aksi Toggle Libur Dinamis Per Tanggal ---
    if ($action === 'toggle_libur_date') {
        $tanggal = isset($input['tanggal']) ? trim($input['tanggal']) : '';
        $status_libur = isset($input['status_libur']) ? trim($input['status_libur']) : '0';
        $keterangan = isset($input['keterangan']) ? trim($input['keterangan']) : 'Libur';
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tanggal tidak valid.']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            if ($status_libur === '1') {
                $stmt = $pdo->prepare("INSERT INTO hari_libur (tanggal, keterangan) VALUES (?, ?) ON DUPLICATE KEY UPDATE keterangan = VALUES(keterangan)");
                $stmt->execute([$tanggal, $keterangan]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM hari_libur WHERE tanggal = ?");
                $stmt->execute([$tanggal]);
            }
            
            // Jika tanggal tersebut adalah hari ini, sinkronkan juga dengan status_libur global
            if ($tanggal === date('Y-m-d')) {
                $stmt_setting = $pdo->prepare("INSERT INTO pengaturan (kunci, nilai, keterangan) VALUES ('status_libur', ?, 'Status libur') ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
                $stmt_setting->execute([$status_libur]);
            }
            $pdo->commit();
            
            echo json_encode(['status' => 'success', 'message' => 'Status libur tanggal ' . $tanggal . ' berhasil diperbarui!']);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui status libur: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // --- 5. Aksi Toggle Tutup Sementara ---
    if ($action === 'toggle_tutup_sementara') {
        $status_tutup_sementara = isset($input['status_tutup_sementara']) ? trim($input['status_tutup_sementara']) : '0';
        if ($status_tutup_sementara !== '1' && $status_tutup_sementara !== '0') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Status tutup sementara tidak valid.']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO pengaturan (kunci, nilai, keterangan) VALUES ('status_tutup_sementara', ?, 'Status tutup sementara') ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            $stmt->execute([$status_tutup_sementara]);
            
            $msg = ($status_tutup_sementara === '1') ? 'Operasional ditutup sementara!' : 'Operasional dibuka kembali!';
            echo json_encode(['status' => 'success', 'message' => $msg]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status tutup sementara: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // --- Fallback Pengaturan Legacy (Lama) ---
    $tarif_motor = isset($input['tarif_motor']) ? intval($input['tarif_motor']) : null;
    $tarif_mobil = isset($input['tarif_mobil']) ? intval($input['tarif_mobil']) : null;
    $operasional_mulai = isset($input['operasional_mulai']) ? trim($input['operasional_mulai']) : null;
    $operasional_selesai = isset($input['operasional_selesai']) ? trim($input['operasional_selesai']) : null;
    
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        if ($tarif_motor !== null) $stmt->execute(['tarif_motor', $tarif_motor]);
        if ($tarif_mobil !== null) $stmt->execute(['tarif_mobil', $tarif_mobil]);
        if ($operasional_mulai !== null) $stmt->execute(['operasional_mulai', $operasional_mulai]);
        if ($operasional_selesai !== null) $stmt->execute(['operasional_selesai', $operasional_selesai]);
        $pdo->commit();
        
        echo json_encode(['status' => 'success', 'message' => 'Pengaturan berhasil diperbarui!']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui pengaturan: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode([
    'status' => 'error',
    'message' => 'Metode HTTP tidak diizinkan.'
]);
