<?php
/**
 * Halaman Login Admin BUMDes Penang
 * Sistem Autentikasi Admin
 */

// Mulai session PHP
session_start();

// Jika pengguna sudah login, langsung alihkan ke index.php
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';

// Proses form login jika dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if ($username === 'KKN30UTM' && $password === 'NopalKordes123') {
        // Simpan status login di session
        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = $username;
        
        // Alihkan ke halaman dashboard utama
        header('Location: index.php');
        exit;
    } else {
        $error = 'Username atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BUMDes Penang</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Link Stylesheet Utama -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="login-header">
                <h2>BUMDes Penang</h2>
                <p>Portal Sistem Rekap Keuangan</p>
            </div>
            
            <?php if ($error !== ''): ?>
                <div class="login-error-alert">
                    ❌ <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="input-control" placeholder="Masukkan username" required autofocus>
                </div>
                
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="input-control" placeholder="Masukkan password" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    🚪 Masuk ke Dashboard &rarr;
                </button>
            </form>
            
            <div class="login-footer">
                * Masuk menggunakan kredensial admin KKN Universitas Trunojoyo Madura Kelompok 30.
            </div>
            
        </div>
    </div>

</body>
</html>
