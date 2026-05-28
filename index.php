<?php
// index.php - Dashboard & Riwayat Latihan (Mobile Only)
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => false,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// Proteksi Halaman: Jika belum login, tendang ke login.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
require_once 'db.php';

$message = '';

// Proses Simpan Profil Biodata (MySQL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $weight = filter_var($_POST['weight'], FILTER_VALIDATE_FLOAT);
    $height = filter_var($_POST['height'], FILTER_VALIDATE_FLOAT);
    $age = filter_var($_POST['age'], FILTER_VALIDATE_INT);
    $gender = trim($_POST['gender'] ?? '');

    if ($weight && $height && $age && in_array($gender, ['male', 'female'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET weight = ?, height = ?, age = ?, gender = ? WHERE id = ?");
            $stmt->execute([$weight, $height, $age, $gender, $user_id]);
            $message = "<div class='alert alert-success alert-dismissible fade show border-0 shadow-sm mx-3 mt-3 py-2' role='alert' style='background-color: rgba(25, 135, 84, 0.2); color: #20c997; border: 1px solid rgba(32, 201, 151, 0.2) !important; font-size: 0.85rem;'>
                            <i class='bi bi-check-circle-fill me-1'></i> Profil berhasil disimpan di database!
                            <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm mx-3 mt-3 py-2' role='alert' style='font-size: 0.85rem;'>
                            <i class='bi bi-exclamation-triangle-fill me-1'></i> Gagal menyimpan profil: " . htmlspecialchars($e->getMessage()) . "
                            <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        }
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm mx-3 mt-3 py-2' role='alert' style='font-size: 0.85rem;'>
                        <i class='bi bi-exclamation-triangle-fill me-1'></i> Biodata input tidak valid!
                        <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
                    </div>";
    }
}

// Helper untuk menghitung estimasi kalori terbakar
if (!function_exists('get_met')) {
    function get_met($exercise_name) {
        $met = 4.0;
        $lower = strtolower($exercise_name);
        if (strpos($lower, 'bench press') !== false || strpos($lower, 'squat') !== false || strpos($lower, 'deadlift') !== false) {
            $met = 6.0;
        } else if (strpos($lower, 'pulldown') !== false || strpos($lower, 'curl') !== false || strpos($lower, 'row') !== false || strpos($lower, 'pushdown') !== false || strpos($lower, 'extension') !== false) {
            $met = 4.5;
        }
        return $met;
    }
}

if (!function_exists('calculate_calories')) {
    function calculate_calories($exercise_name, $sets, $reps, $weight_kg) {
        $met = get_met($exercise_name);
        $is_timed = false;
        $timed_keywords = ['hang', 'plank', 'hold', 'sit', 'detik', 'second'];
        foreach ($timed_keywords as $keyword) {
            if (stripos($exercise_name, $keyword) !== false) {
                $is_timed = true;
                break;
            }
        }
        
        if ($is_timed) {
            $duration_sec = $sets * $reps;
        } else {
            $duration_sec = $sets * $reps * 3; // Estimasi 3 detik per rep
        }
        
        $duration_min = $duration_sec / 60;
        return $met * 0.0175 * $weight_kg * $duration_min;
    }
}

// Proses Hapus Log Latihan individual jika ada request
if (isset($_GET['delete_id'])) {
    $delete_id = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            // Pastikan log yang dihapus adalah milik user terlogin (keamanan tingkat tinggi)
            $stmt = $pdo->prepare("DELETE FROM workout_logs WHERE id = ? AND user_id = ?");
            $stmt->execute([$delete_id, $user_id]);
            $message = "<div class='alert alert-success alert-dismissible fade show border-0 shadow-sm mx-3 mt-3 py-2' role='alert' style='background-color: rgba(25, 135, 84, 0.2); color: #20c997; border: 1px solid rgba(32, 201, 151, 0.2) !important; font-size: 0.85rem;'>
                            <i class='bi bi-check-circle-fill me-1'></i> Log berhasil dihapus!
                            <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm mx-3 mt-3 py-2' role='alert' style='font-size: 0.85rem;'>
                            <i class='bi bi-exclamation-triangle-fill me-1'></i> Gagal menghapus: " . htmlspecialchars($e->getMessage()) . "
                            <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        }
    }
}

// Proses Hapus Seluruh Sesi Latihan jika ada request
if (isset($_GET['delete_session'])) {
    $delete_session = trim($_GET['delete_session']);
    if (!empty($delete_session)) {
        try {
            // Pastikan log yang dihapus adalah milik user terlogin (keamanan tingkat tinggi)
            $stmt = $pdo->prepare("DELETE FROM workout_logs WHERE created_at = ? AND user_id = ?");
            $stmt->execute([$delete_session, $user_id]);
            $message = "<div class='alert alert-success alert-dismissible fade show border-0 shadow-sm mx-3 mt-3 py-2' role='alert' style='background-color: rgba(25, 135, 84, 0.2); color: #20c997; border: 1px solid rgba(32, 201, 151, 0.2) !important; font-size: 0.85rem;'>
                            <i class='bi bi-check-circle-fill me-1'></i> Sesi latihan berhasil dihapus!
                            <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger alert-dismissible fade show border-0 shadow-sm mx-3 mt-3 py-2' role='alert' style='font-size: 0.85rem;'>
                            <i class='bi bi-exclamation-triangle-fill me-1'></i> Gagal menghapus sesi: " . htmlspecialchars($e->getMessage()) . "
                            <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
                        </div>";
        }
    }
}

// Proses Sukses Menambahkan Log
if (isset($_GET['success'])) {
    $message = "<div class='alert alert-success alert-dismissible fade show border-0 shadow-sm mx-3 mt-3 py-2' role='alert' style='background-color: rgba(186, 255, 41, 0.15); color: var(--accent-color); border: 1px solid rgba(186, 255, 41, 0.3) !important; font-size: 0.85rem;'>
                    <i class='bi bi-fire me-1 text-warning'></i> <strong>Latihan dicatat!</strong> Teruskan perjuanganmu!
                    <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
                </div>";
}

// Ambil data biodata user terautentikasi
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();
    
    $user_weight = $current_user['weight'] ?? 70;
    $user_height = $current_user['height'] ?? 170;
    $user_age = $current_user['age'] ?? 25;
    $user_gender = $current_user['gender'] ?? 'male';
    $username_display = $current_user['username'] ?? 'User';
} catch (PDOException $e) {
    $user_weight = 70;
    $user_height = 170;
    $user_age = 25;
    $user_gender = 'male';
    $username_display = 'User';
}

// Mengambil Data Statistik Ringkas berbasis User
try {
    // Sesi dihitung berdasarkan jumlah kelompok timestamp unik
    $stmt_tot_w = $pdo->prepare("SELECT COUNT(DISTINCT created_at) FROM workout_logs WHERE user_id = ?");
    $stmt_tot_w->execute([$user_id]);
    $total_workouts = $stmt_tot_w->fetchColumn();

    $total_exercises = $pdo->query("SELECT COUNT(*) FROM exercises")->fetchColumn();

    $stmt_lat_a = $pdo->prepare("SELECT created_at FROM workout_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt_lat_a->execute([$user_id]);
    $latest_activity = $stmt_lat_a->fetchColumn();
    $latest_activity_formatted = $latest_activity ? date('d M, H:i', strtotime($latest_activity)) : 'Belum ada';
} catch (PDOException $e) {
    $total_workouts = 0;
    $total_exercises = 0;
    $latest_activity_formatted = 'Error';
}

// Mengambil Riwayat Latihan (History Log) berbasis User
try {
    $stmt = $pdo->prepare("
        SELECT wl.id, wl.sets, wl.reps, wl.weight, wl.created_at, 
               e.id AS exercise_id, e.name AS exercise_name, e.target_muscle 
        FROM workout_logs wl
        JOIN exercises e ON wl.exercise_id = e.id
        WHERE wl.user_id = ?
        ORDER BY wl.created_at DESC, wl.id ASC
    ");
    $stmt->execute([$user_id]);
    $logs = $stmt->fetchAll();

    // Mengelompokkan log ke dalam sesi latihan utuh (Session-based grouping)
    $sessions = [];
    foreach ($logs as $log) {
        $timestamp = $log['created_at'];
        if (!isset($sessions[$timestamp])) {
            $sessions[$timestamp] = [
                'created_at' => $timestamp,
                'exercises' => []
            ];
        }

        $ex_id = $log['exercise_id'];
        if (!isset($sessions[$timestamp]['exercises'][$ex_id])) {
            $sessions[$timestamp]['exercises'][$ex_id] = [
                'name' => $log['exercise_name'],
                'target_muscle' => $log['target_muscle'],
                'sets' => []
            ];
        }

        $sessions[$timestamp]['exercises'][$ex_id]['sets'][] = [
            'id' => $log['id'],
            'sets_count' => $log['sets'],
            'reps' => $log['reps'],
            'weight' => $log['weight']
        ];
    }
} catch (PDOException $e) {
    $logs = [];
    $sessions = [];
    $message .= "<div class='alert alert-danger border-0 shadow-sm mx-3 mt-3'>Gagal memuat riwayat: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gym Tracker</title>
    
    <!-- PWA Meta Tags & Manifest -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#111217">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="icon.png">
    <link rel="icon" type="image/png" href="icon.png">

    <!-- Bootstrap 5 CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #111217;      /* Gelap mobile frame */
            --outer-bg: #08080a;      /* Hitam pekat desktop background */
            --card-bg: #1a1c24;       /* Card background */
            --accent-color: #baff29;  /* Neon lime */
            --accent-hover: #a3e61a;
            --text-primary: #f8f9fa;
            --text-secondary: #9ea0a9;
            --border-glow: rgba(186, 255, 41, 0.15);
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
            overflow-x: hidden;
        }

        /* Centered Mobile-Only Frame */
        .mobile-frame {
            width: 100%;
            max-width: 480px;
            background-color: var(--bg-color);
            min-height: 100vh;
            border-left: 1px solid rgba(255, 255, 255, 0.05);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.8);
            position: relative;
            padding-bottom: 90px; /* Space for bottom nav */
            display: flex;
            flex-direction: column;
        }

        /* Top Header Bar */
        .header-bar {
            height: 60px;
            background-color: rgba(26, 28, 36, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            sticky-top: true;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .header-logo {
            font-weight: 800;
            font-size: 1.25rem;
            color: var(--text-primary);
            text-decoration: none;
            letter-spacing: 0.5px;
        }

        .header-logo span {
            color: var(--accent-color);
        }

        .header-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            font-weight: bold;
            font-size: 0.85rem;
            box-shadow: 0 0 10px rgba(186, 255, 41, 0.2);
        }

        /* Cards & Styling */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            transition: all 0.2s ease;
        }

        .glass-card:active {
            transform: scale(0.98);
        }

        /* Stat Panel */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-icon {
            font-size: 1.6rem;
            color: var(--accent-color);
            background: rgba(186, 255, 41, 0.08);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        /* Bottom Sticky Tab Navigation (Instagram Style) */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 480px;
            height: 70px;
            background: rgba(26, 28, 36, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            padding: 0 15px;
        }

        .bottom-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none !important;
            color: var(--text-secondary);
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.2s ease;
            width: 25%;
        }

        .bottom-nav-item i {
            font-size: 1.25rem;
            margin-bottom: 2px;
        }

        .bottom-nav-item.active {
            color: var(--accent-color);
        }

        /* Elevated Center FAB */
        .fab-container {
            width: 20%;
            display: flex;
            justify-content: center;
            position: relative;
        }

        .fab-item {
            position: absolute;
            top: -32px;
            width: 56px;
            height: 56px;
            background-color: var(--accent-color);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 15px rgba(186, 255, 41, 0.4);
            transition: all 0.2s ease;
            color: #000 !important;
        }

        .fab-item i {
            font-size: 1.6rem;
            margin: 0;
        }

        .fab-item:active {
            transform: scale(0.9) translateY(4px);
        }

        /* History Items (List-style instead of heavy tables) */
        .log-item {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 12px 0;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .badge-target {
            background-color: rgba(186, 255, 41, 0.1);
            color: var(--accent-color);
            border: 1px solid rgba(186, 255, 41, 0.2);
            font-weight: 600;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 0.75rem;
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.08);
            margin-bottom: 15px;
            display: block;
        }

        /* Custom premium glassmorphism modal overlay */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(8, 8, 10, 0.85);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1050;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .custom-modal-content {
            width: 100%;
            max-width: 440px;
            max-height: 90vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch; /* Smooth iOS inertial scrolling */
            border: 1px solid rgba(186, 255, 41, 0.15) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 15px rgba(186, 255, 41, 0.05) !important;
            padding: 22px;
            animation: modalSlideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalSlideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Custom Modal Tabs styling to look futuristic and neon */
        .custom-tabs {
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        
        .custom-tabs .nav-link {
            background: none !important;
            border: none !important;
            color: var(--text-secondary) !important;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 10px 0;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease;
        }

        .custom-tabs .nav-link.active {
            color: var(--accent-color) !important;
            border-bottom: 2px solid var(--accent-color) !important;
        }

        .form-control-custom::placeholder {
            color: rgba(255, 255, 255, 0.25) !important;
        }
    </style>
</head>
<body>

    <!-- MOBILE-ONLY CONTAINER -->
    <div class="mobile-frame">
        
        <!-- HEADER TOP BAR -->
        <header class="header-bar">
            <a href="index.php" class="header-logo">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i>GYM<span>TRACKER</span>
            </a>
            <div class="header-avatar" id="avatarBtn" title="User Profile & Kalkulator" style="cursor: pointer;">
                U
            </div>
        </header>

        <!-- Dynamic Success/Error Alerts -->
        <?php if (!empty($message)) echo $message; ?>

        <!-- SCROLLABLE CONTENT BODY -->
        <div class="px-3 py-4 flex-grow-1">
            
            <!-- Welcome Header -->
            <div class="mb-4">
                <h4 class="fw-bold mb-1">Halo, Selamat Berlatih! 👋</h4>
                <p class="text-secondary small mb-0">Disiplin adalah kunci sukses pembentukan tubuh.</p>
            </div>

            <!-- STATISTICS CARDS GRID -->
            <div class="row g-2 mb-4">
                <!-- Stat 1: Total Sesi -->
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-calendar2-week"></i>
                        </div>
                        <div>
                            <span class="text-secondary d-block" style="font-size: 0.65rem; text-transform: uppercase;">Total Latihan</span>
                            <span class="fw-bold fs-5 text-white"><?= $total_workouts ?> <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-secondary)">x</span></span>
                        </div>
                    </div>
                </div>
                <!-- Stat 2: Total Exercises -->
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-database"></i>
                        </div>
                        <div>
                            <span class="text-secondary d-block" style="font-size: 0.65rem; text-transform: uppercase;">Gerakan</span>
                            <span class="fw-bold fs-5 text-white"><?= $total_exercises ?> <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-secondary)">Pilar</span></span>
                        </div>
                    </div>
                </div>
                <!-- Stat 3: Total Calories (New!) -->
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(255, 87, 34, 0.08); color: #ff5722;">
                            <i class="bi bi-fire"></i>
                        </div>
                        <div>
                            <span class="text-secondary d-block" style="font-size: 0.65rem; text-transform: uppercase;">Estimasi Kalori</span>
                            <span class="fw-bold fs-5 text-white" id="total-calories-card">0 <span style="font-size: 0.75rem; font-weight: normal; color: var(--text-secondary)">kcal</span></span>
                        </div>
                    </div>
                </div>
                <!-- Stat 4: Last Workout -->
                <div class="col-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <span class="text-secondary d-block" style="font-size: 0.65rem; text-transform: uppercase;">Terakhir</span>
                            <span class="fw-bold d-block text-white" style="font-size: 0.85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px;" id="last-activity-card"><?= $latest_activity_formatted ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- DYNAMIC HISTORY SECTION (Grouped as Session Cards like Hevy) -->
            <div class="mb-3 d-flex justify-content-between align-items-center mt-4">
                <span class="fw-bold text-white fs-6"><i class="bi bi-journal-text text-warning me-2"></i>Riwayat Sesi Latihan</span>
                <span class="badge bg-secondary border border-secondary border-opacity-25" style="font-size: 0.7rem;"><?= count($sessions) ?> Sesi</span>
            </div>

            <?php if (empty($sessions)): ?>
                <!-- Empty State -->
                <div class="glass-card p-4 text-center empty-state">
                    <i class="bi bi-journals fs-1 text-secondary opacity-25 mb-3 d-block"></i>
                    <h6 class="fw-bold text-white mb-1">Belum ada riwayat latihan</h6>
                    <p class="text-secondary small mb-0">Tekan tombol "+" di bawah untuk memulai sesi latihan baru pertama kamu!</p>
                </div>
            <?php else: ?>
                <!-- Session Cards List -->
                <div class="session-cards-container d-flex flex-column gap-3">
                    <?php foreach ($sessions as $timestamp => $session): ?>
                        <div class="glass-card p-3" style="border: 1px solid rgba(255, 255, 255, 0.05); transition: none;">
                            
                            <!-- Session Card Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                                <div>
                                    <h6 class="fw-bold text-white mb-0" style="font-size: 0.9rem; letter-spacing: 0.3px;">
                                        <i class="bi bi-calendar2-check-fill text-warning me-1.5"></i>Sesi Latihan Aktif
                                    </h6>
                                    <span class="text-secondary" style="font-size: 0.7rem;">
                                        <i class="bi bi-clock me-1 text-success"></i><?= date('l, d M Y - H:i', strtotime($timestamp)) ?>
                                    </span>
                                </div>
                                <a href="index.php?delete_session=<?= urlencode($timestamp) ?>" 
                                   class="btn btn-sm btn-outline-danger py-0.5 px-2 border-danger text-danger d-flex align-items-center gap-1" 
                                   onclick="return confirm('Hapus seluruh sesi latihan ini? Tindakan ini tidak dapat dibatalkan.')" 
                                   style="font-size: 0.68rem; font-weight: 600; border-radius: 6px; text-decoration: none;">
                                    <i class="bi bi-trash3" style="font-size: 0.65rem;"></i> Hapus Sesi
                                </a>
                            </div>

                            <!-- Exercises List in this Session -->
                            <div class="d-flex flex-column gap-3">
                                <?php 
                                $session_kcal = 0;
                                foreach ($session['exercises'] as $ex_id => $ex): 
                                ?>
                                    <div>
                                        <!-- Exercise Name & Target Muscle Badge -->
                                        <div class="d-flex align-items-center gap-2 mb-1.5">
                                            <span class="fw-bold text-white" style="font-size: 0.85rem;"><?= htmlspecialchars($ex['name']) ?></span>
                                            <span class="badge-target" style="font-size: 0.65rem; padding: 1px 6px; border-radius: 4px;"><?= htmlspecialchars($ex['target_muscle']) ?></span>
                                        </div>
                                        
                                        <!-- Sets Detail Table (Compact & Premium) -->
                                        <div class="ps-2 mt-1 border-start border-warning border-opacity-25 d-flex flex-column gap-1.5" style="font-size: 0.78rem;">
                                            <?php 
                                            $set_num = 1;
                                            foreach ($ex['sets'] as $set): 
                                                $weight_text = ($set['weight'] == 0) ? 'Bodyweight' : number_format($set['weight'], 1) . ' kg';
                                                
                                                $is_timed = false;
                                                $timed_keywords = ['hang', 'plank', 'hold', 'sit', 'detik', 'second'];
                                                foreach ($timed_keywords as $keyword) {
                                                    if (stripos($ex['name'], $keyword) !== false) {
                                                        $is_timed = true;
                                                        break;
                                                    }
                                                }
                                                $reps_unit = $is_timed ? 'Detik' : 'Rep';
                                                
                                                // Calculate calorie burn for this set
                                                $set_kcal = calculate_calories($ex['name'], $set['sets_count'], $set['reps'], $user_weight);
                                                $session_kcal += $set_kcal;
                                            ?>
                                                <div class="text-secondary d-flex justify-content-between align-items-center">
                                                    <span>
                                                        <span class="text-warning fw-semibold" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.2px;">Set <?= $set_num ?></span>: 
                                                        <strong><?= $set['sets_count'] ?>x</strong> (<?= $weight_text ?> x <?= $set['reps'] ?> <?= $reps_unit ?>)
                                                    </span>
                                                    <span class="small" style="color: #ff5722 !important; font-size: 0.7rem; font-weight: 500;">
                                                        <i class="bi bi-fire me-0.5"></i><?= round($set_kcal, 1) ?> kcal
                                                    </span>
                                                </div>
                                            <?php 
                                                $set_num++;
                                            endforeach; 
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Session Footer (Total Duration, Exercises & Calories) -->
                            <div class="mt-3 pt-2 border-top border-secondary border-opacity-10 d-flex justify-content-between align-items-center">
                                <span class="text-secondary" style="font-size: 0.7rem;">
                                    Total: <strong class="text-white"><?= count($session['exercises']) ?> Gerakan</strong>
                                </span>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 py-1 px-2 d-flex align-items-center gap-1" style="font-size: 0.72rem; color: #ff5722 !important; background: rgba(255, 87, 34, 0.08) !important;">
                                    <i class="bi bi-fire text-warning"></i> +<?= round($session_kcal, 0) ?> kcal
                                </span>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- STICKY BOTTOM NAVIGATION BAR -->
        <nav class="bottom-nav">
            <a href="index.php" class="bottom-nav-item active">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard</span>
            </a>
            
            <!-- Central Elevated Floating Action Button (FAB) -->
            <div class="fab-container">
                <a href="track.php" class="fab-item" title="Catat Latihan Baru">
                    <i class="bi bi-plus-lg"></i>
                </a>
            </div>
            
            <a href="exercises.php" class="bottom-nav-item">
                <i class="bi bi-book"></i>
                <span>Library</span>
            </a>
        </nav>

    </div>

    <!-- Bootstrap 5 Bundle JS with Popper via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('Service Worker Terdaftar!', reg))
                    .catch(err => console.error('Pendaftaran Service Worker Gagal!', err));
            });
        }
    </script>

    <!-- PROFILE & FITNESS CALCULATOR MODAL -->
    <div id="fitnessModal" class="custom-modal-overlay d-none">
        <div class="custom-modal-content glass-card">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                <h5 class="fw-bold text-white mb-0"><i class="bi bi-person-circle text-warning me-2"></i>Profil & Kalkulator</h5>
                <button type="button" class="btn-close btn-close-white" id="closeModalBtn"></button>
            </div>
            
            <!-- Modal Tabs -->
            <ul class="nav nav-tabs custom-tabs mb-3" id="modalTabs" role="tablist">
                <li class="nav-item w-50" role="presentation">
                    <button class="nav-link w-100 active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-pane" type="button" role="tab">Profil & Kalori</button>
                </li>
                <li class="nav-item w-50" role="presentation">
                    <button class="nav-link w-100" id="calculator-tab" data-bs-toggle="tab" data-bs-target="#calculator-pane" type="button" role="tab">Kalkulator BMI</button>
                </li>
            </ul>
            
            <div class="tab-content" id="modalTabContent">
                <!-- PROFILE TAB (SYNCED TO MYSQL) -->
                <div class="tab-pane fade show active" id="profile-pane" role="tabpanel">
                    <form id="profileForm" action="index.php" method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-2 text-warning small fw-bold">Halo, <?= htmlspecialchars($username_display) ?>! 👋</div>
                        
                        <div class="mb-3">
                            <label class="form-label">Berat Badan (Kg)</label>
                            <input type="number" step="0.1" name="weight" id="user-weight" class="form-control form-control-custom" placeholder="Contoh: 70" value="<?= htmlspecialchars($user_weight) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tinggi Badan (Cm)</label>
                            <input type="number" step="0.1" name="height" id="user-height" class="form-control form-control-custom" placeholder="Contoh: 170" value="<?= htmlspecialchars($user_height) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Umur (Tahun)</label>
                            <input type="number" name="age" id="user-age" class="form-control form-control-custom" placeholder="Contoh: 25" value="<?= htmlspecialchars($user_age) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gender</label>
                            <select name="gender" id="user-gender" class="form-select form-select-custom" required>
                                <option value="male" <?= $user_gender === 'male' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="female" <?= $user_gender === 'female' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-custom mt-2">
                            <i class="bi bi-save-fill me-2"></i>Simpan Profil
                        </button>
                        
                        <!-- Secure Logout Button inside profile tab -->
                        <a href="logout.php" class="btn btn-outline-danger w-100 mt-3 small py-2 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.8rem; border-radius: 8px; font-weight: 600; text-decoration: none;">
                            <i class="bi bi-box-arrow-left"></i>Keluar dari Akun
                        </a>
                    </form>
                </div>
                
                <!-- CALCULATOR TAB -->
                <div class="tab-pane fade" id="calculator-pane" role="tabpanel">
                    <div class="mb-3">
                        <label class="form-label">Tinggi Badan (Cm)</label>
                        <input type="number" id="calc-height" class="form-control form-control-custom" placeholder="Contoh: 170">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Berat Badan (Kg)</label>
                        <input type="number" id="calc-weight" class="form-control form-control-custom" placeholder="Contoh: 65">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Umur (Tahun)</label>
                        <input type="number" id="calc-age" class="form-control form-control-custom" placeholder="Contoh: 25">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Gender</label>
                        <select id="calc-gender" class="form-select form-select-custom">
                            <option value="male">Laki-laki</option>
                            <option value="female">Perempuan</option>
                        </select>
                    </div>
                    <button type="button" id="btnCalculateBmi" class="btn btn-custom mt-2">
                        <i class="bi bi-calculator me-2"></i>Hitung BMI & Fat %
                    </button>
                    
                    <!-- CALCULATOR RESULTS VIEW -->
                    <div id="bmi-result-card" class="mt-4 p-3 rounded bg-black bg-opacity-40 border border-secondary border-opacity-25 d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-secondary small fw-bold">HASIL KALKULASI</span>
                            <span id="bmi-status-badge" class="badge bg-success">Normal</span>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-2 rounded bg-dark bg-opacity-50 text-center">
                                    <span class="text-secondary d-block" style="font-size: 0.65rem; text-transform: uppercase;">Body Mass Index (BMI)</span>
                                    <h4 class="fw-bold text-white mb-0" id="bmi-value-display">22.5</h4>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 rounded bg-dark bg-opacity-50 text-center">
                                    <span class="text-secondary d-block" style="font-size: 0.65rem; text-transform: uppercase;">Estimasi Lemak (Fat)</span>
                                    <h4 class="fw-bold text-warning mb-0" id="fat-value-display">15.4%</h4>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Visual indicator bar for BMI -->
                        <span class="text-secondary d-block mb-1" style="font-size: 0.65rem; text-transform: uppercase;">Indikator BMI</span>
                        <div class="progress mb-2" style="height: 6px; background-color: rgba(255,255,255,0.05); overflow: visible; position: relative;">
                            <div style="position: absolute; left: 0; width: 37%; height: 100%; background: #0dcaf0; border-radius: 3px 0 0 3px;"></div> <!-- < 18.5 -->
                            <div style="position: absolute; left: 37%; width: 23%; height: 100%; background: #baff29;"></div> <!-- 18.5 - 24.9 -->
                            <div style="position: absolute; left: 60%; width: 20%; height: 100%; background: #ffc107;"></div> <!-- 25 - 29.9 -->
                            <div style="position: absolute; left: 80%; width: 20%; height: 100%; background: #dc3545; border-radius: 0 3px 3px 0;"></div> <!-- >= 30 -->
                            
                            <div id="bmi-indicator-pointer" style="position: absolute; top: -4px; width: 14px; height: 14px; border-radius: 50%; background: #fff; border: 3px solid #000; box-shadow: 0 0 5px rgba(255,255,255,0.8); transition: left 0.5s ease;"></div>
                        </div>
                        <div class="d-flex justify-content-between text-secondary" style="font-size: 0.55rem;">
                            <span>Kurus (&lt;18.5)</span>
                            <span>Normal</span>
                            <span>Gemuk (25-30)</span>
                            <span>Obesitas (&gt;30)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN INTERACTIVE JS FOR CALORIES, MODAL & BMI -->
    <script>
        // Data tersinkronisasi server dari PHP session/database
        const DB_USER_WEIGHT = <?= (float)$user_weight ?>;
        const DB_USER_HEIGHT = <?= (float)$user_height ?>;
        const DB_USER_AGE = <?= (int)$user_age ?>;
        const DB_USER_GENDER = "<?= htmlspecialchars($user_gender) ?>";

        // --- 1. INTERAKSI MODAL PROFIL & KALKULATOR ---
        const modal = document.getElementById('fitnessModal');
        const avatarBtn = document.getElementById('avatarBtn');
        const closeModalBtn = document.getElementById('closeModalBtn');
        
        avatarBtn.addEventListener('click', () => {
            modal.classList.remove('d-none');
            
            // Pra-isi tab kalkulator BMI dengan biodata profil database
            if (!document.getElementById('calc-height').value) {
                document.getElementById('calc-height').value = DB_USER_HEIGHT;
            }
            if (!document.getElementById('calc-weight').value) {
                document.getElementById('calc-weight').value = DB_USER_WEIGHT;
            }
            if (!document.getElementById('calc-age').value) {
                document.getElementById('calc-age').value = DB_USER_AGE;
            }
            if (!document.getElementById('calc-gender').value) {
                document.getElementById('calc-gender').value = DB_USER_GENDER;
            }
        });

        closeModalBtn.addEventListener('click', () => {
            modal.classList.add('d-none');
        });

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.add('d-none');
            }
        });

        // --- 2. LOGIKA KALKULATOR BMI & FAT % ---
        const btnCalculateBmi = document.getElementById('btnCalculateBmi');
        const calcHeightInput = document.getElementById('calc-height');
        const calcWeightInput = document.getElementById('calc-weight');
        const calcAgeInput = document.getElementById('calc-age');
        const calcGenderInput = document.getElementById('calc-gender');
        
        const bmiResultCard = document.getElementById('bmi-result-card');
        const bmiValueDisplay = document.getElementById('bmi-value-display');
        const fatValueDisplay = document.getElementById('fat-value-display');
        const bmiStatusBadge = document.getElementById('bmi-status-badge');
        const bmiIndicatorPointer = document.getElementById('bmi-indicator-pointer');

        btnCalculateBmi.addEventListener('click', () => {
            const heightCm = parseFloat(calcHeightInput.value);
            const weightKg = parseFloat(calcWeightInput.value);
            const ageYears = parseInt(calcAgeInput.value) || 25;
            const gender = calcGenderInput.value;

            if (!heightCm || !weightKg || heightCm <= 0 || weightKg <= 0) {
                alert('Silakan masukkan Tinggi dan Berat Badan yang valid.');
                return;
            }

            const heightM = heightCm / 100;
            const bmi = weightKg / (heightM * heightM);

            // Rumus estimasi Lemak Tubuh (Body Fat %) berbasis BMI
            const genderFactor = (gender === 'male') ? 1 : 0;
            const bodyFat = (1.20 * bmi) + (0.23 * ageYears) - (10.8 * genderFactor) - 5.4;

            // Klasifikasi BMI & Pointer Posisi
            let status = 'Normal';
            let badgeClass = 'bg-success';
            let pointerPosition = 50;

            if (bmi < 18.5) {
                status = 'Kurus';
                badgeClass = 'bg-info text-dark';
                pointerPosition = (bmi / 18.5) * 37;
                if (pointerPosition < 3) pointerPosition = 3;
            } else if (bmi >= 18.5 && bmi < 25) {
                status = 'Normal';
                badgeClass = 'bg-success';
                pointerPosition = 37 + ((bmi - 18.5) / 6.5) * 23;
            } else if (bmi >= 25 && bmi < 30) {
                status = 'Gemuk';
                badgeClass = 'bg-warning text-dark';
                pointerPosition = 60 + ((bmi - 25) / 5) * 20;
            } else {
                status = 'Obesitas';
                badgeClass = 'bg-danger';
                pointerPosition = 80 + ((bmi - 30) / 10) * 17;
                if (pointerPosition > 97) pointerPosition = 97;
            }

            // Tampilkan hasil
            bmiValueDisplay.textContent = bmi.toFixed(1);
            fatValueDisplay.textContent = `${Math.max(2, bodyFat).toFixed(1)}%`;
            bmiStatusBadge.textContent = status;
            bmiStatusBadge.className = `badge ${badgeClass}`;
            bmiIndicatorPointer.style.left = `calc(${pointerPosition}% - 7px)`;

            bmiResultCard.classList.remove('d-none');
        });
    </script>
</body>
</html>
