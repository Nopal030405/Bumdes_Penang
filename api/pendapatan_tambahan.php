<?php
/**
 * API Pendapatan Tambahan
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
    $tanggal = isset($_GET['tanggal']) ? trim($_GET['tanggal']) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        $tanggal = date('Y-m-d');
    }
    try {
        // Ambil daftar pendapatan tambahan hari terpilih
        $stmt = $pdo->prepare("
            SELECT id, nama_item, nominal, TIME(created_at) as waktu 
            FROM pendapatan_tambahan 
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
            'message' => 'Gagal mengambil riwayat pendapatan tambahan: ' . $e->getMessage()
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
    
    $nama_item = isset($input['nama_item']) ? trim($input['nama_item']) : '';
    $nominal = isset($input['nominal']) ? intval($input['nominal']) : 0;
    
    // Validasi
    if (empty($nama_item)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Nama keperluan/item tidak boleh kosong.'
        ]);
        exit;
    }
    if ($nominal <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Nominal pendapatan harus berupa angka lebih dari 0.'
        ]);
        exit;
    }
    
    try {
        // Simpan data
        $stmt = $pdo->prepare("INSERT INTO pendapatan_tambahan (nama_item, nominal) VALUES (?, ?)");
        $stmt->execute([$nama_item, $nominal]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Pendapatan tambahan berhasil dicatat!',
            'data' => [
                'nama_item' => $nama_item,
                'nominal' => $nominal,
                'waktu' => date('H:i:s')
            ]
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal mencatat pendapatan tambahan: ' . $e->getMessage()
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
            'message' => 'ID tidak valid.'
        ]);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM pendapatan_tambahan WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Pendapatan tambahan berhasil dihapus!'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Gagal menghapus pendapatan tambahan: ' . $e->getMessage()
        ]);
    }
    exit;
}

http_response_code(405);
echo json_encode([
    'status' => 'error',
    'message' => 'Metode HTTP tidak diizinkan.'
]);
