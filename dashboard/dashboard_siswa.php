<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah siswa
checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_nis = $_SESSION['user_nis'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa</title>
    <link rel="stylesheet" href="../css/dashboard_siswa.css">
</head>
<body>
    <div class="navbar">
        <h1>Dashboard Siswa</h1>
        <div class="user-info">
            <span>Selamat datang, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h2>Selamat Datang di Sistem Sekolah</h2>
            <p>Anda login sebagai siswa dengan NIS: <strong><?php echo htmlspecialchars($user_nis); ?></strong></p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>📚 Profil Saya</h3>
                <p>Kelola informasi profil dan data pribadi Anda</p>
                <a href="../profil/profil_siswa.php" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">Lihat Profil →</a>
            </div>
            <div class="info-card">
                <h3>📝 Tugas</h3>
                <p>Lihat dan kerjakan tugas yang diberikan oleh guru</p>
                <a href="../tugas/tugas_siswa.php" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">Lihat Tugas →</a>
            </div>
            <div class="info-card">
                <h3>📊 Nilai</h3>
                <p>Lihat nilai dan hasil evaluasi pembelajaran</p>
                <a href="../nilai/nilai_siswa.php" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">Lihat Nilai →</a>
            </div>
            <div class="info-card">
                <h3>📁 Arsip</h3>
                <p>Akses materi dan arsip pembelajaran</p>
                <a href="../arsip/arsip_siswa.php" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">Lihat Arsip →</a>
            </div>
        </div>
    </div>
</body>
</html>