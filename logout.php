<?php
/**
 * Logout System - BUMDES SUMBER REZEKI
 */

session_start();

// Hapus semua data session
$_SESSION = [];

// Hancurkan session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session
session_destroy();

// Alihkan kembali ke halaman login
header("Location: login.php");
exit;
