<?php
session_start();
require_once '../config.php';

checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$pdo = getDBConnection();

// Proses hapus arsip
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $id_arsip = $_POST['id_arsip'];
        
        // Ambil data arsip
        $stmt = $pdo->prepare("SELECT * FROM arsip WHERE id_arsip = ?");
        $stmt->execute([$id_arsip]);
        $arsip = $stmt->fetch();
        
        if (!$arsip) {
            throw new Exception("Arsip tidak ditemukan!");
        }
        
        // Hapus file fisik
        if (file_exists($arsip['file_path'])) {
            if (!unlink($arsip['file_path'])) {
                throw new Exception("Gagal menghapus file fisik!");
            }
        }
        
        // Hapus dari database
        $stmt = $pdo->prepare("DELETE FROM arsip WHERE id_arsip = ?");
        $stmt->execute([$id_arsip]);
        
        $success_message = "Arsip berhasil dihapus!";
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Filter
$search = $_GET['search'] ?? '';
$tipe_filter = $_GET['tipe'] ?? '';
$uploader_filter = $_GET['uploader'] ?? '';

// Query arsip
$query = "
    SELECT a.*, 
           CASE 
               WHEN a.tipe_uploader = 'guru' THEN g.nama_guru
               WHEN a.tipe_uploader = 'siswa' THEN s.nama_siswa
               ELSE 'Admin'
           END as nama_uploader,
           CASE 
               WHEN a.tipe_uploader = 'guru' THEN g.email
               WHEN a.tipe_uploader = 'siswa' THEN s.nis
               ELSE '-'
           END as info_uploader
    FROM arsip a
    LEFT JOIN user_guru g ON a.tipe_uploader = 'guru' AND a.id_uploader = g.id_guru
    LEFT JOIN user_siswa s ON a.tipe_uploader = 'siswa' AND a.id_uploader = s.id_siswa
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (a.judul_arsip LIKE ? OR a.file_name LIKE ? OR 
                     g.nama_guru LIKE ? OR s.nama_siswa LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($tipe_filter)) {
    $query .= " AND a.tipe_uploader = ?";
    $params[] = $tipe_filter;
}

if (!empty($uploader_filter)) {
    $query .= " AND (g.nama_guru LIKE ? OR s.nama_siswa LIKE ?)";
    $uploader_term = "%$uploader_filter%";
    $params[] = $uploader_term;
    $params[] = $uploader_term;
}

$query .= " ORDER BY a.tanggal_upload DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_arsip = $stmt->fetchAll();

// Statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM arsip");
$total_arsip = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM arsip WHERE tipe_uploader = 'guru'");
$arsip_guru = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM arsip WHERE tipe_uploader = 'siswa'");
$arsip_siswa = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(file_size) as total FROM arsip");
$total_size = $stmt->fetch()['total'] ?? 0;

// Function format size
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Arsip - Admin</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
    <style>
        .main-content { padding: 30px; }
        .page-header { margin-bottom: 30px; }
        .page-header h2 { color: #2d3748; font-size: 28px; margin-bottom: 5px; }
        .page-header p { color: #718096; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea; }
        .stat-card h3 { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #667eea; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .card-body { padding: 25px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-bar input, .filter-bar select { padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .filter-bar input { flex: 1; min-width: 250px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #f5576c; color: white; }
        .btn-info { background: #26c6da; color: white; }
        .btn-success { background: #38ef7d; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; font-weight: 600; color: #2d3748; font-size: 14px; }
        tr:hover { background: #f7fafc; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-guru { background: #d1ecf1; color: #0c5460; }
        .badge-siswa { background: #d4edda; color: #155724; }
        .badge-admin { background: #fff3cd; color: #856404; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 500px; }
        .modal-header { background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0; }
        .modal-header h3 { font-size: 20px; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
        .modal-body { padding: 25px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #a0aec0; }
        @media (max-width: 768px) {
            .table-responsive { font-size: 13px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-content">
            <h1><span>📁</span> Manajemen Arsip</h1>
            <div class="user-info">
                <div class="user-badge"><strong><?php echo htmlspecialchars($user_name); ?></strong></div>
                <a href="../dashboard/dashboard_admin.php" class="logout-btn">← Kembali</a>
            </div>
        </div>
    </div>

    <div class="container">
        <aside class="sidebar">
            <a href="../dashboard/dashboard_admin.php" class="menu-item">
                <span>📊</span> Dashboard
            </a>
            <a href="../manage/manage_siswa.php" class="menu-item">
                <span>👥</span> Manajemen Siswa
            </a>
            <a href="../manage/manage_guru.php" class="menu-item">
                <span>👨‍🏫</span> Manajemen Guru
            </a>
            <a href="../manage/manage_kelas.php" class="menu-item">
                <span>🏫</span> Manajemen Kelas
            </a>
            <a href="../manage/manage_pelajaran.php" class="menu-item">
                <span>📚</span> Manajemen Pelajaran
            </a>
            <a href="manage_arsip.php" class="menu-item active">
                <span>📁</span> Manajemen Arsip
            </a>
        </aside>

        <main class="main-content">
            <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="page-header">
                <h2>Manajemen Arsip Sistem</h2>
                <p>Kelola semua arsip yang diupload oleh guru dan siswa</p>
            </div>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <h3>Total Arsip</h3>
                    <div class="number"><?php echo $total_arsip; ?></div>
                </div>
                <div class="stat-card" style="border-left-color: #26c6da;">
                    <h3>Arsip Guru</h3>
                    <div class="number" style="color: #26c6da;"><?php echo $arsip_guru; ?></div>
                </div>
                <div class="stat-card" style="border-left-color: #38ef7d;">
                    <h3>Arsip Siswa</h3>
                    <div class="number" style="color: #38ef7d;"><?php echo $arsip_siswa; ?></div>
                </div>
                <div class="stat-card" style="border-left-color: #ffa726;">
                    <h3>Total Ukuran</h3>
                    <div class="number" style="color: #ffa726; font-size: 20px;"><?php echo formatSize($total_size); ?></div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card">
                <div class="card-body" style="padding: 20px;">
                    <form method="GET" class="filter-bar">
                        <input type="text" name="search" placeholder="Cari judul, nama file, atau uploader..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="tipe">
                            <option value="">Semua Tipe</option>
                            <option value="guru" <?php echo $tipe_filter === 'guru' ? 'selected' : ''; ?>>Guru</option>
                            <option value="siswa" <?php echo $tipe_filter === 'siswa' ? 'selected' : ''; ?>>Siswa</option>
                        </select>
                        <button type="submit" class="btn btn-primary">🔍 Cari</button>
                        <?php if (!empty($search) || !empty($tipe_filter) || !empty($uploader_filter)): ?>
                        <a href="manage_arsip.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabel Arsip -->
            <div class="card">
                <div class="card-body">
                    <h3 style="margin-bottom: 20px;">Daftar Arsip (<?php echo count($all_arsip); ?>)</h3>
                    
                    <?php if (count($all_arsip) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Judul Arsip</th>
                                    <th>Nama File</th>
                                    <th>Ukuran</th>
                                    <th>Tipe</th>
                                    <th>Diupload Oleh</th>
                                    <th>Tanggal Upload</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_arsip as $idx => $arsip): ?>
                                <?php
                                $badge_class = 'badge-guru';
                                if ($arsip['tipe_uploader'] === 'siswa') $badge_class = 'badge-siswa';
                                elseif ($arsip['tipe_uploader'] === 'admin') $badge_class = 'badge-admin';
                                
                                $icon_map = [
                                    'pdf' => '📄', 'doc' => '📝', 'docx' => '📝',
                                    'xls' => '📊', 'xlsx' => '📊',
                                    'ppt' => '📊', 'pptx' => '📊',
                                    'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
                                    'zip' => '🗜️', 'rar' => '🗜️'
                                ];
                                $icon = $icon_map[$arsip['file_type']] ?? '📁';
                                ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($arsip['judul_arsip']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo $icon; ?> <?php echo htmlspecialchars($arsip['file_name']); ?>
                                    </td>
                                    <td><?php echo formatSize($arsip['file_size']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($arsip['tipe_uploader']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($arsip['nama_uploader']); ?></strong>
                                        <br>
                                        <small style="color: #718096;">
                                            <?php echo htmlspecialchars($arsip['info_uploader']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($arsip['tanggal_upload'])); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" target="_blank" class="btn btn-info btn-sm">
                                            👁️ Lihat
                                        </a>
                                        <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" download class="btn btn-success btn-sm">
                                            📥 Download
                                        </a>
                                        <button class="btn btn-danger btn-sm" onclick="deleteArsip(<?php echo $arsip['id_arsip']; ?>, '<?php echo htmlspecialchars($arsip['judul_arsip']); ?>', '<?php echo htmlspecialchars($arsip['nama_uploader']); ?>')">
                                            🗑️ Hapus
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div style="font-size: 64px; margin-bottom: 20px;">📁</div>
                        <h3 style="margin-bottom: 10px; color: #718096;">Tidak Ada Arsip</h3>
                        <p>Belum ada arsip yang diupload di sistem</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Delete -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Konfirmasi Hapus Arsip</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #2d3748;">
                    Apakah Anda yakin ingin menghapus arsip <strong id="delete_arsip_name"></strong>?
                </p>
                <p style="color: #718096; font-size: 14px; margin-bottom: 15px;">
                    Diupload oleh: <strong id="delete_uploader_name"></strong>
                </p>
                <p style="color: #e53e3e; font-size: 14px; margin-bottom: 20px;">
                    ⚠️ File yang sudah dihapus tidak dapat dikembalikan!
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_arsip" id="delete_id_arsip">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function deleteArsip(id, nama, uploader) {
            document.getElementById('delete_id_arsip').value = id;
            document.getElementById('delete_arsip_name').textContent = nama;
            document.getElementById('delete_uploader_name').textContent = uploader;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
        
        // Auto close alerts
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