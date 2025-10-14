<?php
session_start();
require_once 'config.php';

// Cek apakah user sudah login dan tipe user adalah admin
checkUserType(['admin']);

header('Content-Type: application/json');

try {
    $id_pelajaran = $_GET['id_pelajaran'] ?? null;
    
    if (!$id_pelajaran) {
        throw new Exception("ID Pelajaran tidak valid");
    }
    
    $pdo = getDBConnection();
    
    // Ambil data kelas yang mengajarkan pelajaran ini
    $stmt = $pdo->prepare("
        SELECT k.id_kelas, k.nama_kelas, k.tahun_ajaran, g.nama_guru as nama_wali
        FROM kelas_pelajaran kp
        JOIN kelas k ON kp.id_kelas = k.id_kelas
        LEFT JOIN user_guru g ON k.id_guru_wali = g.id_guru
        WHERE kp.id_pelajaran = ?
        ORDER BY k.tahun_ajaran DESC, k.nama_kelas ASC
    ");
    
    $stmt->execute([$id_pelajaran]);
    $kelas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'kelas' => $kelas
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}