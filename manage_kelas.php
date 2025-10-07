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
            $nama_kelas = trim($_POST['nama_kelas']);
            $tahun_ajaran = trim($_POST['tahun_ajaran']);
            $id_guru_wali = $_POST['id_guru_wali'] ?? null;
            
            // Validasi
            if (empty($nama_kelas) || empty($tahun_ajaran)) {
                throw new Exception("Nama kelas dan tahun ajaran wajib diisi!");
            }
            
            // Cek apakah nama kelas dan tahun ajaran sudah ada
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE nama_kelas = ? AND tahun_ajaran = ?");
            $stmt->execute([$nama_kelas, $tahun_ajaran]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Kelas dengan nama dan tahun ajaran yang sama sudah ada!");
            }
            
            // Jika ada guru wali, cek apakah guru sudah menjadi wali kelas lain di tahun ajaran yang sama
            if ($id_guru_wali) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE id_guru_wali = ? AND tahun_ajaran = ?");
                $stmt->execute([$id_guru_wali, $tahun_ajaran]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Guru ini sudah menjadi wali kelas lain di tahun ajaran yang sama!");
                }
            }
            
            // Insert ke database
            $stmt = $pdo->prepare("
                INSERT INTO kelas (nama_kelas, tahun_ajaran, id_guru_wali)
                VALUES (?, ?, ?)
            ");
            
            $stmt->execute([
                $nama_kelas,
                $tahun_ajaran,
                $id_guru_wali ? $id_guru_wali : null
            ]);
            
            $success_message = "Kelas berhasil dibuat! ID Kelas: " . $pdo->lastInsertId();
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'update') {
        try {
            $id_kelas = $_POST['id_kelas'];
            $nama_kelas = trim($_POST['nama_kelas']);
            $tahun_ajaran = trim($_POST['tahun_ajaran']);
            $id_guru_wali = $_POST['id_guru_wali'] ?? null;
            
            // Validasi
            if (empty($nama_kelas) || empty($tahun_ajaran)) {
                throw new Exception("Nama kelas dan tahun ajaran wajib diisi!");
            }
            
            // Cek apakah nama kelas dan tahun ajaran sudah digunakan kelas lain
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE nama_kelas = ? AND tahun_ajaran = ? AND id_kelas != ?");
            $stmt->execute([$nama_kelas, $tahun_ajaran, $id_kelas]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Nama kelas dan tahun ajaran sudah digunakan oleh kelas lain!");
            }
            
            // Jika ada guru wali, cek apakah guru sudah menjadi wali kelas lain di tahun ajaran yang sama
            if ($id_guru_wali) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE id_guru_wali = ? AND tahun_ajaran = ? AND id_kelas != ?");
                $stmt->execute([$id_guru_wali, $tahun_ajaran, $id_kelas]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception("Guru ini sudah menjadi wali kelas lain di tahun ajaran yang sama!");
                }
            }
            
            // Update data
            $stmt = $pdo->prepare("
                UPDATE kelas 
                SET nama_kelas = ?, tahun_ajaran = ?, id_guru_wali = ?
                WHERE id_kelas = ?
            ");
            
            $stmt->execute([
                $nama_kelas,
                $tahun_ajaran,
                $id_guru_wali ? $id_guru_wali : null,
                $id_kelas
            ]);
            
            $success_message = "Data kelas berhasil diupdate!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $id_kelas = $_POST['id_kelas'];
            
            // Cek apakah ada siswa di kelas ini
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_siswa WHERE id_kelas = ?");
            $stmt->execute([$id_kelas]);
            $jumlah_siswa = $stmt->fetchColumn();
            
            if ($jumlah_siswa > 0) {
                throw new Exception("Tidak dapat menghapus kelas! Masih ada $jumlah_siswa siswa di kelas ini. Pindahkan atau hapus siswa terlebih dahulu.");
            }
            
            // Hapus kelas
            $stmt = $pdo->prepare("DELETE FROM kelas WHERE id_kelas = ?");
            $stmt->execute([$id_kelas]);
            
            $success_message = "Kelas berhasil dihapus!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Ambil semua data kelas dengan join guru wali dan hitung jumlah siswa
$search = $_GET['search'] ?? '';
$tahun_filter = $_GET['tahun'] ?? '';

$query = "
    SELECT k.*, 
           g.nama_guru as nama_wali,
           (SELECT COUNT(*) FROM user_siswa WHERE id_kelas = k.id_kelas) as jumlah_siswa
    FROM kelas k
    LEFT JOIN user_guru g ON k.id_guru_wali = g.id_guru
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (k.nama_kelas LIKE ? OR g.nama_guru LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($tahun_filter)) {
    $query .= " AND k.tahun_ajaran = ?";
    $params[] = $tahun_filter;
}

$query .= " ORDER BY k.tahun_ajaran DESC, k.nama_kelas ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_kelas = $stmt->fetchAll();

// Ambil daftar guru aktif untuk dropdown
$stmt = $pdo->query("SELECT id_guru, nama_guru FROM user_guru WHERE status = 'aktif' ORDER BY nama_guru");
$all_guru = $stmt->fetchAll();

// Ambil daftar tahun ajaran yang unik untuk filter
$stmt = $pdo->query("SELECT DISTINCT tahun_ajaran FROM kelas ORDER BY tahun_ajaran DESC");
$all_tahun_ajaran = $stmt->fetchAll();

// Ambil statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas");
$total_kelas = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas WHERE id_guru_wali IS NOT NULL");
$kelas_dengan_wali = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas WHERE id_guru_wali IS NULL");
$kelas_tanpa_wali = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE id_kelas IS NOT NULL");
$total_siswa_terdaftar = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kelas - Admin</title>
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
        
        .btn-info {
            background: #26c6da;
            color: white;
        }
        
        .btn-info:hover {
            background: #00acc1;
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
            grid-template-columns: repeat(3, 1fr);
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
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
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
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
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
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .info-box p {
            color: #424242;
            font-size: 14px;
            line-height: 1.6;
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
        <h1>🏫 Manajemen Kelas</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="/dashboard/dashboard_admin.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Kelola Data Kelas</h2>
            <p>Tambah, edit, dan hapus data kelas serta tetapkan wali kelas</p>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Kelas</h3>
                <div class="number"><?php echo $total_kelas; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Kelas dengan Wali</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $kelas_dengan_wali; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Kelas Tanpa Wali</h3>
                <div class="number" style="color: #ffa726;"><?php echo $kelas_tanpa_wali; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #26c6da;">
                <h3>Total Siswa Terdaftar</h3>
                <div class="number" style="color: #26c6da;"><?php echo $total_siswa_terdaftar; ?></div>
            </div>
        </div>

        <!-- Form Tambah Kelas -->
        <div class="card">
            <div class="card-header">
                <h3>➕ Tambah Kelas Baru</h3>
            </div>
            <div class="card-body">
                <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <div class="info-box">
                    <h4>ℹ️ Informasi</h4>
                    <p>
                        <strong>Nama Kelas:</strong> Contoh: X-1, XI IPA 1, XII IPS 2<br>
                        <strong>Tahun Ajaran:</strong> Format: 2024/2025<br>
                        <strong>Wali Kelas:</strong> Satu guru hanya bisa menjadi wali di satu kelas per tahun ajaran
                    </p>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nama Kelas <span style="color: red;">*</span></label>
                            <input type="text" name="nama_kelas" required placeholder="Contoh: X-1">
                        </div>
                        
                        <div class="form-group">
                            <label>Tahun Ajaran <span style="color: red;">*</span></label>
                            <input type="text" name="tahun_ajaran" required placeholder="Contoh: 2024/2025">
                        </div>
                        
                        <div class="form-group">
                            <label>Wali Kelas (Opsional)</label>
                            <select name="id_guru_wali">
                                <option value="">Belum Ada Wali Kelas</option>
                                <?php foreach ($all_guru as $guru): ?>
                                <option value="<?php echo $guru['id_guru']; ?>">
                                    <?php echo htmlspecialchars($guru['nama_guru']); ?> (ID: <?php echo $guru['id_guru']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">✓ Simpan Data Kelas</button>
                </form>
            </div>
        </div>

        <!-- Daftar Kelas -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Daftar Kelas</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari nama kelas atau wali kelas..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="tahun">
                        <option value="">Semua Tahun Ajaran</option>
                        <?php foreach ($all_tahun_ajaran as $tahun): ?>
                        <option value="<?php echo $tahun['tahun_ajaran']; ?>" <?php echo $tahun_filter == $tahun['tahun_ajaran'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tahun['tahun_ajaran']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($tahun_filter)): ?>
                    <a href="manage_kelas.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (count($all_kelas) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Kelas</th>
                            <th>Tahun Ajaran</th>
                            <th>Wali Kelas</th>
                            <th>Jumlah Siswa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_kelas as $kelas): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($kelas['id_kelas']); ?></td>
                            <td><strong><?php echo htmlspecialchars($kelas['nama_kelas']); ?></strong></td>
                            <td><?php echo htmlspecialchars($kelas['tahun_ajaran']); ?></td>
                            <td>
                                <?php if ($kelas['nama_wali']): ?>
                                    <span class="badge badge-success">
                                        <?php echo htmlspecialchars($kelas['nama_wali']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Belum Ada Wali</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo $kelas['jumlah_siswa']; ?> Siswa
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-info btn-sm" onclick="viewSiswa(<?php echo $kelas['id_kelas']; ?>, '<?php echo htmlspecialchars($kelas['nama_kelas']); ?>')">
                                        👥 Lihat Siswa
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="editKelas(<?php echo htmlspecialchars(json_encode($kelas)); ?>)">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteKelas(<?php echo $kelas['id_kelas']; ?>, '<?php echo htmlspecialchars($kelas['nama_kelas']); ?>', <?php echo $kelas['jumlah_siswa']; ?>)">
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
                    <p>Tidak ada data kelas</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Data Kelas</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_kelas" id="edit_id_kelas">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nama Kelas <span style="color: red;">*</span></label>
                            <input type="text" name="nama_kelas" id="edit_nama_kelas" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Tahun Ajaran <span style="color: red;">*</span></label>
                            <input type="text" name="tahun_ajaran" id="edit_tahun_ajaran" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Wali Kelas</label>
                            <select name="id_guru_wali" id="edit_id_guru_wali">
                                <option value="">Tidak Ada Wali Kelas</option>
                                <?php foreach ($all_guru as $guru): ?>
                                <option value="<?php echo $guru['id_guru']; ?>">
                                    <?php echo htmlspecialchars($guru['nama_guru']); ?> (ID: <?php echo $guru['id_guru']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
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
                    Apakah Anda yakin ingin menghapus kelas <strong id="delete_nama_kelas"></strong>?
                </p>
                <p id="delete_warning" style="color: #e53e3e; font-size: 14px; margin-bottom: 20px;">
                    ⚠️ Data yang sudah dihapus tidak dapat dikembalikan!
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_kelas" id="delete_id_kelas">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal View Siswa -->
    <div class="modal" id="viewSiswaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>👥 Daftar Siswa - <span id="view_nama_kelas"></span></h3>
                <button class="modal-close" onclick="closeViewSiswaModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="siswa_list_content">
                    <div style="text-align: center; padding: 20px;">
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Edit Modal Functions
        function editKelas(kelas) {
            document.getElementById('edit_id_kelas').value = kelas.id_kelas;
            document.getElementById('edit_nama_kelas').value = kelas.nama_kelas;
            document.getElementById('edit_tahun_ajaran').value = kelas.tahun_ajaran;
            document.getElementById('edit_id_guru_wali').value = kelas.id_guru_wali || '';
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Delete Modal Functions
        function deleteKelas(id, nama, jumlahSiswa) {
            document.getElementById('delete_id_kelas').value = id;
            document.getElementById('delete_nama_kelas').textContent = nama;
            
            const warningEl = document.getElementById('delete_warning');
            if (jumlahSiswa > 0) {
                warningEl.innerHTML = `⚠️ <strong>PERINGATAN:</strong> Kelas ini memiliki ${jumlahSiswa} siswa. Anda harus memindahkan atau menghapus siswa terlebih dahulu!`;
                warningEl.style.color = '#e53e3e';
                warningEl.style.fontWeight = 'bold';
            } else {
                warningEl.innerHTML = '⚠️ Data yang sudah dihapus tidak dapat dikembalikan!';
                warningEl.style.color = '#e53e3e';
                warningEl.style.fontWeight = 'normal';
            }
            
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // View Siswa Modal Functions
        function viewSiswa(idKelas, namaKelas) {
            document.getElementById('view_nama_kelas').textContent = namaKelas;
            document.getElementById('viewSiswaModal').classList.add('active');
            
            // Fetch data siswa dengan AJAX
            fetch('get_siswa_kelas.php?id_kelas=' + idKelas)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '';
                        if (data.siswa.length > 0) {
                            html = '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<thead><tr>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">No</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">NIS</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Nama Siswa</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">JK</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Status</th>';
                            html += '</tr></thead><tbody>';
                            
                            data.siswa.forEach((siswa, index) => {
                                let badgeClass = 'badge-success';
                                if (siswa.status === 'nonaktif') badgeClass = 'badge-danger';
                                if (siswa.status === 'lulus') badgeClass = 'badge-warning';
                                if (siswa.status === 'pindah') badgeClass = 'badge-info';
                                
                                html += '<tr style="border-bottom: 1px solid #e2e8f0;">';
                                html += '<td style="padding: 12px;">' + (index + 1) + '</td>';
                                html += '<td style="padding: 12px;">' + siswa.nis + '</td>';
                                html += '<td style="padding: 12px;">' + siswa.nama_siswa + '</td>';
                                html += '<td style="padding: 12px;">' + (siswa.jenis_kelamin === 'L' ? 'L' : 'P') + '</td>';
                                html += '<td style="padding: 12px;"><span class="badge ' + badgeClass + '">' + siswa.status.charAt(0).toUpperCase() + siswa.status.slice(1) + '</span></td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                        } else {
                            html = '<div style="text-align: center; padding: 40px; color: #a0aec0;"><p>Belum ada siswa di kelas ini</p></div>';
                        }
                        document.getElementById('siswa_list_content').innerHTML = html;
                    } else {
                        document.getElementById('siswa_list_content').innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;"><p>Error: ' + data.message + '</p></div>';
                    }
                })
                .catch(error => {
                    document.getElementById('siswa_list_content').innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;"><p>Terjadi kesalahan saat memuat data</p></div>';
                });
        }
        
        function closeViewSiswaModal() {
            document.getElementById('viewSiswaModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            const viewSiswaModal = document.getElementById('viewSiswaModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            if (event.target === viewSiswaModal) {
                closeViewSiswaModal();
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