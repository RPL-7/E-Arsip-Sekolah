<?php
session_start();
require_once 'config.php';

// Cek apakah user sudah login dan tipe user adalah admin
checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$user_username = $_SESSION['user_username'];

// Koneksi database
$pdo = getDBConnection();

// Proses form
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        try {
            $nis = trim($_POST['nis']);
            $nama_siswa = trim($_POST['nama_siswa']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $jenis_kelamin = $_POST['jenis_kelamin'];
            $tanggal_lahir = $_POST['tanggal_lahir'];
            $alamat = trim($_POST['alamat']);
            $no_hp = trim($_POST['no_hp']);
            $id_kelas = $_POST['id_kelas'] ?? null;
            
            // Validasi
            if (empty($nis) || empty($nama_siswa) || empty($password)) {
                throw new Exception("NIS, Nama, dan Password wajib diisi!");
            }
            
            if ($password !== $confirm_password) {
                throw new Exception("Password dan konfirmasi password tidak sama!");
            }
            
            if (strlen($password) < 6) {
                throw new Exception("Password minimal 6 karakter!");
            }
            
            // Cek apakah NIS sudah ada
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_siswa WHERE nis = ?");
            $stmt->execute([$nis]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("NIS sudah terdaftar!");
            }
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert ke database
            $stmt = $pdo->prepare("
                INSERT INTO user_siswa (nis, nama_siswa, password_login, jenis_kelamin, tanggal_lahir, alamat, no_hp, id_kelas, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif')
            ");
            
            $stmt->execute([
                $nis,
                $nama_siswa,
                $password_hash,
                $jenis_kelamin,
                $tanggal_lahir,
                $alamat,
                $no_hp,
                $id_kelas ? $id_kelas : null
            ]);
            
            $success_message = "Akun siswa berhasil dibuat! ID Siswa: " . $pdo->lastInsertId();
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'update') {
        try {
            $id_siswa = $_POST['id_siswa'];
            $nis = trim($_POST['nis']);
            $nama_siswa = trim($_POST['nama_siswa']);
            $jenis_kelamin = $_POST['jenis_kelamin'];
            $tanggal_lahir = $_POST['tanggal_lahir'];
            $alamat = trim($_POST['alamat']);
            $no_hp = trim($_POST['no_hp']);
            $id_kelas = $_POST['id_kelas'] ?? null;
            $status = $_POST['status'];
            
            // Validasi
            if (empty($nis) || empty($nama_siswa)) {
                throw new Exception("NIS dan Nama wajib diisi!");
            }
            
            // Cek apakah NIS sudah digunakan siswa lain
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_siswa WHERE nis = ? AND id_siswa != ?");
            $stmt->execute([$nis, $id_siswa]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("NIS sudah digunakan oleh siswa lain!");
            }
            
            // Update data
            $stmt = $pdo->prepare("
                UPDATE user_siswa 
                SET nis = ?, nama_siswa = ?, jenis_kelamin = ?, 
                    tanggal_lahir = ?, alamat = ?, no_hp = ?, id_kelas = ?, status = ?
                WHERE id_siswa = ?
            ");
            
            $stmt->execute([
                $nis,
                $nama_siswa,
                $jenis_kelamin,
                $tanggal_lahir,
                $alamat,
                $no_hp,
                $id_kelas ? $id_kelas : null,
                $status,
                $id_siswa
            ]);
            
            // Update password jika diisi
            if (!empty($_POST['password'])) {
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($password !== $confirm_password) {
                    throw new Exception("Password dan konfirmasi password tidak sama!");
                }
                
                if (strlen($password) < 6) {
                    throw new Exception("Password minimal 6 karakter!");
                }
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE user_siswa SET password_login = ? WHERE id_siswa = ?");
                $stmt->execute([$password_hash, $id_siswa]);
            }
            
            $success_message = "Data siswa berhasil diupdate!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $id_siswa = $_POST['id_siswa'];
            
            // Hapus siswa
            $stmt = $pdo->prepare("DELETE FROM user_siswa WHERE id_siswa = ?");
            $stmt->execute([$id_siswa]);
            
            $success_message = "Data siswa berhasil dihapus!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Ambil semua data siswa dengan join kelas
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';

$query = "
    SELECT s.*, k.nama_kelas, k.tahun_ajaran
    FROM user_siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.nama_siswa LIKE ? OR s.nis LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $query .= " AND s.status = ?";
    $params[] = $status_filter;
}

if (!empty($kelas_filter)) {
    $query .= " AND s.id_kelas = ?";
    $params[] = $kelas_filter;
}

$query .= " ORDER BY s.id_siswa DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_siswa = $stmt->fetchAll();

// Ambil daftar kelas untuk dropdown
$stmt = $pdo->query("SELECT id_kelas, nama_kelas, tahun_ajaran FROM kelas ORDER BY nama_kelas");
$all_kelas = $stmt->fetchAll();

// Ambil statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'aktif'");
$total_siswa_aktif = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'nonaktif'");
$total_siswa_nonaktif = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'lulus'");
$total_siswa_lulus = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'pindah'");
$total_siswa_pindah = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Siswa - Admin</title>
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
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #38ef7d;
            color: white;
        }
        
        .btn-success:hover {
            background: #2dd36f;
        }
        
        .btn-danger {
            background: #f5576c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e94560;
        }
        
        .btn-warning {
            background: #ffa726;
            color: white;
        }
        
        .btn-warning:hover {
            background: #fb8c00;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
        }
        
        .btn-back:hover {
            background: white;
            color: #667eea;
        }
        
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
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
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .stat-card h3 {
            font-size: 14px;
            color: #718096;
            margin-bottom: 8px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            font-size: 20px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-bar input,
        .filter-bar select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .filter-bar input {
            flex: 1;
            min-width: 250px;
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
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            overflow-y: auto;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            margin: 20px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 20px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-bar input {
                min-width: 100%;
            }
            
            table {
                font-size: 12px;
            }
            
            table th,
            table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>👥 Manajemen Siswa</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="./dashboard/dashboard_admin.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Kelola Data Siswa</h2>
            <p>Tambah, edit, dan hapus data siswa</p>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Siswa</h3>
                <div class="number"><?php echo count($all_siswa); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Siswa Aktif</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $total_siswa_aktif; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Siswa Lulus</h3>
                <div class="number" style="color: #ffa726;"><?php echo $total_siswa_lulus; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f5576c;">
                <h3>Siswa Nonaktif/Pindah</h3>
                <div class="number" style="color: #f5576c;"><?php echo $total_siswa_nonaktif + $total_siswa_pindah; ?></div>
            </div>
        </div>

        <!-- Form Tambah Siswa -->
        <div class="card">
            <div class="card-header">
                <h3>➕ Tambah Siswa Baru</h3>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>NIS <span style="color: red;">*</span></label>
                            <input type="text" name="nis" required placeholder="Masukkan NIS">
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Lengkap <span style="color: red;">*</span></label>
                            <input type="text" name="nama_siswa" required placeholder="Masukkan nama lengkap">
                        </div>
                        
                        <div class="form-group">
                            <label>Jenis Kelamin <span style="color: red;">*</span></label>
                            <select name="jenis_kelamin" required>
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tanggal Lahir <span style="color: red;">*</span></label>
                            <input type="date" name="tanggal_lahir" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nomor HP</label>
                            <input type="text" name="no_hp" placeholder="08xxxxxxxxxx">
                        </div>
                        
                        <div class="form-group">
                            <label>Kelas</label>
                            <select name="id_kelas">
                                <option value="">Pilih Kelas (Opsional)</option>
                                <?php foreach ($all_kelas as $kelas): ?>
                                <option value="<?php echo $kelas['id_kelas']; ?>">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']) . ' - ' . htmlspecialchars($kelas['tahun_ajaran']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Password <span style="color: red;">*</span></label>
                            <input type="password" name="password" required placeholder="Minimal 6 karakter">
                        </div>
                        
                        <div class="form-group">
                            <label>Konfirmasi Password <span style="color: red;">*</span></label>
                            <input type="password" name="confirm_password" required placeholder="Ketik ulang password">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Alamat</label>
                            <textarea name="alamat" placeholder="Masukkan alamat lengkap"></textarea>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">✓ Simpan Data Siswa</button>
                </form>
            </div>
        </div>

        <!-- Daftar Siswa -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Daftar Siswa</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari nama atau NIS..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="kelas">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($all_kelas as $kelas): ?>
                        <option value="<?php echo $kelas['id_kelas']; ?>" <?php echo $kelas_filter == $kelas['id_kelas'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas['nama_kelas']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="nonaktif" <?php echo $status_filter === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                        <option value="lulus" <?php echo $status_filter === 'lulus' ? 'selected' : ''; ?>>Lulus</option>
                        <option value="pindah" <?php echo $status_filter === 'pindah' ? 'selected' : ''; ?>>Pindah</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($kelas_filter)): ?>
                    <a href="manage_siswa.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (count($all_siswa) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>JK</th>
                            <th>No. HP</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_siswa as $siswa): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($siswa['id_siswa']); ?></td>
                            <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
                            <td><?php echo htmlspecialchars($siswa['nama_siswa']); ?></td>
                            <td><?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></td>
                            <td><?php echo $siswa['jenis_kelamin'] === 'L' ? 'L' : 'P'; ?></td>
                            <td><?php echo htmlspecialchars($siswa['no_hp'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $badge_class = 'badge-success';
                                if ($siswa['status'] == 'nonaktif') $badge_class = 'badge-danger';
                                if ($siswa['status'] == 'lulus') $badge_class = 'badge-warning';
                                if ($siswa['status'] == 'pindah') $badge_class = 'badge-info';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($siswa['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-warning btn-sm" onclick="editSiswa(<?php echo htmlspecialchars(json_encode($siswa)); ?>)">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteSiswa(<?php echo $siswa['id_siswa']; ?>, '<?php echo htmlspecialchars($siswa['nama_siswa']); ?>')">
                                        🗑️ Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #a0aec0;">
                    <p>Tidak ada data siswa</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Data Siswa</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_siswa" id="edit_id_siswa">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>NIS <span style="color: red;">*</span></label>
                            <input type="text" name="nis" id="edit_nis" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Lengkap <span style="color: red;">*</span></label>
                            <input type="text" name="nama_siswa" id="edit_nama_siswa" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Jenis Kelamin <span style="color: red;">*</span></label>
                            <select name="jenis_kelamin" id="edit_jenis_kelamin" required>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tanggal Lahir <span style="color: red;">*</span></label>
                            <input type="date" name="tanggal_lahir" id="edit_tanggal_lahir" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nomor HP</label>
                            <input type="text" name="no_hp" id="edit_no_hp">
                        </div>
                        
                        <div class="form-group">
                            <label>Kelas</label>
                            <select name="id_kelas" id="edit_id_kelas">
                                <option value="">Tidak Ada Kelas</option>
                                <?php foreach ($all_kelas as $kelas): ?>
                                <option value="<?php echo $kelas['id_kelas']; ?>">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']) . ' - ' . htmlspecialchars($kelas['tahun_ajaran']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Status <span style="color: red;">*</span></label>
                            <select name="status" id="edit_status" required>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                                <option value="lulus">Lulus</option>
                                <option value="pindah">Pindah</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Alamat</label>
                            <textarea name="alamat" id="edit_alamat"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <hr style="margin: 20px 0;">
                            <p style="color: #718096; font-size: 14px; margin-bottom: 15px;">
                                <strong>Ganti Password</strong> (Kosongkan jika tidak ingin mengubah password)
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="password" id="edit_password" placeholder="Minimal 6 karakter">
                        </div>
                        
                        <div class="form-group">
                            <label>Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" id="edit_confirm_password" placeholder="Ketik ulang password">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">✓ Update Data</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Delete Confirmation -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);">
                <h3>🗑️ Konfirmasi Hapus</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #2d3748;">
                    Apakah Anda yakin ingin menghapus siswa <strong id="delete_nama_siswa"></strong>?
                </p>
                <p style="color: #e53e3e; font-size: 14px; margin-bottom: 20px;">
                    ⚠️ Data yang sudah dihapus tidak dapat dikembalikan!
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_siswa" id="delete_id_siswa">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Edit Modal Functions
        function editSiswa(siswa) {
            document.getElementById('edit_id_siswa').value = siswa.id_siswa;
            document.getElementById('edit_nis').value = siswa.nis;
            document.getElementById('edit_nama_siswa').value = siswa.nama_siswa;
            document.getElementById('edit_jenis_kelamin').value = siswa.jenis_kelamin;
            document.getElementById('edit_tanggal_lahir').value = siswa.tanggal_lahir;
            document.getElementById('edit_no_hp').value = siswa.no_hp || '';
            document.getElementById('edit_id_kelas').value = siswa.id_kelas || '';
            document.getElementById('edit_alamat').value = siswa.alamat || '';
            document.getElementById('edit_status').value = siswa.status;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_confirm_password').value = '';
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Delete Modal Functions
        function deleteSiswa(id, nama) {
            document.getElementById('delete_id_siswa').value = id;
            document.getElementById('delete_nama_siswa').textContent = nama;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
        
        // Auto close alert after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>