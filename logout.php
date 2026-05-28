<?php
// logout.php - Secure Logout Action Helper
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Bersihkan semua variabel session
$_SESSION = [];

// 2. Hancurkan cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Hancurkan data session di server
session_destroy();

// 4. Redirect kembali ke gerbang login
header("Location: login.php");
exit;
?>
