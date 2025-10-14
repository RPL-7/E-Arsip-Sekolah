<?php
// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_arsipsekolah'); 
define('DB_USER', 'root'); 
define('DB_PASS', ''); 

// Fungsi koneksi database
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Koneksi database gagal: " . $e->getMessage());
    }
}

// Fungsi untuk cek apakah user sudah login
function checkLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        header('Location: login.php');
        exit;
    }
}

// Fungsi untuk cek tipe user tertentu
function checkUserType($allowed_types) {
    checkLogin();
    
    if (!in_array($_SESSION['user_type'], $allowed_types)) {
        header('Location: unauthorized.php');
        exit;
    }
}

// Fungsi untuk logout
function logout() {
    session_start();
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}