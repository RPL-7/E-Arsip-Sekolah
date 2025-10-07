<?php
session_start();
require_once 'config.php';

// Cek apakah user sudah login dan tipe user adalah admin
checkUserType(['admin']);

header('Content-Type: application/json');

try {
    $id_kelas = $_GET['id_kelas'] ?? null;
    
    if (!$id_kelas) {
        throw new Exception("ID Kelas tidak valid");
    }
    
    $pdo = getDBConnection();
    
    // Ambil data siswa di kelas ini
    $stmt = $pdo->prepare("
        SELECT nis, nama_siswa, jenis_kelamin, status
        FROM user_siswa
        WHERE id_kelas = ?
        ORDER BY nama_siswa ASC
    ");
    
    $stmt->execute([$id_kelas]);
    $siswa = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'siswa' => $siswa
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}