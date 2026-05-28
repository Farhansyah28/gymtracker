<?php
// login.php - Secure Authentication and Biodata Onboarding (Mobile Only View)
require_once 'db.php';

// Inisialisasi session dengan parameter keamanan
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => false, // Set ke true jika menggunakan HTTPS
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// Deteksi Kunci Cloudflare Turnstile (Lokal vs Production)
if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
    // Testing/Mock Keys (Selalu berhasil untuk testing di localhost)
    $turnstile_sitekey = "1x00000000000000000000AA";
    $turnstile_secret = "1x000000000000000000000000000000AA";
} else {
    // Kunci Produksi Asli untuk gymtracker.boang.my.id
    // Silakan ganti nilai di bawah ini dengan Site Key & Secret Key asli dari dashboard Cloudflare Anda!
    $turnstile_sitekey = "0x4AAAAAADXkLKsUWV4FQQ0_";
    $turnstile_secret = "0x4AAAAAADXkLPn63V_WoQdbjrghc4ZUfg0";
}

// Redirect ke dashboard jika sudah login
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$errors = [];
$success = '';
$active_tab = 'login'; // default tab

// Proses Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. PROSES LOGIN
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $active_tab = 'login';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errors[] = "Username dan password wajib diisi!";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Regenerasi ID Sesi secara aman untuk proteksi Session Fixation
                    session_regenerate_id(true);

                    // Simpan data sesi
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];

                    header("Location: index.php");
                    exit;
                } else {
                    $errors[] = "Username atau password salah!";
                }
            } catch (PDOException $e) {
                $errors[] = "Terjadi kesalahan sistem: " . $e->getMessage();
            }
        }
    }

    // 2. PROSES REGISTRASI + BIODATA DENGAN CLOUDFLARE TURNSTILE
    elseif (isset($_POST['action']) && $_POST['action'] === 'register') {
        $active_tab = 'register';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $height = filter_var($_POST['height'] ?? null, FILTER_VALIDATE_FLOAT);
        $weight = filter_var($_POST['weight'] ?? null, FILTER_VALIDATE_FLOAT);
        $age = filter_var($_POST['age'] ?? null, FILTER_VALIDATE_INT);
        $gender = trim($_POST['gender'] ?? '');
        $turnstile_response = $_POST['cf-turnstile-response'] ?? '';

        // Validasi Turnstile Token dengan Cloudflare API
        if (empty($turnstile_response)) {
            $errors[] = "Silakan lengkapi verifikasi Cloudflare Turnstile.";
        } else {
            // Menggunakan $turnstile_secret dari pendeteksi lingkungan di atas

            $verify_url = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
            $post_data = http_build_query([
                'secret' => $turnstile_secret,
                'response' => $turnstile_response,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);

            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-type: application-x-www-form-urlencoded\r\n",
                    'content' => $post_data,
                    'timeout' => 5
                ]
            ];

            $context = stream_context_create($opts);
            $response_json = @file_get_contents($verify_url, false, $context);
            $response = json_decode($response_json, true);

            if (!$response || !isset($response['success']) || !$response['success']) {
                $errors[] = "Verifikasi keamanan Cloudflare Turnstile gagal! Silakan coba lagi.";
            }
        }

        // Validasi input lainnya
        if (empty($username) || empty($password)) {
            $errors[] = "Username dan password wajib diisi!";
        }
        if (strlen($username) < 3) {
            $errors[] = "Username minimal harus memiliki 3 karakter.";
        }
        if (strlen($password) < 6) {
            $errors[] = "Password minimal harus memiliki 6 karakter.";
        }
        if ($height === false || $height < 50 || $height > 250) {
            $errors[] = "Tinggi badan harus berkisar antara 50 - 250 cm.";
        }
        if ($weight === false || $weight < 20 || $weight > 300) {
            $errors[] = "Berat badan harus berkisar antara 20 - 300 kg.";
        }
        if ($age === false || $age < 10 || $age > 100) {
            $errors[] = "Umur harus berkisar antara 10 - 100 tahun.";
        }
        if (!in_array($gender, ['male', 'female'])) {
            $errors[] = "Pilih gender Anda yang valid.";
        }

        // Cek duplikasi username jika tidak ada error validasi
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "Username '{$username}' sudah digunakan, silakan pilih yang lain.";
                } else {
                    // Hash password menggunakan secure BCRYPT
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    // Lakukan insert user baru berserta biodatanya
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, height, weight, age, gender) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $height, $weight, $age, $gender]);

                    $success = "Akun berhasil dibuat! Silakan masuk dengan akun baru Anda.";
                    $active_tab = 'login';
                    // Kosongkan form input register
                    $username = '';
                    $height = $weight = $age = '';
                }
            } catch (PDOException $e) {
                $errors[] = "Gagal membuat akun: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gym Tracker - Masuk / Daftar</title>

    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#111217">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="icon.png">
    <link rel="icon" type="image/png" href="icon.png">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <!-- Cloudflare Turnstile API Script -->
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <style>
        :root {
            --bg-color: #111217;
            --outer-bg: #08080a;
            --card-bg: #1a1c24;
            --accent-color: #baff29;
            --accent-hover: #a3e61a;
            --text-primary: #f8f9fa;
            --text-secondary: #9ea0a9;
        }

        body {
            background-color: var(--outer-bg);
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
        }

        /* Mobile Container Frame */
        .mobile-frame {
            width: 100%;
            max-width: 480px;
            background-color: var(--bg-color);
            min-height: 100vh;
            border-left: 1px solid rgba(255, 255, 255, 0.05);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.8);
            padding: 40px 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Brand Title */
        .brand-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .brand-logo {
            font-weight: 800;
            font-size: 2rem;
            color: var(--text-primary);
            text-decoration: none;
            letter-spacing: 1px;
        }

        .brand-logo span {
            color: var(--accent-color);
        }

        /* Form Glassmorphism Card */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.35);
            padding: 24px;
        }

        /* Neon Custom Tabs */
        .custom-tabs {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        }

        .custom-tabs .nav-link {
            background: none !important;
            border: none !important;
            color: var(--text-secondary) !important;
            font-size: 0.95rem;
            font-weight: 600;
            padding: 10px 0;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease;
        }

        .custom-tabs .nav-link.active {
            color: var(--accent-color) !important;
            border-bottom: 2px solid var(--accent-color) !important;
        }

        /* Form Inputs */
        .form-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control-custom {
            background-color: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--text-primary) !important;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control-custom:focus {
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 8px rgba(186, 255, 41, 0.2) !important;
        }

        .form-select-custom {
            background-color: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--text-primary) !important;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-select-custom:focus {
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 8px rgba(186, 255, 41, 0.2) !important;
        }

        .form-select-custom option {
            background-color: #1a1c24;
            color: var(--text-primary);
        }

        .input-group-text-custom {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
            border-radius: 0 8px 8px 0;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .btn-custom {
            background-color: var(--accent-color);
            color: #000;
            font-weight: 700;
            border-radius: 8px;
            padding: 12px;
            border: none;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(186, 255, 41, 0.25);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-custom:active {
            transform: scale(0.98);
        }

        .form-control-custom::placeholder {
            color: rgba(255, 255, 255, 0.25) !important;
        }
    </style>
</head>

<body>

    <div class="mobile-frame">

        <!-- Brand Header -->
        <div class="brand-header">
            <h1 class="brand-logo">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i>GYM<span>TRACKER</span>
            </h1>
            <p class="text-secondary small mt-2">Bangun fisik impianmu dengan catatan latihan presisi</p>
        </div>

        <div class="glass-card">

            <!-- Success / Error Alert Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger border-0 py-2 small mb-3"
                    style="background-color: rgba(220, 53, 69, 0.15); color: #ea868f;">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success border-0 py-2 small mb-3"
                    style="background-color: rgba(25, 135, 84, 0.15); color: #75b798;">
                    <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs custom-tabs mb-4 text-center justify-content-center" role="tablist">
                <li class="nav-item w-50" role="presentation">
                    <button class="nav-link w-100 <?= $active_tab === 'login' ? 'active' : '' ?>" id="login-tab"
                        data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">Masuk</button>
                </li>
                <li class="nav-item w-50" role="presentation">
                    <button class="nav-link w-100 <?= $active_tab === 'register' ? 'active' : '' ?>" id="register-tab"
                        data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">Daftar
                        Akun</button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- PANEL LOGIN -->
                <div class="tab-pane fade <?= $active_tab === 'login' ? 'show active' : '' ?>" id="login-pane"
                    role="tabpanel">
                    <form action="login.php" method="POST">
                        <input type="hidden" name="action" value="login">

                        <div class="mb-3">
                            <label for="login-username" class="form-label">Username</label>
                            <input type="text" name="username" id="login-username"
                                class="form-control form-control-custom" placeholder="Masukkan username" required>
                        </div>

                        <div class="mb-4">
                            <label for="login-password" class="form-label">Password</label>
                            <input type="password" name="password" id="login-password"
                                class="form-control form-control-custom" placeholder="Masukkan password" required>
                        </div>

                        <button type="submit" class="btn btn-custom">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Masuk Ke Gym
                        </button>
                    </form>
                </div>

                <!-- PANEL REGISTRASI + BIODATA -->
                <div class="tab-pane fade <?= $active_tab === 'register' ? 'show active' : '' ?>" id="register-pane"
                    role="tabpanel">
                    <form action="login.php" method="POST">
                        <input type="hidden" name="action" value="register">

                        <h6 class="text-warning fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 0.5px;">1.
                            KREDENSIAL AKUN</h6>

                        <div class="mb-3">
                            <label for="reg-username" class="form-label">Username</label>
                            <input type="text" name="username" id="reg-username"
                                class="form-control form-control-custom" placeholder="Minimal 3 karakter" required>
                        </div>

                        <div class="mb-3">
                            <label for="reg-password" class="form-label">Password</label>
                            <input type="password" name="password" id="reg-password"
                                class="form-control form-control-custom" placeholder="Minimal 6 karakter" required>
                        </div>

                        <h6 class="text-warning fw-bold mt-4 mb-3" style="font-size: 0.8rem; letter-spacing: 0.5px;">2.
                            BIODATA FISIK (BMI & KALORI)</h6>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label for="reg-height" class="form-label">Tinggi Badan</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="height" id="reg-height"
                                        class="form-control form-control-custom" placeholder="Tinggi" required>
                                    <span class="input-group-text input-group-text-custom">Cm</span>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="reg-weight" class="form-label">Berat Badan</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="weight" id="reg-weight"
                                        class="form-control form-control-custom" placeholder="Berat" required>
                                    <span class="input-group-text input-group-text-custom">Kg</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label for="reg-age" class="form-label">Umur</label>
                                <input type="number" name="age" id="reg-age" class="form-control form-control-custom"
                                    placeholder="Tahun" required>
                            </div>
                            <div class="col-6">
                                <label for="reg-gender" class="form-label">Gender</label>
                                <select name="gender" id="reg-gender" class="form-select form-select-custom" required>
                                    <option value="" disabled selected>Pilih</option>
                                    <option value="male">Pria</option>
                                    <option value="female">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <!-- Cloudflare Turnstile Widget (Dynamic Environment keys) -->
                        <div class="mb-4 d-flex justify-content-center">
                            <div class="cf-turnstile" data-sitekey="<?= $turnstile_sitekey ?>" data-theme="dark"></div>
                        </div>

                        <button type="submit" class="btn btn-custom">
                            <i class="bi bi-person-plus-fill me-2"></i>Daftar & Hitung BMI
                        </button>
                    </form>
                </div>
            </div>

        </div>

    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>