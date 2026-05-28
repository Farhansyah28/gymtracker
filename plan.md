# Gym Tracker Project Plan

## Deskripsi
Aplikasi web sederhana menggunakan PHP Native dan MySQL untuk mencatat *progress* nge-gym (set, repetisi, beban) dan menyediakan panduan penggunaan alat gym berupa gambar serta video tutorial dari YouTube.

## Fitur Utama
1. **Exercise Library (Panduan Alat):** Menampilkan daftar alat/gerakan gym, gambar cara pakai, dan *embed* video YouTube.
2. **Workout Tracker:** Form untuk mencatat gerakan yang dilakukan, jumlah set, repetisi, dan beban (kg).
3. **History/Log:** Halaman untuk melihat riwayat latihan sebelumnya.

## Tech Stack
- **Backend:** PHP Native (Procedural/OOP sederhana)
- **Database:** MySQL
- **Frontend:** HTML, CSS (Bootstrap 5 via CDN agar cepat rapi), JavaScript (jika butuh interaksi ringan)

## Struktur Database

**Tabel `exercises` (Data Alat/Gerakan)**
| Kolom | Tipe Data | Deskripsi |
|---|---|---|
| id | INT (PK, AI) | ID Gerakan |
| name | VARCHAR | Nama alat/gerakan (ex: Bench Press) |
| target_muscle | VARCHAR | Otot yang dilatih |
| image_url | VARCHAR | Link/path gambar panduan |
| youtube_id | VARCHAR | ID Video YouTube untuk di-embed |

**Tabel `workout_logs` (Catatan Latihan)**
| Kolom | Tipe Data | Deskripsi |
|---|---|---|
| id | INT (PK, AI) | ID Log |
| exercise_id | INT (FK) | Relasi ke tabel exercises |
| sets | INT | Jumlah set |
| reps | INT | Jumlah repetisi per set |
| weight | DECIMAL | Beban dalam Kg |
| created_at | TIMESTAMP | Waktu latihan dicatat |

## Struktur Folder
/gym-tracker
  ├── db.php (Koneksi database)
  ├── index.php (Dashboard & Riwayat)
  ├── exercises.php (Daftar alat & tutorial)
  ├── track.php (Form input latihan)
  └── /assets (Folder untuk gambar statis/CSS kustom)