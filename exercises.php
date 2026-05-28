<?php
// exercises.php - Panduan Alat & Library Gerakan Gym (Mobile Only)
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

$success_message = '';
$error_message = '';

// 1. Logika HAPUS/RESET gerakan jika ada request reset
if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    try {
        $pdo->query("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->query("TRUNCATE TABLE exercises");
        $pdo->query("TRUNCATE TABLE workout_logs"); // Bersihkan logs juga agar FK aman
        $pdo->query("SET FOREIGN_KEY_CHECKS = 1");
        
        $seeder = [
            [
                'name' => 'Bench Press',
                'target_muscle' => 'Chest (Dada)',
                'image_url' => 'https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?q=80&w=600&auto=format&fit=crop',
                'youtube_id' => 'rT7DgIZt51Y'
            ],
            [
                'name' => 'Bicep Curl',
                'target_muscle' => 'Biceps (Lengan)',
                'image_url' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?q=80&w=600&auto=format&fit=crop',
                'youtube_id' => 'ykJmrZ5v0ws'
            ],
            [
                'name' => 'Squat',
                'target_muscle' => 'Legs (Kaki)',
                'image_url' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?q=80&w=600&auto=format&fit=crop',
                'youtube_id' => 'UXJrBgI2RxA'
            ],
            [
                'name' => 'Lat Pulldown',
                'target_muscle' => 'Back (Punggung)',
                'image_url' => 'https://images.unsplash.com/photo-1605296867304-46d5465a25f1?q=80&w=600&auto=format&fit=crop',
                'youtube_id' => 'CAwf7n6Luuc'
            ],
            [
                'name' => 'Dead Hang',
                'target_muscle' => 'Forearms (Lengan Bawah)',
                'image_url' => 'https://images.unsplash.com/photo-1526506118085-60ce8714f8c5?q=80&w=600&auto=format&fit=crop',
                'youtube_id' => 'RQQ85l_S0eU'
            ]
        ];

        $stmt = $pdo->prepare("INSERT INTO exercises (name, target_muscle, image_url, youtube_id) VALUES (?, ?, ?, ?)");
        foreach ($seeder as $ex) {
            $stmt->execute([$ex['name'], $ex['target_muscle'], $ex['image_url'], $ex['youtube_id']]);
        }
        
        header("Location: exercises.php?msg=reset_success");
        exit;
    } catch (PDOException $e) {
        $error_message = "Gagal mereset gerakan: " . $e->getMessage();
    }
}

// 2. Logika TAMBAH gerakan baru (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_exercise'])) {
    $name = trim($_POST['name']);
    $target_muscle = trim($_POST['target_muscle']);
    $image_url = trim($_POST['image_url']);
    $youtube_id = ''; // Tidak diperlukan lagi untuk embed video

    if (!$name || !$target_muscle || !$image_url) {
        $error_message = "Semua kolom form (Nama, Otot Target, Link Gambar) harus diisi lengkap!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO exercises (name, target_muscle, image_url, youtube_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $target_muscle, $image_url, $youtube_id]);
            header("Location: exercises.php?msg=add_success");
            exit;
        } catch (PDOException $e) {
            $error_message = "Gagal menambahkan gerakan: " . $e->getMessage();
        }
    }
}

// 3. Logika EDIT gerakan (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_exercise'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    $name = trim($_POST['name']);
    $target_muscle = trim($_POST['target_muscle']);
    $image_url = trim($_POST['image_url']);
    
    if (!$id || !$name || !$target_muscle || !$image_url) {
        $error_message = "Semua kolom form edit harus diisi lengkap!";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE exercises SET name = ?, target_muscle = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$name, $target_muscle, $image_url, $id]);
            header("Location: exercises.php?msg=edit_success");
            exit;
        } catch (PDOException $e) {
            $error_message = "Gagal memperbarui gerakan: " . $e->getMessage();
        }
    }
}

// 4. Logika HAPUS gerakan (GET)
if (isset($_GET['delete_id'])) {
    $delete_id = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);
    if ($delete_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM exercises WHERE id = ?");
            $stmt->execute([$delete_id]);
            header("Location: exercises.php?msg=delete_success");
            exit;
        } catch (PDOException $e) {
            $error_message = "Gagal menghapus gerakan: " . $e->getMessage();
        }
    }
}

// 5. Handle Alert Message dari Query Param
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'reset_success') {
        $success_message = "Library berhasil direset ke 5 gerakan dasar!";
    } elseif ($_GET['msg'] === 'add_success') {
        $success_message = "Gerakan baru berhasil ditambahkan!";
    } elseif ($_GET['msg'] === 'edit_success') {
        $success_message = "Gerakan berhasil diperbarui!";
    } elseif ($_GET['msg'] === 'delete_success') {
        $success_message = "Gerakan berhasil dihapus!";
    }
}

// Ambil semua data gerakan terbaru dari database
try {
    $stmt = $pdo->query("SELECT * FROM exercises ORDER BY name ASC");
    $exercises = $stmt->fetchAll();
    
    // Ambil list otot target untuk filter tab
    $stmt_muscles = $pdo->query("SELECT DISTINCT target_muscle FROM exercises ORDER BY target_muscle ASC");
    $muscles = $stmt_muscles->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $exercises = [];
    $muscles = [];
    $error_message = "Gagal memuat data gerakan: " . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gym Tracker - Library</title>
    
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

        /* Filter Tab Scroll (Horizontal swipeable on mobile) */
        .filter-scroll {
            display: flex;
            overflow-x: auto;
            white-space: nowrap;
            gap: 8px;
            padding: 0 15px 12px 15px;
            scrollbar-width: none; /* Firefox */
        }

        .filter-scroll::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .filter-btn {
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-secondary);
            font-weight: 600;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.8rem;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .filter-btn:active, .filter-btn.active {
            background-color: var(--accent-color);
            color: #000;
            border-color: var(--accent-color);
            box-shadow: 0 2px 10px rgba(186, 255, 41, 0.25);
        }

        /* Exercise List Items */
        .exercise-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .card-img-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
            background-color: #0d0e12; /* Dark premium canvas background */
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 12px; /* Smooth padding so image doesn't touch the borders */
        }

        .card-img-top {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 8px; /* Slightly rounded edges for the image itself */
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .card-img-wrapper:hover .card-img-top {
            transform: scale(1.03); /* Soft premium micro-interaction on hover */
        }

        .badge-target {
            position: absolute;
            top: 12px;
            right: 12px;
            background-color: rgba(186, 255, 41, 0.15);
            color: var(--accent-color);
            border: 1px solid rgba(186, 255, 41, 0.3);
            font-weight: 700;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 0.72rem;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.35);
            z-index: 10;
        }

        .card-body-custom {
            padding: 16px;
        }

        .exercise-title {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .video-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* YouTube Embed Wrapper */
        .video-container {
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            margin-top: 8px;
        }

        /* Form Inputs for Adding Exercise */
        .form-control-custom {
            background-color: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: var(--text-primary) !important;
            border-radius: 8px;
            padding: 8px 12px;
            transition: all 0.2s ease;
        }

        .form-control-custom:focus {
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 8px rgba(186, 255, 41, 0.2) !important;
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

        /* Custom Premium Modal Styles */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(8, 8, 10, 0.8);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            z-index: 1060;
            transition: all 0.3s ease;
        }

        .custom-modal-content {
            width: 100%;
            max-width: 400px;
            max-height: 85vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch; /* Smooth iOS inertial scrolling */
            background-color: rgba(26, 28, 36, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 20px var(--border-glow);
            animation: modalFadeIn 0.3s ease;
        }

        .glass-card {
            background: rgba(26, 28, 36, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
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
            <div class="header-avatar" title="User Profile">
                U
            </div>
        </header>

        <!-- TITLE HEADER WITH ACTION BUTTONS -->
        <div class="px-3 pt-4 pb-2 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-0">Pustaka Gerakan 📚</h4>
                <p class="text-secondary small mb-0" style="font-size: 0.7rem;">Ketuk kategori otot untuk menyaring.</p>
            </div>
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-outline-warning py-1 px-2" data-bs-toggle="collapse" data-bs-target="#addExerciseForm" style="font-size: 0.72rem; font-weight: 600;">
                    <i class="bi bi-plus-lg"></i> Tambah
                </button>
                <a href="exercises.php?reset=1" class="btn btn-sm btn-outline-danger py-1 px-2" onclick="return confirm('Apakah Anda yakin ingin mereset library gerakan kembali ke 4 basic?')" style="font-size: 0.72rem; font-weight: 600;">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
            </div>
        </div>

        <!-- COLLAPSIBLE TAMBAH GERAKAN FORM -->
        <div class="collapse px-3 mb-3" id="addExerciseForm">
            <div class="card card-body border-secondary border-opacity-25 p-3 animate-fade-in" style="background-color: var(--card-bg);">
                <h6 class="fw-bold text-warning mb-3" style="font-size: 0.85rem;"><i class="bi bi-plus-circle me-1"></i>TAMBAH GERAKAN BARU</h6>
                <form action="exercises.php" method="POST">
                    <input type="hidden" name="add_exercise" value="1">
                    
                    <div class="mb-2">
                        <label class="form-label mb-1" style="font-size: 0.65rem;">Nama Gerakan</label>
                        <input type="text" name="name" class="form-control form-control-custom py-1 px-2" style="font-size: 0.8rem;" placeholder="Contoh: Incline Bench Press" required>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label mb-1" style="font-size: 0.65rem;">Otot Target</label>
                        <input type="text" name="target_muscle" class="form-control form-control-custom py-1 px-2" style="font-size: 0.8rem;" placeholder="Contoh: Chest (Dada)" required>
                    </div>
                    
                    <div class="mb-2">
                        <label class="form-label mb-1" style="font-size: 0.65rem;">Link Gambar Panduan</label>
                        <input type="url" name="image_url" class="form-control form-control-custom py-1 px-2" style="font-size: 0.8rem;" placeholder="https://images.unsplash.com/..." required>
                    </div>
                    
                    <div class="mb-3">
                        <!-- ID Video YouTube dihapus agar form lebih ringkas dan mobile-friendly -->
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100 py-1" style="font-size: 0.8rem; font-weight: 700; color: #000;">
                        <i class="bi bi-plus-lg me-1"></i>Simpan Gerakan
                    </button>
                </form>
            </div>
        </div>

        <!-- Success & Error Feedback Notifications -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mx-3 py-2" role="alert" style="background-color: rgba(25, 135, 84, 0.2); color: #20c997; border: 1px solid rgba(32, 201, 151, 0.2) !important; font-size: 0.8rem;">
                <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($success_message) ?>
                <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mx-3 py-2" role="alert" style="font-size: 0.8rem;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error_message) ?>
                <button type='button' class='btn-close btn-close-white p-2' data-bs-dismiss='alert' aria-label='Close'></button>
            </div>
        <?php endif; ?>

        <!-- FILTER SCROLL VIEW (Mobile gesture-friendly) -->
        <?php if (!empty($muscles)): ?>
            <div class="filter-scroll my-2">
                <button class="btn filter-btn active" onclick="filterMuscle('all')">Semua Otot</button>
                <?php foreach ($muscles as $muscle): ?>
                    <button class="btn filter-btn" onclick="filterMuscle('<?= htmlspecialchars($muscle) ?>')">
                        <?= htmlspecialchars($muscle) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- CARD LIST -->
        <div class="px-3 pt-2 pb-4">
            <?php if (empty($exercises)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-folder-x text-secondary" style="font-size: 2.5rem;"></i>
                    <h6 class="mt-2 text-secondary">Belum ada data gerakan.</h6>
                </div>
            <?php else: ?>
                <div id="exerciseGrid">
                    <?php foreach ($exercises as $exercise): ?>
                        <!-- Card Item -->
                        <div class="exercise-item" data-muscle="<?= htmlspecialchars($exercise['target_muscle']) ?>">
                            <div class="exercise-card">
                                
                                <!-- Card Image -->
                                <div class="card-img-wrapper">
                                    <img src="<?= htmlspecialchars($exercise['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($exercise['name']) ?>" onerror="this.src='https://images.unsplash.com/photo-1517838277536-f5f99be501cd?q=80&w=600&auto=format&fit=crop';">
                                    <span class="badge-target"><?= htmlspecialchars($exercise['target_muscle']) ?></span>
                                </div>

                                <!-- Card Body -->
                                <div class="card-body-custom">
                                    <h5 class="exercise-title mb-2"><?= htmlspecialchars($exercise['name']) ?></h5>
                                    
                                    <div class="pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-secondary small" style="font-size: 0.72rem;">
                                            <i class="bi bi-info-circle me-1 text-warning"></i>Panduan Latihan
                                        </span>
                                        <a href="https://www.youtube.com/results?search_query=<?= urlencode($exercise['name'] . ' gym tutorial') ?>" target="_blank" class="btn btn-sm btn-outline-danger py-1 px-2.5 d-flex align-items-center gap-1.5" style="font-size: 0.72rem; font-weight: 600; border-radius: 6px; text-decoration: none;">
                                            <i class="bi bi-youtube text-danger"></i> Cari di YouTube <i class="bi bi-box-arrow-up-right" style="font-size: 0.6rem;"></i>
                                        </a>
                                    </div>
                                    
                                    <!-- Edit & Delete Action Row (Futuristic & Premium) -->
                                    <div class="d-flex justify-content-end gap-2 pt-2 border-top border-secondary border-opacity-10">
                                        <button type="button" class="btn btn-sm btn-outline-warning py-0.5 px-2.5 d-flex align-items-center gap-1" onclick="openEditModal(<?= htmlspecialchars(json_encode($exercise)) ?>)" style="font-size: 0.7rem; font-weight: 600; border-radius: 6px;">
                                            <i class="bi bi-pencil-square" style="font-size: 0.72rem;"></i> Edit
                                        </button>
                                        <a href="exercises.php?delete_id=<?= $exercise['id'] ?>" class="btn btn-sm btn-outline-danger py-0.5 px-2.5 d-flex align-items-center gap-1" onclick="return confirm('Apakah Anda yakin ingin menghapus gerakan latihan ini dari perpustakaan?')" style="font-size: 0.7rem; font-weight: 600; border-radius: 6px; text-decoration: none;">
                                            <i class="bi bi-trash3" style="font-size: 0.72rem;"></i> Hapus
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- STICKY BOTTOM NAVIGATION BAR -->
        <nav class="bottom-nav">
            <a href="index.php" class="bottom-nav-item">
                <i class="bi bi-grid"></i>
                <span>Dashboard</span>
            </a>
            
            <!-- Central Elevated Floating Action Button (FAB) -->
            <div class="fab-container">
                <a href="track.php" class="fab-item" title="Catat Latihan Baru">
                    <i class="bi bi-plus-lg"></i>
                </a>
            </div>
            
            <a href="exercises.php" class="bottom-nav-item active">
                <i class="bi bi-book-fill"></i>
                <span>Library</span>
            </a>
        </nav>

    </div>

    <!-- ================== EDIT EXERCISE MODAL ================== -->
    <div id="editExerciseModal" class="custom-modal-overlay d-none" style="z-index: 1060;">
        <div class="custom-modal-content glass-card">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                <h5 class="fw-bold text-white mb-0"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Gerakan Latihan</h5>
                <button type="button" class="btn-close btn-close-white" onclick="closeEditModal()"></button>
            </div>
            
            <form action="exercises.php" method="POST">
                <input type="hidden" name="edit_exercise" value="1">
                <input type="hidden" name="id" id="edit-id">
                
                <div class="mb-2">
                    <label class="form-label mb-1" style="font-size: 0.65rem;">Nama Gerakan</label>
                    <input type="text" name="name" id="edit-name" class="form-control form-control-custom py-1 px-2" style="font-size: 0.8rem;" required>
                </div>
                
                <div class="mb-2">
                    <label class="form-label mb-1" style="font-size: 0.65rem;">Otot Target</label>
                    <input type="text" name="target_muscle" id="edit-target-muscle" class="form-control form-control-custom py-1 px-2" style="font-size: 0.8rem;" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label mb-1" style="font-size: 0.65rem;">Link Gambar / GIF Panduan</label>
                    <input type="url" name="image_url" id="edit-image-url" class="form-control form-control-custom py-1 px-2" style="font-size: 0.8rem;" required>
                </div>
                
                <button type="submit" class="btn btn-warning w-100 py-2" style="font-size: 0.8rem; font-weight: 700; color: #000; text-transform: uppercase;">
                    <i class="bi bi-save-fill me-1"></i>Simpan Perubahan
                </button>
            </form>
        </div>
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

    <!-- Client-side Filtering JS -->
    <script>
        function filterMuscle(muscle) {
            // Update active state button
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            
            // Cari tombol yang sesuai dan jadikan active
            const clickedBtn = Array.from(buttons).find(btn => btn.textContent.trim() === (muscle === 'all' ? 'Semua Otot' : muscle));
            if (clickedBtn) {
                clickedBtn.classList.add('active');
            }

            const items = document.querySelectorAll('.exercise-item');
            items.forEach(item => {
                if (muscle === 'all' || item.getAttribute('data-muscle') === muscle) {
                    item.style.display = 'block';
                    item.style.opacity = '0';
                    setTimeout(() => {
                        item.style.transition = 'opacity 0.3s ease';
                        item.style.opacity = '1';
                    }, 50);
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // --- EDIT MODAL CONTROLS ---
        function openEditModal(exercise) {
            document.getElementById('edit-id').value = exercise.id;
            document.getElementById('edit-name').value = exercise.name;
            document.getElementById('edit-target-muscle').value = exercise.target_muscle;
            document.getElementById('edit-image-url').value = exercise.image_url;
            document.getElementById('editExerciseModal').classList.remove('d-none');
        }

        function closeEditModal() {
            document.getElementById('editExerciseModal').classList.add('d-none');
        }
    </script>
</body>
</html>
