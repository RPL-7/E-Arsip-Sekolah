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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
        }
        
        .navbar h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .logout-btn:hover {
            background: white;
            color: #667eea;
        }
        
        /* Layout */
        .container {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 20px 0;
        }
        
        .menu-item {
            padding: 15px 25px;
            color: #555;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 15px;
        }
        
        .menu-item:hover {
            background: #f8f9fa;
            color: #667eea;
            border-left-color: #667eea;
        }
        
        .menu-item.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, rgba(255,255,255,0) 100%);
            color: #667eea;
            border-left-color: #667eea;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #718096;
            font-size: 14px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #667eea;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #718096;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            font-size: 12px;
            color: #a0aec0;
        }
        
        /* Menu Grid */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .menu-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            cursor: pointer;
            transition: all 0.3s ease;
            border-top: 4px solid #667eea;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.2);
        }
        
        .menu-card .icon {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .menu-card h3 {
            color: #2d3748;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .menu-card p {
            color: #718096;
            font-size: 14px;
            line-height: 1.5;
        }
        
        /* Tables */
        .data-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .data-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .data-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .data-card-body {
            padding: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-size: 13px;
            color: #555;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        
        table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #2d3748;
        }
        
        table tr:last-child td {
            border-bottom: none;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .data-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
            <a href="../manage_siswa.php" class="menu-item">
                <span>👥</span> Manajemen Siswa
            </a>
            <a href="../manage_guru.php" class="menu-item">
                <span>👨‍🏫</span> Manajemen Guru
            </a>
            <a href="../manage_kelas.php" class="menu-item">
                <span>🏫</span> Manajemen Kelas
            </a>
            <a href="#" class="menu-item">
                <span>📚</span> Manajemen Pelajaran
            </a>
            <a href="#" class="menu-item">
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
                <a href="../manage_siswa.php" class="menu-card">
                    <div class="icon">👥</div>
                    <h3>Tambah Siswa Baru</h3>
                    <p>Daftarkan siswa baru ke dalam sistem</p>
                </a>
                <a href="../manage_guru.php" class="menu-card" style="border-top-color: #38ef7d;">
                    <div class="icon">👨‍🏫</div>
                    <h3>Tambah Guru Baru</h3>
                    <p>Daftarkan guru baru dan buat akun</p>
                </a>
                <a href="#" class="menu-card" style="border-top-color: #f5576c;">
                    <div class="icon">🏫</div>
                    <h3>Tambah Kelas Baru</h3>
                    <p>Buat kelas baru dan tentukan wali kelas</p>
                </a>
                <a href="#" class="menu-card" style="border-top-color: #ffa726;">
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

            <!-- Recent Data -->
            <div class="data-section">
                <!-- Siswa Terbaru -->
                <div class="data-card">
                    <div class="data-card-header">
                        👥 Siswa Terdaftar Terakhir
                    </div>
                    <div class="data-card-body">
                        <?php if (count($siswa_terbaru) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa_terbaru as $siswa): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_siswa']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $status_class = 'badge-success';
                                        if ($siswa['status'] == 'nonaktif') $status_class = 'badge-warning';
                                        if ($siswa['status'] == 'lulus') $status_class = 'badge-danger';
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($siswa['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <p>Belum ada data siswa</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Guru Terbaru -->
                <div class="data-card">
                    <div class="data-card-header">
                        👨‍🏫 Guru Terdaftar Terakhir
                    </div>
                    <div class="data-card-body">
                        <?php if (count($guru_terbaru) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guru_terbaru as $guru): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($guru['id_guru']); ?></td>
                                    <td><?php echo htmlspecialchars($guru['nama_guru']); ?></td>
                                    <td><?php echo htmlspecialchars($guru['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $guru['status'] == 'aktif' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo ucfirst($guru['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <p>Belum ada data guru</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>