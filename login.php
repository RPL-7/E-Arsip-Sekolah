<?php
session_start();

// Konfigurasi database
$host = 'localhost';
$dbname = 'db_arsipsekolah'; 
$username = 'root'; 
$password = ''; 

header('Content-Type: application/json');

try {
    // Koneksi ke database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ambil data dari form
    $user_type = $_POST['user_type'] ?? '';
    $identifier = $_POST['identifier'] ?? '';
    $password_input = $_POST['password'] ?? '';
    
    // Validasi input
    if (empty($user_type) || empty($identifier) || empty($password_input)) {
        echo json_encode([
            'success' => false,
            'message' => 'Semua field harus diisi!'
        ]);
        exit;
    }
    
    $user_data = null;
    $redirect_url = '';
    
    // Proses login berdasarkan tipe user
    switch ($user_type) {
        case 'siswa':
            // Login untuk siswa menggunakan NIS
            $stmt = $pdo->prepare("
                SELECT id_siswa, nis, nama_siswa, password_login, status 
                FROM user_siswa 
                WHERE nis = :identifier
            ");
            $stmt->execute(['identifier' => $identifier]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                // Cek status siswa
                if ($user_data['status'] !== 'aktif') {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Akun siswa tidak aktif!'
                    ]);
                    exit;
                }
                
                // Verifikasi password
                if (password_verify($password_input, $user_data['password_login'])) {
                    $_SESSION['user_type'] = 'siswa';
                    $_SESSION['user_id'] = $user_data['id_siswa'];
                    $_SESSION['user_nis'] = $user_data['nis'];
                    $_SESSION['user_name'] = $user_data['nama_siswa'];
                    
                    $redirect_url = '/dashboard/dashboard_siswa.php';
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login berhasil! Selamat datang, ' . $user_data['nama_siswa'],
                        'redirect' => $redirect_url
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'NIS atau password salah!'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'NIS tidak ditemukan!'
                ]);
            }
            break;
            
        case 'guru':
            // Login untuk guru menggunakan ID Guru
            $stmt = $pdo->prepare("
                SELECT id_guru, nip, nama_guru, password_login, email, status 
                FROM user_guru 
                WHERE id_guru = :identifier
            ");
            $stmt->execute(['identifier' => $identifier]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                // Cek status guru
                if ($user_data['status'] !== 'aktif') {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Akun guru tidak aktif!'
                    ]);
                    exit;
                }
                
                // Verifikasi password
                if (password_verify($password_input, $user_data['password_login'])) {
                    $_SESSION['user_type'] = 'guru';
                    $_SESSION['user_id'] = $user_data['id_guru'];
                    $_SESSION['user_nip'] = $user_data['nip'];
                    $_SESSION['user_name'] = $user_data['nama_guru'];
                    $_SESSION['user_email'] = $user_data['email'];
                    
                    $redirect_url = '/dashboard/dashboard_guru.php';
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login berhasil! Selamat datang, ' . $user_data['nama_guru'],
                        'redirect' => $redirect_url
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID Guru atau password salah!'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'ID Guru tidak ditemukan!'
                ]);
            }
            break;
            
        case 'admin':
            // Login untuk admin menggunakan username
            $stmt = $pdo->prepare("
                SELECT id_admin, username, nama_admin, email, password 
                FROM admin 
                WHERE username = :identifier
            ");
            $stmt->execute(['identifier' => $identifier]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user_data) {
                // Verifikasi password
                if ($password_input === $user_data['password']) {
                    $_SESSION['user_type'] = 'admin';
                    $_SESSION['user_id'] = $user_data['id_admin'];
                    $_SESSION['user_username'] = $user_data['username'];
                    $_SESSION['user_name'] = $user_data['nama_admin'];
                    $_SESSION['user_email'] = $user_data['email'];
                    
                    $redirect_url = '/dashboard/dashboard_admin.php';
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login berhasil! Selamat datang, ' . $user_data['nama_admin'],
                        'redirect' => $redirect_url
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Username atau password salah!'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Username tidak ditemukan!'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Tipe user tidak valid!'
            ]);
            break;
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Kesalahan database: ' . $e->getMessage()
    ]);
}