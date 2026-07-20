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
    
    if ($username === 'KKN30UTM' && $password === 'kkn30somalang2026') {
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
    <link rel="stylesheet" href="assets/css/style.css?v=1753098000">
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
                    Masuk ke Dashboard &rarr;
                </button>
            </form>
            
            <div class="login-footer-container" style="margin-top: 1.75rem; padding: 0.85rem 1rem; background: #F8F9FA; border: 2px solid #0D0D0D; border-radius: 8px; box-shadow: 3px 3px 0px #0D0D0D; display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                <img src="assets/logo-utm.png" alt="Logo UTM" style="height: 42px; width: auto; object-fit: contain;">
                <div style="text-align: center; flex: 1;">
                    <div style="font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 0.85rem; color: #0D0D0D; line-height: 1.2; text-transform: uppercase; letter-spacing: 0.5px;">KKN 30 UTM</div>
                    <div style="font-family: 'Inter', sans-serif; font-weight: 600; font-size: 0.65rem; color: #52575E; line-height: 1.3; margin-top: 0.2rem;">Ds Somalang, Kec Pakong, Kab Pamekasan</div>
                </div>
                <img src="assets/logo-kkn.png" alt="Logo KKN" style="height: 42px; width: auto; object-fit: contain;">
            </div>
            
        </div>
    </div>

</body>
</html>
