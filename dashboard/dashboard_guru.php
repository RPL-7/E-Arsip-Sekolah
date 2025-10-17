<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah guru
checkUserType(['guru']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru</title>
    <link rel="stylesheet" href="../css/dashboard_guru.css">
</head>
<body>
    <div class="navbar">
        <h1>Dashboard Guru</h1>
        <div class="user-info">
            <span>Selamat datang, <strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h2>Selamat Datang di Sistem Sekolah</h2>
            <p>Anda login sebagai guru dengan ID: <strong><?php echo htmlspecialchars($user_id); ?></strong></p>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <h3>👥 Data Siswa</h3>
                <p>Kelola dan lihat data siswa di kelas yang Anda ampu</p>
            </div>
            <div class="info-card">
                <h3>📝 Tugas & Materi</h3>
                <p>Buat dan kelola tugas serta materi pembelajaran</p>
            </div>
            <div class="info-card">
                <h3>📊 Penilaian</h3>
                <p>Input dan kelola nilai siswa</p>
            </div>
            <div class="info-card">
                <h3>📁 Arsip</h3>
                <p>Upload dan kelola arsip pembelajaran</p>
                <a href="../arsip/arsip_guru.php" style="display: inline-block; margin-top: 10px; padding: 8px 16px; background: #11998e; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">Kelola Arsip →</a>
            </div>
            <div class="info-card">
                <h3>🏫 Kelas Wali</h3>
                <p>Kelola kelas yang menjadi wali kelas Anda</p>
            </div>
            <div class="info-card">
                <h3>📅 Jadwal Mengajar</h3>
                <p>Lihat dan kelola jadwal mengajar Anda</p>
            </div>
        </div>
    </div>
</body>
</html>