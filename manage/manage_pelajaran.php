<?php
session_start();
require_once '../config.php';

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
    
    if ($action === 'update') {
        try {
            $id_pelajaran = $_POST['id_pelajaran'];
            $nama_pelajaran = trim($_POST['nama_pelajaran']);
            $kelas_guru = $_POST['kelas_guru'] ?? [];
            
            // Validasi
            if (empty($nama_pelajaran)) {
                throw new Exception("Nama mata pelajaran wajib diisi!");
            }
            
            // Cek apakah nama pelajaran sudah digunakan pelajaran lain
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pelajaran WHERE nama_pelajaran = ? AND id_pelajaran != ?");
            $stmt->execute([$nama_pelajaran, $id_pelajaran]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Nama mata pelajaran sudah digunakan!");
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update nama pelajaran
            $stmt = $pdo->prepare("UPDATE pelajaran SET nama_pelajaran = ? WHERE id_pelajaran = ?");
            $stmt->execute([$nama_pelajaran, $id_pelajaran]);
            
            // Hapus semua relasi kelas lama
            $stmt = $pdo->prepare("DELETE FROM kelas_pelajaran WHERE id_pelajaran = ?");
            $stmt->execute([$id_pelajaran]);
            
            // Insert relasi kelas baru dengan guru
            if (!empty($kelas_guru)) {
                $stmt = $pdo->prepare("INSERT INTO kelas_pelajaran (id_kelas, id_pelajaran, id_guru) VALUES (?, ?, ?)");
                foreach ($kelas_guru as $id_kelas => $id_guru) {
                    $guru_id = !empty($id_guru) ? $id_guru : null;
                    $stmt->execute([$id_kelas, $id_pelajaran, $guru_id]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success_message = "Data mata pelajaran berhasil diupdate!";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $id_pelajaran = $_POST['id_pelajaran'];
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Hapus relasi di kelas_pelajaran
            $stmt = $pdo->prepare("DELETE FROM kelas_pelajaran WHERE id_pelajaran = ?");
            $stmt->execute([$id_pelajaran]);
            
            // Hapus pelajaran
            $stmt = $pdo->prepare("DELETE FROM pelajaran WHERE id_pelajaran = ?");
            $stmt->execute([$id_pelajaran]);
            
            // Commit transaction
            $pdo->commit();
            
            $success_message = "Mata pelajaran berhasil dihapus!";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
}

// Ambil semua data pelajaran dengan jumlah kelas
$search = $_GET['search'] ?? '';

$query = "
    SELECT p.*, 
           COUNT(DISTINCT kp.id_kelas) as jumlah_kelas
    FROM pelajaran p
    LEFT JOIN kelas_pelajaran kp ON p.id_pelajaran = kp.id_pelajaran
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND p.nama_pelajaran LIKE ?";
    $params[] = "%$search%";
}

$query .= " GROUP BY p.id_pelajaran ORDER BY p.nama_pelajaran ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_pelajaran = $stmt->fetchAll();

// Ambil daftar kelas untuk dropdown
$stmt = $pdo->query("SELECT id_kelas, nama_kelas, tahun_ajaran FROM kelas ORDER BY tahun_ajaran DESC, nama_kelas ASC");
$all_kelas = $stmt->fetchAll();

// Ambil daftar guru aktif
$stmt = $pdo->query("SELECT id_guru, nama_guru FROM user_guru WHERE status = 'aktif' ORDER BY nama_guru ASC");
$all_guru = $stmt->fetchAll();

// Ambil statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pelajaran");
$total_pelajaran = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT id_pelajaran) as total FROM kelas_pelajaran");
$pelajaran_diajarkan = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas");
$total_kelas = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pelajaran - Admin</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .page-header-text h2 {
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .page-header-text p {
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
        }
        
        .card-header h3 {
            font-size: 20px;
        }
        
        .card-body {
            padding: 25px;
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
        
        .filter-bar input {
            flex: 1;
            min-width: 250px;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
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
            overflow-y: auto;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .kelas-selection {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .kelas-item {
            display: grid;
            grid-template-columns: auto 2fr 3fr;
            gap: 15px;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .kelas-item:last-child {
            border-bottom: none;
        }
        
        .kelas-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .kelas-item label {
            cursor: pointer;
            margin: 0;
            font-weight: normal;
        }
        
        .kelas-item .kelas-label {
            font-weight: 500;
            color: #2d3748;
        }
        
        .kelas-item select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .kelas-item select:disabled {
            background: #f8f9fa;
            color: #a0aec0;
            cursor: not-allowed;
        }
        
        .kelas-header {
            display: grid;
            grid-template-columns: auto 2fr 3fr;
            gap: 15px;
            padding: 10px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 13px;
            color: #555;
        }
        
        .select-all {
            background: #f8f9fa;
            padding: 8px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        
        .select-all input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📚 Manajemen Pelajaran</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="../dashboard/dashboard_admin.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <div class="page-header-text">
                <h2>Data Mata Pelajaran</h2>
                <p>Kelola mata pelajaran dan kelas yang mengajarkan</p>
            </div>
            <a href="tambah_pelajaran.php" class="btn btn-success">➕ Tambah Pelajaran Baru</a>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Mata Pelajaran</h3>
                <div class="number"><?php echo $total_pelajaran; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Pelajaran Diajarkan</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $pelajaran_diajarkan; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Total Kelas</h3>
                <div class="number" style="color: #ffa726;"><?php echo $total_kelas; ?></div>
            </div>
        </div>

        <!-- Daftar Pelajaran -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Daftar Mata Pelajaran</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari nama mata pelajaran..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search)): ?>
                    <a href="manage_pelajaran.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (count($all_pelajaran) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Mata Pelajaran</th>
                            <th>Jumlah Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_pelajaran as $pelajaran): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pelajaran['id_pelajaran']); ?></td>
                            <td><strong><?php echo htmlspecialchars($pelajaran['nama_pelajaran']); ?></strong></td>
                            <td>
                                <span class="badge <?php echo $pelajaran['jumlah_kelas'] > 0 ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo $pelajaran['jumlah_kelas']; ?> Kelas
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-info btn-sm" onclick="viewKelas(<?php echo $pelajaran['id_pelajaran']; ?>, '<?php echo htmlspecialchars($pelajaran['nama_pelajaran']); ?>')">
                                        🏫 Lihat Kelas
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="editPelajaran(<?php echo htmlspecialchars(json_encode($pelajaran)); ?>)">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deletePelajaran(<?php echo $pelajaran['id_pelajaran']; ?>, '<?php echo htmlspecialchars($pelajaran['nama_pelajaran']); ?>')">
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
                    <p>Tidak ada data mata pelajaran</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Mata Pelajaran</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_pelajaran" id="edit_id_pelajaran">
                    
                    <div class="form-group">
                        <label>Nama Mata Pelajaran <span style="color: red;">*</span></label>
                        <input type="text" name="nama_pelajaran" id="edit_nama_pelajaran" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Pilih Kelas dan Guru Pengajar</label>
                        <div class="kelas-selection">
                            <div class="select-all">
                                <input type="checkbox" id="edit_select_all" onclick="toggleEditSelectAll(this)">
                                <label for="edit_select_all">Pilih Semua Kelas</label>
                            </div>
                            
                            <div class="kelas-header">
                                <div>Pilih</div>
                                <div style="grid-column: span 2;">Kelas</div>
                                <div style="grid-column: span 2;">Guru Pengajar</div>
                            </div>
                            
                            <?php foreach ($all_kelas as $kelas): ?>
                            <div class="kelas-item">
                                <input type="checkbox" 
                                       class="edit-kelas-checkbox" 
                                       id="edit_kelas_<?php echo $kelas['id_kelas']; ?>"
                                       onchange="toggleEditGuruSelect(<?php echo $kelas['id_kelas']; ?>)">
                                
                                <label for="edit_kelas_<?php echo $kelas['id_kelas']; ?>" class="kelas-label" style="grid-column: span 2;">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']) . ' - ' . htmlspecialchars($kelas['tahun_ajaran']); ?>
                                </label>
                                
                                <select name="kelas_guru[<?php echo $kelas['id_kelas']; ?>]" 
                                        id="edit_guru_<?php echo $kelas['id_kelas']; ?>"
                                        style="grid-column: span 2;" 
                                        disabled>
                                    <option value="">-- Pilih Guru Pengajar --</option>
                                    <?php foreach ($all_guru as $guru): ?>
                                    <option value="<?php echo $guru['id_guru']; ?>">
                                        <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                                <label for="edit_select_all">Pilih Semua Kelas</label>
                            </div>
                            
                            <?php foreach ($all_kelas as $kelas): ?>
                            <div class="kelas-item">
                                <input type="checkbox" name="kelas[]" value="<?php echo $kelas['id_kelas']; ?>" id="edit_kelas_<?php echo $kelas['id_kelas']; ?>" class="edit-kelas-checkbox">
                                <label for="edit_kelas_<?php echo $kelas['id_kelas']; ?>">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']) . ' - ' . htmlspecialchars($kelas['tahun_ajaran']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
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

    <!-- Modal Delete -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);">
                <h3>🗑️ Konfirmasi Hapus</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #2d3748;">
                    Apakah Anda yakin ingin menghapus mata pelajaran <strong id="delete_nama_pelajaran"></strong>?
                </p>
                <p style="color: #e53e3e; font-size: 14px; margin-bottom: 20px;">
                    ⚠️ Data yang sudah dihapus tidak dapat dikembalikan!
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_pelajaran" id="delete_id_pelajaran">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal View Kelas -->
    <div class="modal" id="viewKelasModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🏫 Kelas yang Mengajarkan - <span id="view_nama_pelajaran"></span></h3>
                <button class="modal-close" onclick="closeViewKelasModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="kelas_list_content">
                    <div style="text-align: center; padding: 20px;">
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editPelajaran(pelajaran) {
            document.getElementById('edit_id_pelajaran').value = pelajaran.id_pelajaran;
            document.getElementById('edit_nama_pelajaran').value = pelajaran.nama_pelajaran;
            
            // Reset semua checkbox dan select
            const checkboxes = document.querySelectorAll('.edit-kelas-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = false;
                const idKelas = cb.id.replace('edit_kelas_', '');
                document.getElementById('edit_guru_' + idKelas).disabled = true;
                document.getElementById('edit_guru_' + idKelas).value = '';
            });
            
            // Ambil kelas dan guru yang sudah dipilih
            fetch('get_kelas_pelajaran.php?id_pelajaran=' + pelajaran.id_pelajaran)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.kelas) {
                        data.kelas.forEach(kelas => {
                            const checkbox = document.getElementById('edit_kelas_' + kelas.id_kelas);
                            if (checkbox) {
                                checkbox.checked = true;
                                const select = document.getElementById('edit_guru_' + kelas.id_kelas);
                                select.disabled = false;
                                if (kelas.id_guru) {
                                    select.value = kelas.id_guru;
                                }
                            }
                        });
                    }
                });
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function toggleEditSelectAll(checkbox) {
            const kelasCheckboxes = document.querySelectorAll('.edit-kelas-checkbox');
            kelasCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                const idKelas = cb.id.replace('edit_kelas_', '');
                toggleEditGuruSelect(idKelas);
            });
        }
        
        function toggleEditGuruSelect(idKelas) {
            const checkbox = document.getElementById('edit_kelas_' + idKelas);
            const select = document.getElementById('edit_guru_' + idKelas);
            
            if (checkbox.checked) {
                select.disabled = false;
                select.style.background = 'white';
            } else {
                select.disabled = true;
                select.value = '';
                select.style.background = '#f8f9fa';
            }
        }
        
        function deletePelajaran(id, nama) {
            document.getElementById('delete_id_pelajaran').value = id;
            document.getElementById('delete_nama_pelajaran').textContent = nama;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        function viewKelas(idPelajaran, namaPelajaran) {
            document.getElementById('view_nama_pelajaran').textContent = namaPelajaran;
            document.getElementById('viewKelasModal').classList.add('active');
            
            fetch('get_kelas_pelajaran.php?id_pelajaran=' + idPelajaran)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '';
                        if (data.kelas.length > 0) {
                            html = '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<thead><tr>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">No</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Nama Kelas</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Tahun Ajaran</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Guru Pengajar</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Wali Kelas</th>';
                            html += '</tr></thead><tbody>';
                            
                            data.kelas.forEach((kelas, index) => {
                                html += '<tr style="border-bottom: 1px solid #e2e8f0;">';
                                html += '<td style="padding: 12px;">' + (index + 1) + '</td>';
                                html += '<td style="padding: 12px;"><strong>' + kelas.nama_kelas + '</strong></td>';
                                html += '<td style="padding: 12px;">' + kelas.tahun_ajaran + '</td>';
                                html += '<td style="padding: 12px;">';
                                if (kelas.nama_guru_pengajar) {
                                    html += '<span style="background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">👨‍🏫 ' + kelas.nama_guru_pengajar + '</span>';
                                } else {
                                    html += '<span style="color: #a0aec0;">Belum ditentukan</span>';
                                }
                                html += '</td>';
                                html += '<td style="padding: 12px;">' + (kelas.nama_wali || '-') + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                        } else {
                            html = '<div style="text-align: center; padding: 40px; color: #a0aec0;"><p>Belum ada kelas yang mengajarkan mata pelajaran ini</p></div>';
                        }
                        document.getElementById('kelas_list_content').innerHTML = html;
                    } else {
                        document.getElementById('kelas_list_content').innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;"><p>Error: ' + data.message + '</p></div>';
                    }
                })
                .catch(error => {
                    document.getElementById('kelas_list_content').innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;"><p>Terjadi kesalahan saat memuat data</p></div>';
                });
        }
        
        function closeViewKelasModal() {
            document.getElementById('viewKelasModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            const viewKelasModal = document.getElementById('viewKelasModal');
            
            if (event.target === editModal) closeEditModal();
            if (event.target === deleteModal) closeDeleteModal();
            if (event.target === viewKelasModal) closeViewKelasModal();
        }
        
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