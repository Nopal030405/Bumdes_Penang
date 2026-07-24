<?php
/**
 * Halaman Login Admin BUMDES SUMBER REZEKI
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
    <title>Login - BUMDES SUMBER REZEKI</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Link Stylesheet Utama -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time(); ?>">

    <!-- Cache-bust background image agar selalu fresh tanpa Ctrl+Shift+R -->
    <style>
        body::before {
            background-image: url('assets/background.jpg?v=<?= time(); ?>');
        }
    </style>
</head>
<body class="login-body">

    <div class="login-wrapper">
        <div class="login-card">
            
            <div class="login-header">
                <h2>BUMDES SUMBER REZEKI</h2>
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
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="input-control" placeholder="Masukkan password" required>
                        <button type="button" id="togglePassword" class="password-toggle-btn" aria-label="Tampilkan Password" title="Tampilkan/Sembunyikan Password">
                            <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg id="eyeOffIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                        </button>
                    </div>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePasswordBtn = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');

            if (togglePasswordBtn && passwordInput) {
                togglePasswordBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    
                    if (isPassword) {
                        eyeIcon.style.display = 'none';
                        eyeOffIcon.style.display = 'block';
                        togglePasswordBtn.setAttribute('aria-label', 'Sembunyikan Password');
                    } else {
                        eyeIcon.style.display = 'block';
                        eyeOffIcon.style.display = 'none';
                        togglePasswordBtn.setAttribute('aria-label', 'Tampilkan Password');
                    }
                });
            }
        });
    </script>
</body>
</html>
