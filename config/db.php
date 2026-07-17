<?php
/**
 * Konfigurasi Database & Koneksi PDO
 * BUMDes Penang - Sistem Rekap Penghasilan
 */

// Pengaturan Zona Waktu Lokal (Sesuai WIB/WITA/WIT, diset ke Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Database (Sesuaikan saat deploy di cPanel)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bumdes_penang');

try {
    // Membuat koneksi database menggunakan PDO
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // Sinkronisasi zona waktu session MySQL dengan PHP (+07:00 untuk Asia/Jakarta)
    $pdo->exec("SET time_zone = '+07:00'");
    
} catch (PDOException $e) {
    // Tampilkan pesan error JSON jika dipanggil via API, atau teks jika langsung
    if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Koneksi database gagal: ' . $e->getMessage()
        ]);
        exit;
    } else {
        die("Koneksi database gagal. Silakan periksa konfigurasi database Anda. Detail: " . $e->getMessage());
    }
}

/**
 * Mendapatkan nilai pengaturan berdasarkan kunci
 * @param PDO $pdo Koneksi database
 * @param string $kunci Kunci pengaturan
 * @param mixed $default Nilai default jika tidak ditemukan
 * @return string
 */
function dapatkan_pengaturan($pdo, $kunci, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT nilai FROM pengaturan WHERE kunci = ?");
        $stmt->execute([$kunci]);
        $result = $stmt->fetch();
        return $result ? $result['nilai'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Mengecek apakah waktu sekarang berada di dalam jam operasional aktif
 * @param PDO $pdo Koneksi database
 * @return array ['aktif' => bool, 'mulai' => string, 'selesai' => string, 'sekarang' => string]
 */
function cek_jam_operasional($pdo) {
    $libur = dapatkan_pengaturan($pdo, 'status_libur', '0');
    if ($libur === '1') {
        return [
            'aktif' => false,
            'libur' => true,
            'tutup_sementara' => false,
            'mulai' => dapatkan_pengaturan($pdo, 'operasional_mulai', '07:00'),
            'selesai' => dapatkan_pengaturan($pdo, 'operasional_selesai', '17:00'),
            'sekarang' => date('H:i')
        ];
    }

    $tutup_sementara = dapatkan_pengaturan($pdo, 'status_tutup_sementara', '0');
    if ($tutup_sementara === '1') {
        return [
            'aktif' => false,
            'libur' => false,
            'tutup_sementara' => true,
            'mulai' => dapatkan_pengaturan($pdo, 'operasional_mulai', '07:00'),
            'selesai' => dapatkan_pengaturan($pdo, 'operasional_selesai', '17:00'),
            'sekarang' => date('H:i')
        ];
    }

    $mulai = dapatkan_pengaturan($pdo, 'operasional_mulai', '07:00');
    $selesai = dapatkan_pengaturan($pdo, 'operasional_selesai', '17:00');
    
    $jam_sekarang = date('H:i');
    
    // Perbandingan waktu secara string format HH:MM
    $aktif = ($jam_sekarang >= $mulai && $jam_sekarang <= $selesai);
    
    return [
        'aktif' => $aktif,
        'libur' => false,
        'tutup_sementara' => false,
        'mulai' => $mulai,
        'selesai' => $selesai,
        'sekarang' => $jam_sekarang
    ];
}
