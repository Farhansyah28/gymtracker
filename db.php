<?php
// db.php - Koneksi Database Gym Tracker
if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1'])) {
    // Konfigurasi Database Lokal (Laragon / XAMPP)
    $host = 'localhost';
    $dbname = 'gym_tracker';
    $username = 'root';
    $password = '';
} else {
    // Konfigurasi Database Production di Shared Hosting Arehost.id
    $host = '127.0.0.1'; // Menggunakan IP langsung bypass DNS IPv6 resolution timeout
    $dbname = 'boangmyi_gymtracker';       // Ganti 'boangmyi' dengan nama user cPanel Anda
    $username = 'boangmyi';       // Ganti 'boangmyi' dengan nama user cPanel Anda
    $password = 'Farhan123!'; // Ganti dengan password database cPanel Anda
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Memberikan instruksi jika database belum di-create
    die("Koneksi database gagal. Pastikan database 'gym_tracker' sudah dibuat dan server MySQL menyala.<br>Error: " . htmlspecialchars($e->getMessage()));
}

// Jalankan Auto-Migrations dan Seeder HANYA di server lokal (untuk pengembangan)
// Di server production, database sudah di-import jadi tidak perlu memeriksa skema di setiap request (mengurangi latency)
if (in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']) || isset($_GET['run_migration'])) {
    // Auto Migrations untuk inisialisasi & pembaruan skema database secara aman
    try {
        // 1. Buat tabel exercises jika belum ada
        $pdo->exec("CREATE TABLE IF NOT EXISTS exercises (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            target_muscle VARCHAR(100) NOT NULL,
            image_url VARCHAR(255) NOT NULL,
            youtube_id VARCHAR(50) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 2. Buat tabel users jika belum ada
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            height DECIMAL(5, 2) DEFAULT NULL,
            weight DECIMAL(5, 2) DEFAULT NULL,
            age INT DEFAULT NULL,
            gender ENUM('male', 'female') DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 3. Buat tabel workout_logs jika belum ada
        $pdo->exec("CREATE TABLE IF NOT EXISTS workout_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            exercise_id INT NOT NULL,
            sets INT NOT NULL,
            reps INT NOT NULL,
            weight DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 4. Tambahkan kolom user_id pada workout_logs jika belum ada
        $columns = $pdo->query("SHOW COLUMNS FROM workout_logs LIKE 'user_id'")->fetchAll();
        if (empty($columns)) {
            $pdo->exec("ALTER TABLE workout_logs ADD COLUMN user_id INT DEFAULT NULL");
            $pdo->exec("ALTER TABLE workout_logs ADD CONSTRAINT fk_workout_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        }

        // 5. Ubah kolom image_url pada exercises menjadi TEXT agar mendukung URL GIF yang panjang secara aman
        try {
            $pdo->exec("ALTER TABLE exercises MODIFY COLUMN image_url TEXT NOT NULL");
        } catch (PDOException $e) {
            // Abaikan jika kolom sudah bertipe TEXT
        }
    } catch (PDOException $e) {
        // Abaikan error migrasi otomatis saat loading awal
    }

    // Auto Seeder untuk melengkapi Exercise Library awal jika masih kosong
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM exercises")->fetchColumn();
        if ($count == 0) {
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
        }
    } catch (PDOException $e) {
        // Abaikan jika seeder gagal
    }
}
?>