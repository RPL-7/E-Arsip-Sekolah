<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah admin
checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$user_username = $_SESSION['user_username'];

// Koneksi database
$pdo = getDBConnection();

// Ambil statistik
try {
    // Total siswa aktif
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'aktif'");
    $total_siswa = $stmt->fetch()['total'];
    
    // Total guru aktif
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_guru WHERE status = 'aktif'");
    $total_guru = $stmt->fetch()['total'];
    
    // Total kelas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas");
    $total_kelas = $stmt->fetch()['total'];
    
    // Total pelajaran
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelajaran");
    $total_pelajaran = $stmt->fetch()['total'];
    
    // Total arsip
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM arsip");
    $total_arsip = $stmt->fetch()['total'];
    
    // Total admin
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin");
    $total_admin = $stmt->fetch()['total'];
    
    // Data siswa terbaru (5 terakhir)
    $stmt = $pdo->query("
        SELECT s.nis, s.nama_siswa, k.nama_kelas, s.status
        FROM user_siswa s
        LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
        ORDER BY s.id_siswa DESC
        LIMIT 5
    ");
    $siswa_terbaru = $stmt->fetchAll();
    
    // Data guru terbaru (5 terakhir)
    $stmt = $pdo->query("
        SELECT id_guru, nama_guru, email, status
        FROM user_guru
        ORDER BY id_guru DESC
        LIMIT 5
    ");
    $guru_terbaru = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Sekolah</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>
<body>
    <div class="navbar">
        <div class="navbar-content">
            <h1>
                <span>🎓</span>
                Dashboard Administrator
            </h1>
            <div class="user-info">
                <div class="user-badge">
                    <strong><?php echo htmlspecialchars($user_name); ?></strong> (@<?php echo htmlspecialchars($user_username); ?>)
                </div>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <aside class="sidebar">
            <a href="#" class="menu-item active">
                <span>📊</span> Dashboard
            </a>
            <a href="../manage/manage_siswa.php" class="menu-item">
                <span>👥</span> Manajemen Siswa
            </a>
            <a href="../manage/manage_guru.php" class="menu-item">
                <span>👨‍🏫</span> Manajemen Guru
            </a>
            <a href="../manage/manage_kelas.php" class="menu-item">
                <span>🏫</span> Manajemen Kelas
            </a>
            <a href="../manage/manage_pelajaran.php" class="menu-item">
                <span>📚</span> Manajemen Pelajaran
            </a>
            <a href="../manage/manage_arsip.php" class="menu-item">
                <span>📁</span> Manajemen Arsip
            </a>
            <a href="#" class="menu-item">
                <span>👤</span> Manajemen Admin
            </a>
            <a href="#" class="menu-item">
                <span>📊</span> Laporan
            </a>
            <a href="#" class="menu-item">
                <span>⚙️</span> Pengaturan
            </a>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h2>Selamat Datang, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
                <p>Berikut adalah ringkasan sistem pada <?php echo date('d F Y'); ?></p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Siswa</h3>
                    <div class="number"><?php echo $total_siswa; ?></div>
                    <div class="label">Siswa Aktif</div>
                </div>
                <div class="stat-card" style="border-left-color: #38ef7d;">
                    <h3>Total Guru</h3>
                    <div class="number" style="color: #38ef7d;"><?php echo $total_guru; ?></div>
                    <div class="label">Guru Aktif</div>
                </div>
                <div class="stat-card" style="border-left-color: #f5576c;">
                    <h3>Total Kelas</h3>
                    <div class="number" style="color: #f5576c;"><?php echo $total_kelas; ?></div>
                    <div class="label">Kelas Tersedia</div>
                </div>
                <div class="stat-card" style="border-left-color: #ffa726;">
                    <h3>Total Pelajaran</h3>
                    <div class="number" style="color: #ffa726;"><?php echo $total_pelajaran; ?></div>
                    <div class="label">Mata Pelajaran</div>
                </div>
                <div class="stat-card" style="border-left-color: #ab47bc;">
                    <h3>Total Arsip</h3>
                    <div class="number" style="color: #ab47bc;"><?php echo $total_arsip; ?></div>
                    <div class="label">File Arsip</div>
                </div>
                <div class="stat-card" style="border-left-color: #26c6da;">
                    <h3>Total Admin</h3>
                    <div class="number" style="color: #26c6da;"><?php echo $total_admin; ?></div>
                    <div class="label">Administrator</div>
                </div>
            </div>

            <!-- Quick Menu -->
            <div class="menu-grid">
                <a href="../add/tambah_siswa.php" class="menu-card">
                    <div class="icon">👥</div>
                    <h3>Tambah Siswa Baru</h3>
                    <p>Daftarkan siswa baru ke dalam sistem</p>
                </a>
                <a href="../add/tambah_guru.php" class="menu-card" style="border-top-color: #38ef7d;">
                    <div class="icon">👨‍🏫</div>
                    <h3>Tambah Guru Baru</h3>
                    <p>Daftarkan guru baru dan buat akun</p>
                </a>
                <a href="../add/tambah_kelas.php" class="menu-card" style="border-top-color: #f5576c;">
                    <div class="icon">🏫</div>
                    <h3>Tambah Kelas Baru</h3>
                    <p>Buat kelas baru dan tentukan wali kelas</p>
                </a>
                <a href="../add/tambah_pelajaran.php" class="menu-card" style="border-top-color: #ffa726;">
                    <div class="icon">📚</div>
                    <h3>Tambah Pelajaran</h3>
                    <p>Tambahkan mata pelajaran baru</p>
                </a>
                <a href="#" class="menu-card" style="border-top-color: #ab47bc;">
                    <div class="icon">📁</div>
                    <h3>Upload Arsip</h3>
                    <p>Upload dokumen dan arsip sekolah</p>
                </a>
                <a href="#" class="menu-card" style="border-top-color: #26c6da;">
                    <div class="icon">📊</div>
                    <h3>Lihat Laporan</h3>
                    <p>Lihat laporan lengkap sistem</p>
                </a>
            </div>
        </main>
    </div>
</body>
</html>