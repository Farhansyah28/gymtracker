<?php
// track.php - Catat Latihan Baru (Mobile Only Form, Set Tracker & Logic)
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

// Menangani Request POST berbasis JSON (Penyimpanan Sesi Latihan ala Hevy)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    header('Content-Type: application/json');
    
    // Baca body JSON
    $json_raw = file_get_contents('php://input');
    $payload = json_decode($json_raw, true);
    
    if (empty($payload) || !is_array($payload)) {
        echo json_encode(['success' => false, 'error' => 'Data latihan kosong atau tidak valid.']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        foreach ($payload as $ex) {
            $exercise_id = filter_var($ex['exercise_id'], FILTER_VALIDATE_INT);
            $sets = $ex['sets'] ?? [];
            
            if (!$exercise_id || empty($sets)) {
                continue;
            }
            
            // Pengelompokan (grouping) set yang bernilai sama untuk efisiensi baris database
            $grouped = [];
            foreach ($sets as $set) {
                $reps = filter_var($set['reps'], FILTER_VALIDATE_INT);
                $weight = filter_var($set['weight'], FILTER_VALIDATE_FLOAT);
                
                if ($reps === false || $reps <= 0 || $weight === false || $weight < 0) {
                    continue; // Abaikan data set yang tidak valid
                }
                
                $key = $reps . '_' . $weight;
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'reps' => $reps,
                        'weight' => $weight,
                        'count' => 0
                    ];
                }
                $grouped[$key]['count']++;
            }
            
            // Simpan setiap kelompok set ke MySQL database
            foreach ($grouped as $group) {
                $stmt = $pdo->prepare("INSERT INTO workout_logs (user_id, exercise_id, sets, reps, weight) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $exercise_id, $group['count'], $group['reps'], $group['weight']]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Ambil list gerakan lengkap untuk modal exercise selection
try {
    $exercises = $pdo->query("SELECT id, name, target_muscle FROM exercises ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    $exercises = [];
}

// Ambil riwayat latihan terakhir untuk auto-fill dan previous performance
$prev_performance = [];
try {
    $stmt = $pdo->prepare("
        SELECT wl.exercise_id, wl.sets, wl.reps, wl.weight, wl.created_at
        FROM workout_logs wl
        WHERE wl.user_id = ?
        ORDER BY wl.created_at DESC, wl.id ASC
    ");
    $stmt->execute([$user_id]);
    $all_prev = $stmt->fetchAll();
    
    // Kelompokkan per exercise_id
    $exercise_sessions = [];
    foreach ($all_prev as $row) {
        $ex_id = (int)$row['exercise_id'];
        $timestamp = $row['created_at'];
        if (!isset($exercise_sessions[$ex_id])) {
            $exercise_sessions[$ex_id] = [];
        }
        if (!isset($exercise_sessions[$ex_id][$timestamp])) {
            $exercise_sessions[$ex_id][$timestamp] = [];
        }
        $exercise_sessions[$ex_id][$timestamp][] = $row;
    }
    
    // Ambil hanya sesi terbaru (timestamp pertama) untuk setiap exercise
    foreach ($exercise_sessions as $ex_id => $timestamps) {
        $latest_timestamp = array_key_first($timestamps);
        $prev_performance[$ex_id] = $timestamps[$latest_timestamp];
    }
} catch (PDOException $e) {
    $prev_performance = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Gym Tracker - Catat Sesi Latihan</title>
    
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

        /* Glass Cards */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            margin-bottom: 16px;
        }

        /* Form Controls */
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
            transition: all 0.2s ease;
        }

        .form-control-custom:focus {
            border-color: var(--accent-color) !important;
            box-shadow: 0 0 8px rgba(186, 255, 41, 0.2) !important;
        }

        /* Checkbox & Interactive Set Rows */
        .set-check-row {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(255, 255, 255, 0.02);
            border-radius: 8px;
            padding: 6px 10px;
        }
        
        .set-completed {
            background-color: rgba(186, 255, 41, 0.08) !important;
            border-color: rgba(186, 255, 41, 0.25) !important;
        }

        .set-completed .set-number-label {
            color: var(--accent-color) !important;
        }

        /* Bottom Sticky Tab Navigation */
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
            box-shadow: 0 0 20px rgba(186, 255, 41, 0.5);
            transition: all 0.2s ease;
            color: #000 !important;
            transform: rotate(45deg);
        }

        .fab-item i {
            font-size: 1.6rem;
            margin: 0;
        }

        .fab-item:active {
            transform: scale(0.9) translateY(4px) rotate(45deg);
        }

        /* Rest timer animations */
        @keyframes pulse-timer {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .animate-pulse {
            animation: pulse-timer 1s infinite;
        }

        /* Scientific Rest Timer Preset Pills */
        .preset-pill {
            background-color: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: var(--text-secondary);
            font-size: 0.62rem;
            font-weight: 700;
            border-radius: 12px;
            padding: 3px 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .preset-pill:hover, .preset-pill.active {
            background-color: rgba(0, 242, 254, 0.15);
            color: #00f2fe;
            border-color: rgba(0, 242, 254, 0.4);
            box-shadow: 0 0 8px rgba(0, 242, 254, 0.2);
        }

        /* Integrated Custom Modal Overlay (Obsidian Glass) */
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
            max-height: 80vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch; /* Smooth iOS inertial scrolling */
            border: 1px solid rgba(186, 255, 41, 0.15) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            padding: 20px;
            animation: modalSlideUp 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalSlideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Styling buttons */
        .btn-custom {
            background-color: var(--accent-color);
            color: #000;
            font-weight: 700;
            border-radius: 8px;
            padding: 12px;
            border: none;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(186, 255, 41, 0.2);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-custom:active {
            transform: scale(0.98);
        }

        .btn-outline-custom {
            background: none;
            border: 1px dashed var(--accent-color);
            color: var(--accent-color);
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px;
            transition: all 0.2s ease;
        }

        .btn-outline-custom:active {
            background: rgba(186, 255, 41, 0.05);
            transform: scale(0.98);
        }

        /* Hevy-style checkmark button */
        .btn-check-set {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.02);
            color: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-check-set.active {
            background: var(--accent-color) !important;
            border-color: var(--accent-color) !important;
            color: #000 !important;
            box-shadow: 0 0 10px rgba(186, 255, 41, 0.4);
        }

        /* Idle State Panel styling */
        .idle-panel {
            padding: 60px 20px;
            text-align: center;
        }

        .idle-icon {
            font-size: 4rem;
            color: var(--accent-color);
            text-shadow: 0 0 20px rgba(186, 255, 41, 0.25);
            margin-bottom: 20px;
            display: inline-block;
        }

        .exercise-search-item {
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 12px 6px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .exercise-search-item:hover {
            background: rgba(255,255,255,0.02);
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
            <span id="stopwatch-display" class="fw-bold text-warning small d-none" style="letter-spacing: 0.5px;">
                <i class="bi bi-stopwatch me-1 text-success"></i>00:00:00
            </span>
        </header>

        <!-- MAIN SCROLLABLE BODY -->
        <div class="px-3 py-4 flex-grow-1 d-flex flex-column">

            <!-- ================== 1. IDLE STATE CONTAINER ================== -->
            <div id="session-idle-container" class="idle-panel flex-grow-1 d-flex flex-column justify-content-center">
                <span class="idle-icon"><i class="bi bi-fire"></i></span>
                <h4 class="fw-bold mb-2">Siap Berlatih Hari Ini? 🏋️‍♂️</h4>
                <p class="text-secondary small mb-5 px-3">Mulai sesi latihan aktif untuk mencatat beban, set, dan durasi istirahat secara langsung layaknya atlet profesional.</p>
                
                <button type="button" id="btn-start-session" class="btn btn-custom py-3">
                    <i class="bi bi-play-circle-fill me-2"></i>Mulai Sesi Latihan Baru
                </button>
            </div>

            <!-- ================== 2. ACTIVE SESSION CONTAINER ================== -->
            <div id="session-active-container" class="d-none flex-grow-1 d-flex flex-column">
                
                <!-- Active Session Title -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-white mb-0"><i class="bi bi-circle-fill text-success fs-6 me-2 animate-pulse"></i>Sesi Latihan Aktif</h5>
                    <span class="badge bg-secondary border border-secondary border-opacity-25" id="exercise-counter">0 Gerakan</span>
                </div>

                <!-- Exercice List Container -->
                <div id="session-exercises-list" class="flex-grow-1 mb-4">
                    <!-- Dynamic exercises injected here by JS -->
                </div>

                <!-- Control actions -->
                <div class="d-flex flex-column gap-2 mb-5">
                    <button type="button" id="btn-open-exercise-modal" class="btn btn-outline-custom w-100 py-2.5">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Gerakan ke Sesi
                    </button>
                    <button type="button" id="btn-finish-session" class="btn btn-custom w-100 py-3 mt-3">
                        <i class="bi bi-check-circle-fill me-2"></i>Selesaikan Latihan
                    </button>
                    <button type="button" id="btn-cancel-session" class="btn btn-link text-danger text-decoration-none small mt-1">
                        Batalkan Sesi Latihan
                    </button>
                </div>

                <!-- DYNAMIC REST TIMER COUNTER Countdown Display -->
                <div id="rest-timer-wrapper" class="p-3 bg-black bg-opacity-40 rounded border border-info border-opacity-25 d-none text-center sticky-bottom" style="z-index: 998; bottom: 85px;">
                    <span class="text-secondary d-block" style="font-size: 0.6rem; letter-spacing: 1px; font-weight: bold;">REST TIMER</span>
                    <h4 id="timer-display" class="fw-bold text-info mb-0" style="font-size: 1.5rem; text-shadow: 0 0 10px rgba(0, 242, 254, 0.2);">01:00</h4>
                    <div class="progress mt-2 mb-3" style="height: 3px; background-color: rgba(255,255,255,0.05);">
                        <div id="timer-progress" class="progress-bar bg-info" style="width: 100%; transition: width 1s linear;"></div>
                    </div>
                    <!-- Scientific rest time presets (Meta-Analysis 2024 Optimals) -->
                    <div id="rest-presets-row" class="d-flex justify-content-center gap-1 mb-3 flex-wrap">
                        <button type="button" class="btn preset-pill active" onclick="setRestPreset(45, this)" title="Abs / Core (30s - 60s)">Core (45s)</button>
                        <button type="button" class="btn preset-pill" onclick="setRestPreset(90, this)" title="Isolation (60s - 90s)">Iso (90s)</button>
                        <button type="button" class="btn preset-pill" onclick="setRestPreset(120, this)" title="Hypertrophy (1m - 3m)">Hyper (2m)</button>
                        <button type="button" class="btn preset-pill" onclick="setRestPreset(180, this)" title="Compound / Heavy Strength (2m - 5m)">Heavy (3m)</button>
                    </div>
                    <!-- Quick control buttons -->
                    <div class="d-flex justify-content-center gap-2">
                        <button id="btn-adjust-minus" type="button" class="btn btn-sm btn-outline-info py-0.5 px-2 text-info border-info" style="font-size: 0.7rem; font-weight: bold;" onclick="adjustRestTimer(-15)">-15s</button>
                        <button id="btn-adjust-plus" type="button" class="btn btn-sm btn-outline-info py-0.5 px-2 text-info border-info" style="font-size: 0.7rem; font-weight: bold;" onclick="adjustRestTimer(15)">+15s</button>
                        <button id="btn-timer-skip" type="button" class="btn btn-sm btn-outline-danger py-0.5 px-2 text-danger border-danger" style="font-size: 0.7rem; font-weight: bold;" onclick="skipRestTimer()">Skip</button>
                    </div>
                </div>

            </div>

        </div>

        <!-- STICKY BOTTOM NAVIGATION BAR -->
        <nav class="bottom-nav">
            <a href="index.php" class="bottom-nav-item">
                <i class="bi bi-grid"></i>
                <span>Dashboard</span>
            </a>
            
            <!-- Central Elevated Floating Action Button (FAB) -->
            <div class="fab-container">
                <a href="track.php" class="fab-item" title="Catat Sesi Latihan">
                    <i class="bi bi-plus-lg"></i>
                </a>
            </div>
            
            <a href="exercises.php" class="bottom-nav-item">
                <i class="bi bi-book"></i>
                <span>Library</span>
            </a>
        </nav>

    </div>

    <!-- ================== INTEGRATED SEARCH EXERCISE MODAL ================== -->
    <div id="exerciseModal" class="custom-modal-overlay d-none" style="z-index: 1060;">
        <div class="custom-modal-content glass-card">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                <h5 class="fw-bold text-white mb-0"><i class="bi bi-plus-circle text-warning me-2"></i>Pilih Gerakan Latihan</h5>
                <button type="button" class="btn-close btn-close-white" id="btnCloseExerciseModal"></button>
            </div>
            
            <!-- Live Search input -->
            <div class="mb-3">
                <input type="text" id="exercise-search" class="form-control form-control-custom py-2" placeholder="Cari nama gerakan atau otot target...">
            </div>

            <!-- List of filtered exercises -->
            <div id="modal-exercises-list" class="d-flex flex-column">
                <!-- Loaded via JS -->
            </div>
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

    <!-- HEVY-STYLE SESSION TRACKING MOTOR JAVASCRIPT -->
    <script>
        // Data Gerakan dari Database
        const EXERCISES_LIBRARY = <?= json_encode($exercises) ?>;
        
        // Data Previous Performance dari Database
        const PREV_PERFORMANCE = <?= json_encode($prev_performance) ?>;
        
        // State Sesi Latihan Aktif
        let workoutSession = {
            active: false,
            startTime: null,
            exercises: [] // Array of { id, name, target, isTimed, sets: [ { weight, reps, completed, prevWeight, prevReps } ] }
        };

        let stopwatchInterval = null;
        let timerInterval = null;
        let audioCtx = null;
        let timeLeft = 0;
        let totalTime = 0;
        let alarmAudio = null;
        let wakeLock = null;

        // Elemen DOM
        const idleContainer = document.getElementById('session-idle-container');
        const activeContainer = document.getElementById('session-active-container');
        const stopwatchDisplay = document.getElementById('stopwatch-display');
        const exercisesListContainer = document.getElementById('session-exercises-list');
        const exerciseCounter = document.getElementById('exercise-counter');
        
        const btnStartSession = document.getElementById('btn-start-session');
        const btnFinishSession = document.getElementById('btn-finish-session');
        const btnCancelSession = document.getElementById('btn-cancel-session');
        const btnOpenModal = document.getElementById('btn-open-exercise-modal');
        
        const exerciseModal = document.getElementById('exerciseModal');
        const btnCloseModal = document.getElementById('btnCloseExerciseModal');
        const searchInput = document.getElementById('exercise-search');
        const modalListContainer = document.getElementById('modal-exercises-list');

        // Pre-Unlock Audio Context untuk HP (iOS/Android)
        function initAudio() {
            try {
                if (!audioCtx) {
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (AudioContextClass) {
                        audioCtx = new AudioContextClass();
                    }
                }
                if (audioCtx) {
                    if (audioCtx.state === 'suspended') {
                        audioCtx.resume();
                    }
                    // Bunyikan nada senyap instan demi membuka kunci audio channel peramban
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    gain.gain.setValueAtTime(0.0001, audioCtx.currentTime);
                    osc.start(0);
                    osc.stop(0.01);
                }
            } catch (e) {
                console.error("Gagal mengaktifkan AudioContext: ", e);
            }
        }

        // Screen Wake Lock API untuk menjaga layar HP tetap hidup saat berlatih
        async function requestWakeLock() {
            try {
                if ('wakeLock' in navigator) {
                    wakeLock = await navigator.wakeLock.request('screen');
                    console.log('Screen Wake Lock aktif!');
                }
            } catch (err) {
                console.warn(`Gagal mengaktifkan Wake Lock: ${err.name}, ${err.message}`);
            }
        }

        function releaseWakeLock() {
            try {
                if (wakeLock !== null) {
                    wakeLock.release();
                    wakeLock = null;
                    console.log('Screen Wake Lock dilepas.');
                }
            } catch (err) {
                console.warn(`Gagal melepas Wake Lock: ${err.message}`);
            }
        }

        // Dapatkan kembali wake lock saat pengguna kembali ke tab aplikasi
        document.addEventListener('visibilitychange', async () => {
            if (wakeLock !== null && document.visibilityState === 'visible') {
                await requestWakeLock();
            }
        });

        // --- 1. LOCAL STORAGE PERSISTENCE ENGINE ---
        function saveSessionState() {
            if (workoutSession.active) {
                localStorage.setItem('gymtracker_active_session', JSON.stringify(workoutSession));
            } else {
                localStorage.removeItem('gymtracker_active_session');
            }
        }

        function loadSessionState() {
            const saved = localStorage.getItem('gymtracker_active_session');
            if (saved) {
                try {
                    const parsed = JSON.parse(saved);
                    if (parsed && parsed.active) {
                        workoutSession = parsed;
                        workoutSession.startTime = new Date(workoutSession.startTime);
                        
                        // Transformasi UI ke Sesi Aktif
                        idleContainer.classList.add('d-none');
                        activeContainer.classList.remove('d-none');
                        
                        // Mulai kembali stopwatch
                        startStopwatch(true);
                        renderActiveSession();
                        return true;
                    }
                } catch (e) {
                    console.error("Gagal memulihkan sesi dari localStorage: ", e);
                }
            }
            return false;
        }

        // --- 2. STOPWATCH ENGINE ---
        function startStopwatch(resume = false) {
            if (!resume || !workoutSession.startTime) {
                workoutSession.startTime = new Date();
                saveSessionState();
            }
            stopwatchDisplay.classList.remove('d-none');
            
            // Interval Stopwatch
            if (stopwatchInterval) clearInterval(stopwatchInterval);
            stopwatchInterval = setInterval(() => {
                const elapsedMs = new Date() - workoutSession.startTime;
                const secs = Math.floor(elapsedMs / 1000) % 60;
                const mins = Math.floor(elapsedMs / 60000) % 60;
                const hrs = Math.floor(elapsedMs / 3600000);
                
                const timeStr = [
                    hrs.toString().padStart(2, '0'),
                    mins.toString().padStart(2, '0'),
                    secs.toString().padStart(2, '0')
                ].join(':');
                
                stopwatchDisplay.innerHTML = `<i class="bi bi-stopwatch me-1 text-success"></i>${timeStr}`;
            }, 1000);
        }

        function stopStopwatch() {
            if (stopwatchInterval) clearInterval(stopwatchInterval);
            stopwatchDisplay.classList.add('d-none');
        }

        // --- 3. SESSION LIFECYCLE ---
        btnStartSession.addEventListener('click', () => {
            initAudio(); // Unlock audio instan
            
            // Transformasi UI
            idleContainer.classList.add('d-none');
            activeContainer.classList.remove('d-none');
            
            // Set state
            workoutSession.active = true;
            workoutSession.exercises = [];
            
            startStopwatch();
            saveSessionState();
            renderActiveSession();
            
            // Buka langsung modal pemilih gerakan untuk memudahkan user memulai
            openExerciseModal();
        });

        btnCancelSession.addEventListener('click', () => {
            if (confirm('Batalkan sesi latihan saat ini? Seluruh data yang belum disimpan akan terhapus.')) {
                cancelWorkoutSession();
            }
        });

        function cancelWorkoutSession() {
            stopStopwatch();
            clearInterval(timerInterval);
            stopAlarmSound();
            releaseWakeLock();
            document.getElementById('rest-timer-wrapper').classList.add('d-none');
            
            workoutSession.active = false;
            workoutSession.exercises = [];
            saveSessionState();
            
            idleContainer.classList.remove('d-none');
            activeContainer.classList.add('d-none');
        }

        // Simpan seluruh data latihan via AJAX JSON POST
        btnFinishSession.addEventListener('click', () => {
            // Saring hanya gerakan yang memiliki minimal satu set yang diselesaikan (checked)
            const workoutData = [];
            
            workoutSession.exercises.forEach(ex => {
                const completedSets = ex.sets.filter(s => s.completed);
                if (completedSets.length > 0) {
                    workoutData.push({
                        exercise_id: ex.id,
                        sets: completedSets.map(s => ({
                            reps: parseInt(s.reps) || 0,
                            weight: parseFloat(s.weight) || 0
                        }))
                    });
                }
            });

            if (workoutData.length === 0) {
                alert('Silakan selesaikan minimal 1 set gerakan (centang hijau) sebelum mengakhiri latihan!');
                return;
            }

            // Kirim payload ke track.php
            fetch('track.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(workoutData)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    stopAlarmSound();
                    releaseWakeLock();
                    // Bersihkan localStorage sesi aktif
                    localStorage.removeItem('gymtracker_active_session');
                    // Berhasil menyimpan, kirim ke dashboard
                    window.location.href = 'index.php?success=1';
                } else {
                    alert('Gagal menyimpan latihan: ' + (data.error || 'Server error'));
                }
            })
            .catch(err => {
                console.error("Gagal mengirim data latihan: ", err);
                alert('Terjadi kesalahan jaringan atau server.');
            });
        });

        // --- 4. EXERCISE SELECTION MODAL ---
        btnOpenModal.addEventListener('click', openExerciseModal);
        btnCloseModal.addEventListener('click', closeExerciseModal);
        
        exerciseModal.addEventListener('click', (e) => {
            if (e.target === exerciseModal) closeExerciseModal();
        });

        function openExerciseModal() {
            searchInput.value = '';
            renderModalExercises();
            exerciseModal.classList.remove('d-none');
            setTimeout(() => searchInput.focus(), 150);
        }

        function closeExerciseModal() {
            exerciseModal.classList.add('d-none');
        }

        searchInput.addEventListener('input', renderModalExercises);

        function renderModalExercises() {
            const query = searchInput.value.toLowerCase();
            modalListContainer.innerHTML = '';

            const filtered = EXERCISES_LIBRARY.filter(ex => 
                ex.name.toLowerCase().includes(query) || 
                ex.target_muscle.toLowerCase().includes(query)
            );

            if (filtered.length === 0) {
                modalListContainer.innerHTML = '<div class="text-secondary small text-center py-4">Gerakan tidak ditemukan.</div>';
                return;
            }

            filtered.forEach(ex => {
                const item = document.createElement('div');
                item.className = 'exercise-search-item d-flex justify-content-between align-items-center';
                item.innerHTML = `
                    <div>
                        <div class="fw-bold text-white small">${ex.name}</div>
                        <div class="text-secondary" style="font-size: 0.7rem;">${ex.target_muscle}</div>
                    </div>
                    <i class="bi bi-plus-circle-fill text-warning fs-5"></i>
                `;
                item.addEventListener('click', () => {
                    initAudio(); // pastikan audio unlocked
                    addExerciseToSession(ex);
                    closeExerciseModal();
                });
                modalListContainer.appendChild(item);
            });
        }

        // --- 5. SESSION BUILDER ACTIONS ---
        function addExerciseToSession(ex) {
            // Cek apakah gerakan sudah ditambahkan sebelumnya
            const exists = workoutSession.exercises.find(e => e.id === ex.id);
            if (exists) {
                alert(`Gerakan ${ex.name} sudah ditambahkan ke dalam sesi latihan.`);
                return;
            }

            const isTimed = /hang|plank|hold|sit|detik|second/i.test(ex.name);

            // Ambil riwayat latihan terakhir untuk gerakan ini (PREV_PERFORMANCE)
            const prevData = PREV_PERFORMANCE[ex.id] || null;
            let initialSets = [];

            if (prevData && prevData.length > 0) {
                // Kembangkan set yang dikelompokkan ke baris set individual (Hevy style)
                prevData.forEach(group => {
                    const count = parseInt(group.sets) || 1;
                    for (let i = 0; i < count; i++) {
                        initialSets.push({
                            weight: '',
                            reps: '',
                            completed: false,
                            prevWeight: parseFloat(group.weight),
                            prevReps: parseInt(group.reps)
                        });
                    }
                });
            } else {
                // Default set 1 jika tidak ada riwayat
                initialSets.push({
                    weight: '',
                    reps: '',
                    completed: false,
                    prevWeight: null,
                    prevReps: null
                });
            }

            // Tambahkan gerakan baru ke state
            workoutSession.exercises.push({
                id: parseInt(ex.id),
                name: ex.name,
                target: ex.target_muscle,
                isTimed: isTimed,
                sets: initialSets
            });

            saveSessionState();
            renderActiveSession();
        }

        function removeExerciseFromSession(exIndex) {
            if (confirm('Hapus gerakan ini dari sesi latihan? Seluruh data set gerakan ini akan dihapus.')) {
                workoutSession.exercises.splice(exIndex, 1);
                saveSessionState();
                renderActiveSession();
            }
        }

        function addSetToExercise(exIndex) {
            const ex = workoutSession.exercises[exIndex];
            
            // Ambil berat & reps dari set terakhir sebagai panduan kenyamanan penginputan (*auto-fill*)
            const lastSet = ex.sets[ex.sets.length - 1];
            const defaultWeight = lastSet ? lastSet.weight : '';
            const defaultReps = lastSet ? lastSet.reps : '';
            const prevWeight = lastSet ? lastSet.prevWeight : null;
            const prevReps = lastSet ? lastSet.prevReps : null;

            ex.sets.push({
                weight: defaultWeight,
                reps: defaultReps,
                completed: false,
                prevWeight: prevWeight,
                prevReps: prevReps
            });

            saveSessionState();
            renderActiveSession();
        }

        function removeSetFromExercise(exIndex, setIndex) {
            const ex = workoutSession.exercises[exIndex];
            ex.sets.splice(setIndex, 1);
            
            // Jika set kosong, hapus gerakan secara otomatis
            if (ex.sets.length === 0) {
                workoutSession.exercises.splice(exIndex, 1);
            }
            
            saveSessionState();
            renderActiveSession();
        }

        function updateSetVal(exIndex, setIndex, field, val) {
            workoutSession.exercises[exIndex].sets[setIndex][field] = val;
            saveSessionState();
        }

        // Menandakan set selesai (centang Hevy)
        function checkSetRow(btn, exIndex, setIndex) {
            initAudio(); // Unlock audio
            
            const ex = workoutSession.exercises[exIndex];
            const set = ex.sets[setIndex];
            const row = btn.closest('.set-check-row');
            const inputs = row.querySelectorAll('input[type="number"]');

            // Fitur Auto-fill dari previous performance apabila input masih kosong
            if (!set.weight && set.prevWeight !== null) {
                set.weight = set.prevWeight.toString();
                inputs[0].value = set.weight;
            }
            if (!set.reps && set.prevReps !== null) {
                set.reps = set.prevReps.toString();
                inputs[1].value = set.reps;
            }

            // Validasi: Wajib isi data sebelum dicentang
            if (!set.weight || !set.reps || parseFloat(set.weight) < 0 || parseInt(set.reps) <= 0) {
                alert('Silakan isi beban (Kg) dan repetisi/detik terlebih dahulu sebelum mencentang!');
                btn.blur();
                return;
            }

            set.completed = !set.completed;
            saveSessionState();
            
            // Visual feedback
            if (set.completed) {
                btn.classList.add('active');
                row.classList.add('set-completed');
                
                // Deteksi rest time optimal secara ilmiah (Meta-Analysis 2024)
                const optimalSecs = getOptimalRestTime(ex.name, ex.target);
                startRestTimer(optimalSecs);
            } else {
                btn.classList.remove('active');
                row.classList.remove('set-completed');
            }
        }

        // --- 6. RENDER ACTIVE SESSION TO DOM ---
        function renderActiveSession() {
            exercisesListContainer.innerHTML = '';
            
            const totalExercises = workoutSession.exercises.length;
            exerciseCounter.textContent = `${totalExercises} Gerakan`;

            if (totalExercises === 0) {
                exercisesListContainer.innerHTML = `
                    <div class="text-secondary small text-center py-5 glass-card p-3">
                        <i class="bi bi-journal-plus d-block fs-3 mb-2 text-warning"></i>
                        Belum ada gerakan ditambahkan.<br>Klik tombol di bawah untuk menyusun latihan!
                    </div>
                `;
                return;
            }

            workoutSession.exercises.forEach((ex, exIndex) => {
                const card = document.createElement('div');
                card.className = 'glass-card p-3';
                
                // Card Header
                let html = `
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary border-opacity-25">
                        <div>
                            <div class="fw-bold text-white" style="font-size: 0.95rem;">${ex.name}</div>
                            <span class="badge bg-secondary border border-secondary border-opacity-25" style="font-size: 0.65rem; color: var(--accent-color) !important;">${ex.target}</span>
                        </div>
                        <button type="button" class="btn btn-link text-danger p-0" onclick="removeExerciseFromSession(${exIndex})" title="Hapus Gerakan">
                            <i class="bi bi-trash3 fs-6"></i>
                        </button>
                    </div>
                    
                    <!-- Table Header -->
                    <div class="d-flex text-secondary fw-bold text-center mb-2" style="font-size: 0.65rem; letter-spacing: 0.5px;">
                        <span style="width: 25px;">SET</span>
                        <span class="flex-grow-1 text-start ps-2">PREVIOUS</span>
                        <span style="width: 80px;">KG</span>
                        <span style="width: 80px;">${ex.isTimed ? 'DETIK' : 'REPS'}</span>
                        <span style="width: 32px;">CHECK</span>
                        <span style="width: 20px;"></span>
                    </div>
                    
                    <!-- Sets rows list -->
                    <div class="d-flex flex-column gap-2 mb-3">
                `;

                // Set rows
                ex.sets.forEach((set, setIndex) => {
                    const completedClass = set.completed ? 'set-completed' : '';
                    const completedBtnClass = set.completed ? 'active' : '';
                    const prevDisplay = (set.prevWeight !== null && set.prevReps !== null) 
                        ? `${set.prevWeight} kg x ${set.prevReps}` 
                        : '—';

                    html += `
                        <div class="d-flex align-items-center justify-content-between set-check-row gap-2 ${completedClass}">
                            <!-- Set num -->
                            <span class="fw-bold text-secondary text-center set-number-label" style="width: 25px; font-size: 0.8rem;">${setIndex + 1}</span>
                            
                            <!-- Previous display -->
                            <span class="flex-grow-1 text-start ps-2 text-secondary" style="font-size: 0.72rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 90px;" title="${prevDisplay}">
                                ${prevDisplay}
                            </span>
                            
                            <!-- Kg input -->
                            <input type="number" step="0.1" class="form-control form-control-custom text-center py-1 px-2" placeholder="${set.prevWeight !== null ? set.prevWeight : '0'}" value="${set.weight}" style="font-size: 0.85rem; width: 80px;" oninput="updateSetVal(${exIndex}, ${setIndex}, 'weight', this.value)" ${set.completed ? 'disabled' : ''} required>
                            
                            <!-- Reps / Seconds input -->
                            <input type="number" class="form-control form-control-custom text-center py-1 px-2" placeholder="${set.prevReps !== null ? set.prevReps : '0'}" value="${set.reps}" style="font-size: 0.85rem; width: 80px;" oninput="updateSetVal(${exIndex}, ${setIndex}, 'reps', this.value)" ${set.completed ? 'disabled' : ''} required>
                            
                            <!-- Complete Checkmark Button -->
                            <button type="button" class="btn-check-set ${completedBtnClass}" onclick="checkSetRow(this, ${exIndex}, ${setIndex})">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            
                            <!-- Delete set -->
                            <button type="button" class="btn btn-link text-danger btn-sm p-0" onclick="removeSetFromExercise(${exIndex}, ${setIndex})" ${set.completed ? 'disabled' : ''} title="Hapus Set" style="width: 20px;">
                                <i class="bi bi-x-circle fs-6"></i>
                            </button>
                        </div>
                    `;
                });

                // Card Footer (Add Set button)
                html += `
                    </div>
                    <button type="button" class="btn-outline-custom w-100 py-1.5" onclick="addSetToExercise(${exIndex})">
                        <i class="bi bi-plus-lg me-1"></i>Tambah Set
                    </button>
                `;

                card.innerHTML = html;
                exercisesListContainer.appendChild(card);
            });
        }

        // --- 7. REST TIMER COUNTDOWN ---
        function startRestTimer(seconds) {
            clearInterval(timerInterval);
            stopAlarmSound(); // Pastikan alarm sebelumnya mati
            requestWakeLock(); // Jaga layar tetap aktif

            const timerWrapper = document.getElementById('rest-timer-wrapper');
            const timerDisplay = document.getElementById('timer-display');
            const timerProgress = document.getElementById('timer-progress');
            
            // Kembalikan tampilan tombol control dan skip ke bentuk normal
            document.getElementById('btn-adjust-minus').classList.remove('d-none');
            document.getElementById('btn-adjust-plus').classList.remove('d-none');
            
            const skipBtn = document.getElementById('btn-timer-skip');
            skipBtn.innerHTML = 'Skip';
            skipBtn.className = "btn btn-sm btn-outline-danger py-0.5 px-2 text-danger border-danger";
            skipBtn.style.fontSize = "0.7rem";
            skipBtn.style.fontWeight = "bold";

            // Update status active pada pill preset yang cocok dengan durasi detik ini
            const pills = document.querySelectorAll('.preset-pill');
            pills.forEach(pill => {
                const onclickStr = pill.getAttribute('onclick');
                if (onclickStr && onclickStr.includes(seconds.toString() + ',')) {
                    pill.classList.add('active');
                } else {
                    pill.classList.remove('active');
                }
            });

            timerWrapper.classList.remove('d-none');
            timeLeft = seconds;
            totalTime = seconds;

            timerDisplay.textContent = formatTime(timeLeft);
            timerDisplay.className = "fw-bold text-info mb-0";
            timerProgress.style.width = '100%';
            timerProgress.className = "progress-bar bg-info";

            timerInterval = setInterval(() => {
                timeLeft--;
                if (timeLeft < 0) timeLeft = 0;
                
                timerDisplay.textContent = formatTime(timeLeft);
                timerProgress.style.width = `${(timeLeft / totalTime) * 100}%`;

                if (timeLeft <= 10 && timeLeft > 0) {
                    timerProgress.className = "progress-bar bg-danger animate-pulse";
                    timerDisplay.className = "fw-bold text-danger mb-0 animate-pulse";
                }

                if (timeLeft <= 0) {
                    triggerRestTimerEnd();
                }
            }, 1000);
        }

        function triggerRestTimerEnd() {
            clearInterval(timerInterval);
            const timerDisplay = document.getElementById('timer-display');
            const timerProgress = document.getElementById('timer-progress');
            
            timerDisplay.textContent = "WAKTU ISTIRAHAT SELESAI!";
            timerDisplay.className = "fw-bold text-success mb-0 animate-pulse";
            timerProgress.style.width = '0%';
            
            // Sembunyikan tombol +/- 15s dan ubah tombol Skip menjadi "MATIKAN ALARM" yang besar & menonjol
            document.getElementById('btn-adjust-minus').classList.add('d-none');
            document.getElementById('btn-adjust-plus').classList.add('d-none');
            
            const skipBtn = document.getElementById('btn-timer-skip');
            skipBtn.innerHTML = '<i class="bi bi-bell-slash"></i> MATIKAN ALARM';
            skipBtn.className = "btn btn-sm btn-danger py-1.5 px-3 w-100 text-black fw-bold";
            skipBtn.style.fontSize = "0.8rem";

            // Mainkan audio MP3 terulang
            playAlarmSound();
        }

        function adjustRestTimer(amount) {
            if (timeLeft <= 0) return;
            timeLeft += amount;
            if (timeLeft < 0) timeLeft = 0;
            if (timeLeft > totalTime) totalTime = timeLeft; // Expand total if needed
            
            const timerDisplay = document.getElementById('timer-display');
            const timerProgress = document.getElementById('timer-progress');
            
            timerDisplay.textContent = formatTime(timeLeft);
            timerProgress.style.width = `${(timeLeft / totalTime) * 100}%`;
            
            if (timeLeft <= 0) {
                triggerRestTimerEnd();
            }
        }

        function skipRestTimer() {
            clearInterval(timerInterval);
            timeLeft = 0;
            stopAlarmSound(); // Hentikan audio alarm
            releaseWakeLock(); // Lepas wake lock agar layar hp bisa mati normal
            document.getElementById('rest-timer-wrapper').classList.add('d-none');
        }

        // Set waktu istirahat secara manual via scientific preset pills
        function setRestPreset(seconds, btn) {
            // Hapus status aktif dari seluruh preset-pill
            const pills = document.querySelectorAll('.preset-pill');
            pills.forEach(pill => pill.classList.remove('active'));
            
            // Tandai pill yang sedang aktif
            if (btn) {
                btn.classList.add('active');
            }
            
            // Mulai ulang rest timer dengan durasi ilmiah yang dipilih
            startRestTimer(seconds);
        }

        // Algoritma penentuan waktu istirahat optimal berdasarkan meta-analisis ilmiah 2024
        function getOptimalRestTime(exerciseName, targetMuscle) {
            const name = exerciseName.toLowerCase();
            const target = targetMuscle ? targetMuscle.toLowerCase() : '';
            
            // 1. Abs / Core -> 45 Detik (optimal 30s - 60s)
            if (name.includes('abs') || name.includes('core') || name.includes('crunch') || name.includes('plank') || name.includes('sit up') || name.includes('hang') || target.includes('abs') || target.includes('core')) {
                return 45;
            }
            
            // 2. Heavy Compound -> 3 Menit (180 Detik) (optimal 2m - 5m)
            if (name.includes('squat') || name.includes('bench') || name.includes('deadlift') || name.includes('press') || name.includes('pulldown') || name.includes('pull up') || name.includes('chin up') || name.includes('row')) {
                return 180;
            }
            
            // 3. Isolation -> 90 Detik (1.5 Menit) (optimal 60s - 90s)
            if (name.includes('curl') || name.includes('raise') || name.includes('pushdown') || name.includes('fly') || name.includes('extension') || name.includes('kickback') || target.includes('biceps') || target.includes('triceps') || target.includes('deltoid') || target.includes('lengan')) {
                return 90;
            }
            
            // 4. Default Hypertrophy -> 120 Detik (2 Menit) (optimal 1m - 3m)
            return 120;
        }

        // Memutar audio alarm.mp3 bawaan secara looping
        function playAlarmSound() {
            try {
                stopAlarmSound();
                
                if (!alarmAudio) {
                    alarmAudio = new Audio('alarm.mp3');
                    alarmAudio.loop = true;
                    alarmAudio.volume = 1.0; // Maksimum volume keras
                }
                alarmAudio.currentTime = 0;
                
                // PWA / Mobile Compliance: Tangkap kegagalan autoplay jika user belum tap layar
                alarmAudio.play().catch(err => {
                    console.warn("Autoplay diblokir oleh peramban, memainkan fallback bel sintetis:", err);
                    playFallbackSyntheticSound();
                });
            } catch (e) {
                console.error("Gagal memutar alarm.mp3: ", e);
                playFallbackSyntheticSound();
            }
        }

        function stopAlarmSound() {
            if (alarmAudio) {
                try {
                    alarmAudio.pause();
                    alarmAudio.currentTime = 0;
                } catch (e) {
                    console.error("Gagal menghentikan alarm MP3: ", e);
                }
            }
        }

        // Sintesis bel ceria sebagai cadangan (C5 - E5 - G5)
        function playFallbackSyntheticSound() {
            try {
                initAudio();
                if (!audioCtx) return;
                
                const playTone = (freq, duration, delay) => {
                    const osc = audioCtx.createOscillator();
                    const gainNode = audioCtx.createGain();
                    
                    osc.connect(gainNode);
                    gainNode.connect(audioCtx.destination);
                    
                    osc.type = 'sine';
                    osc.frequency.value = freq;
                    
                    const startTime = audioCtx.currentTime + delay;
                    gainNode.gain.setValueAtTime(0.85, startTime); // Suara bel sintetis lebih lantang
                    gainNode.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);
                    
                    osc.start(startTime);
                    osc.stop(startTime + duration);
                };

                playTone(523.25, 0.25, 0);     // C5
                playTone(659.25, 0.25, 0.12);  // E5
                playTone(783.99, 0.4, 0.24);   // G5
            } catch (e) {
                console.error("Gagal membunyikan alarm bel sintetis: ", e);
            }
        }

        function formatTime(secs) {
            const m = Math.floor(secs / 60).toString().padStart(2, '0');
            const s = (secs % 60).toString().padStart(2, '0');
            return `${m}:${s}`;
        }

        // --- 8. INITIAL LOADING AND RESTORING ---
        document.addEventListener('DOMContentLoaded', () => {
            // Coba pulihkan sesi aktif jika ada
            const isRestored = loadSessionState();
            
            // Tambahkan event click ke seluruh body untuk unlock AudioContext pada tap pertama kali (mobile compliance)
            document.body.addEventListener('click', () => {
                initAudio();
            }, { once: true });
        });
    </script>
</body>
</html>
