<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah siswa
checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Koneksi database
$pdo = getDBConnection();

// Ambil data siswa dan kelasnya
$stmt = $pdo->prepare("SELECT id_kelas, nama_siswa, nis FROM user_siswa WHERE id_siswa = ?");
$stmt->execute([$user_id]);
$siswa_data = $stmt->fetch();
$id_kelas = $siswa_data['id_kelas'];
$nama_siswa = $siswa_data['nama_siswa'];
$nis = $siswa_data['nis'];

// Cek apakah siswa punya kelas
if (!$id_kelas) {
    $error_no_class = true;
} else {
    $error_no_class = false;
}

// Ambil semua tugas untuk kelas siswa
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$pelajaran_filter = $_GET['pelajaran'] ?? '';

$query = "
    SELECT t.*, 
           p.nama_pelajaran,
           g.nama_guru,
           CASE 
               WHEN t.deadline IS NOT NULL AND t.deadline < NOW() AND t.status = 'aktif' THEN 'expired'
               ELSE t.status
           END as tugas_status
    FROM tugas t
    JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
    JOIN user_guru g ON t.id_guru = g.id_guru
    WHERE t.id_kelas = ?
";
$params = [$id_kelas];

if (!empty($search)) {
    $query .= " AND (t.judul_tugas LIKE ? OR p.nama_pelajaran LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    if ($status_filter === 'expired') {
        $query .= " AND t.deadline IS NOT NULL AND t.deadline < NOW() AND t.status = 'aktif'";
    } else {
        $query .= " AND t.status = ?";
        $params[] = $status_filter;
    }
}

if (!empty($pelajaran_filter)) {
    $query .= " AND t.id_pelajaran = ?";
    $params[] = $pelajaran_filter;
}

$query .= " ORDER BY 
    CASE 
        WHEN t.deadline IS NOT NULL AND t.deadline < NOW() AND t.status = 'aktif' THEN 1
        WHEN t.status = 'aktif' THEN 2
        ELSE 3
    END,
    t.tanggal_dibuat DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_tugas = $stmt->fetchAll();

// Ambil daftar pelajaran di kelas siswa untuk filter
$stmt = $pdo->prepare("
    SELECT DISTINCT p.id_pelajaran, p.nama_pelajaran
    FROM tugas t
    JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
    WHERE t.id_kelas = ?
    ORDER BY p.nama_pelajaran
");
$stmt->execute([$id_kelas]);
$pelajaran_list = $stmt->fetchAll();

// Statistik
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas WHERE id_kelas = ? AND status = 'aktif'");
$stmt->execute([$id_kelas]);
$total_tugas_aktif = $stmt->fetch()['total'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM tugas 
    WHERE id_kelas = ? AND deadline IS NOT NULL AND deadline < NOW() AND status = 'aktif'
");
$stmt->execute([$id_kelas]);
$tugas_lewat_deadline = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas WHERE id_kelas = ?");
$stmt->execute([$id_kelas]);
$total_semua_tugas = $stmt->fetch()['total'];

function formatSize($bytes) {
    if ($bytes >= 1048576) {
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
    <title>Tugas Saya - Siswa</title>
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
        
        .btn-success {
            background: #38ef7d;
            color: white;
        }
        
        .btn-info {
            background: #26c6da;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
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
        
        .card-body {
            padding: 25px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffa726;
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
        
        .tugas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .tugas-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .tugas-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102,126,234,0.1);
            transform: translateY(-2px);
        }
        
        .tugas-card.expired {
            border-color: #f5576c;
            background: #fff5f7;
        }
        
        .tugas-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .tugas-title h4 {
            color: #2d3748;
            font-size: 18px;
            margin-bottom: 5px;
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
        
        .badge-secondary {
            background: #e2e8f0;
            color: #555;
        }
        
        .tugas-meta {
            font-size: 13px;
            color: #718096;
            margin-bottom: 12px;
        }
        
        .tugas-meta div {
            margin-bottom: 8px;
        }
        
        .tugas-desc {
            color: #2d3748;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
            max-height: 60px;
            overflow: hidden;
        }
        
        .tugas-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
            max-width: 600px;
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
            border-radius: 12px 12px 0 0;
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
        
        .deadline-alert {
            background: #fff3cd;
            border-left: 4px solid #ffa726;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
            color: #856404;
        }
        
        .deadline-alert.expired {
            background: #f8d7da;
            border-left-color: #f5576c;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .tugas-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-bar {
                flex-direction: column;
            }
            
            .filter-bar input {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📝 Tugas Saya</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($nama_siswa); ?></strong> (<?php echo htmlspecialchars($nis); ?>)</span>
            <a href="../dashboard/dashboard_siswa.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <?php if ($error_no_class): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Perhatian!</strong><br>
            Anda belum terdaftar di kelas manapun. Silakan hubungi admin untuk ditambahkan ke kelas.
        </div>
        <?php else: ?>
        
        <div class="page-header">
            <h2>Daftar Tugas</h2>
            <p>Tugas dari guru untuk kelas Anda</p>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Tugas</h3>
                <div class="number"><?php echo $total_semua_tugas; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Tugas Aktif</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $total_tugas_aktif; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f5576c;">
                <h3>Lewat Deadline</h3>
                <div class="number" style="color: #f5576c;"><?php echo $tugas_lewat_deadline; ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card">
            <div class="card-body" style="padding: 20px;">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari judul tugas atau mata pelajaran..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="pelajaran">
                        <option value="">Semua Pelajaran</option>
                        <?php foreach ($pelajaran_list as $pel): ?>
                        <option value="<?php echo $pel['id_pelajaran']; ?>" <?php echo $pelajaran_filter == $pel['id_pelajaran'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pel['nama_pelajaran']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="expired" <?php echo $status_filter === 'expired' ? 'selected' : ''; ?>>Lewat Deadline</option>
                        <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="tertutup" <?php echo $status_filter === 'tertutup' ? 'selected' : ''; ?>>Tertutup</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($pelajaran_filter)): ?>
                    <a href="tugas_siswa.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Daftar Tugas -->
        <?php if (count($all_tugas) > 0): ?>
        <div class="tugas-grid">
            <?php foreach ($all_tugas as $tugas): ?>
            <?php
            $is_expired = $tugas['tugas_status'] === 'expired';
            $status_badge_class = 'badge-success';
            $status_text = 'Aktif';
            
            if ($is_expired) {
                $status_badge_class = 'badge-danger';
                $status_text = 'Lewat Deadline';
            } elseif ($tugas['status'] == 'selesai') {
                $status_badge_class = 'badge-warning';
                $status_text = 'Selesai';
            } elseif ($tugas['status'] == 'tertutup') {
                $status_badge_class = 'badge-secondary';
                $status_text = 'Tertutup';
            }
            ?>
            <div class="tugas-card <?php echo $is_expired ? 'expired' : ''; ?>">
                <div class="tugas-header">
                    <div class="tugas-title">
                        <h4><?php echo htmlspecialchars($tugas['judul_tugas']); ?></h4>
                    </div>
                    <span class="badge <?php echo $status_badge_class; ?>"><?php echo $status_text; ?></span>
                </div>
                
                <div class="tugas-meta">
                    <div>📚 <?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></div>
                    <div>👨‍🏫 <?php echo htmlspecialchars($tugas['nama_guru']); ?></div>
                    <div>📅 Dibuat: <?php echo date('d/m/Y H:i', strtotime($tugas['tanggal_dibuat'])); ?></div>
                    <?php if ($tugas['deadline']): ?>
                    <div style="color: <?php echo $is_expired ? '#f5576c' : '#2d3748'; ?>; font-weight: <?php echo $is_expired ? '600' : 'normal'; ?>">
                        ⏰ Deadline: <?php echo date('d/m/Y H:i', strtotime($tugas['deadline'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($tugas['file_name']): ?>
                    <div>📎 Ada file lampiran</div>
                    <?php endif; ?>
                </div>
                
                <?php if ($tugas['deskripsi']): ?>
                <div class="tugas-desc">
                    <?php echo nl2br(htmlspecialchars($tugas['deskripsi'])); ?>
                </div>
                <?php endif; ?>
                
                <div class="tugas-actions">
                    <button class="btn btn-info btn-sm" onclick="viewTugas(<?php echo htmlspecialchars(json_encode($tugas)); ?>)">
                        👁️ Lihat Detail
                    </button>
                    <?php if ($tugas['file_path']): ?>
                    <a href="<?php echo htmlspecialchars($tugas['file_path']); ?>" target="_blank" class="btn btn-success btn-sm">
                        📥 Download Materi
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 60px 20px; color: #a0aec0;">
                <div style="font-size: 64px; margin-bottom: 20px;">📝</div>
                <h3 style="margin-bottom: 10px; color: #718096;">Belum Ada Tugas</h3>
                <p>Belum ada tugas dari guru untuk kelas Anda</p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>

    <!-- Modal View Detail -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 Detail Tugas</h3>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body" id="viewModalContent">
                <!-- Content akan diisi oleh JavaScript -->
            </div>
        </div>
    </div>

    <script>
        function viewTugas(tugas) {
            const deadline = tugas.deadline ? new Date(tugas.deadline) : null;
            const now = new Date();
            const isExpired = deadline && deadline < now && tugas.status === 'aktif';
            
            let deadlineAlert = '';
            if (deadline) {
                if (isExpired) {
                    deadlineAlert = '<div class="deadline-alert expired"><strong>⏰ DEADLINE TERLEWAT!</strong><br>Batas pengumpulan: ' + deadline.toLocaleString('id-ID') + '</div>';
                } else if (tugas.status === 'aktif') {
                    const timeLeft = deadline - now;
                    const daysLeft = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                    const hoursLeft = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    
                    if (daysLeft === 0 && hoursLeft < 24) {
                        deadlineAlert = '<div class="deadline-alert expired"><strong>⚠️ SEGERA!</strong><br>Deadline: ' + deadline.toLocaleString('id-ID') + ' (' + hoursLeft + ' jam lagi)</div>';
                    } else {
                        deadlineAlert = '<div class="deadline-alert"><strong>⏰ Deadline</strong><br>' + deadline.toLocaleString('id-ID') + ' (' + daysLeft + ' hari lagi)</div>';
                    }
                }
            }
            
            const content = `
                ${deadlineAlert}
                <div style="line-height: 1.8;">
                    <h4 style="color: #2d3748; margin-bottom: 15px; font-size: 20px;">${tugas.judul_tugas}</h4>
                    
                    <div style="margin-bottom: 20px;">
                        <strong style="color: #555;">📚 Mata Pelajaran:</strong> ${tugas.nama_pelajaran}<br>
                        <strong style="color: #555;">👨‍🏫 Guru:</strong> ${tugas.nama_guru}<br>
                        <strong style="color: #555;">📅 Dibuat:</strong> ${new Date(tugas.tanggal_dibuat).toLocaleString('id-ID')}<br>
                        ${deadline ? '<strong style="color: #555;">⏰ Deadline:</strong> ' + deadline.toLocaleString('id-ID') + '<br>' : ''}
                        <strong style="color: #555;">📊 Status:</strong> <span class="badge badge-${isExpired ? 'danger' : tugas.status === 'aktif' ? 'success' : tugas.status === 'selesai' ? 'warning' : 'secondary'}">${isExpired ? 'Lewat Deadline' : tugas.status.charAt(0).toUpperCase() + tugas.status.slice(1)}</span>
                    </div>
                    
                    ${tugas.deskripsi ? '<div style="margin-bottom: 20px;"><strong style="color: #555;">📝 Deskripsi & Instruksi:</strong><div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 8px; white-space: pre-wrap;">' + tugas.deskripsi + '</div></div>' : ''}
                    
                    ${tugas.file_name ? '<div style="margin-bottom: 20px;"><strong style="color: #555;">📎 File Materi/Soal:</strong><br><a href="' + tugas.file_path + '" target="_blank" class="btn btn-success btn-sm" style="margin-top: 8px;">📥 Download ' + tugas.file_name + '</a></div>' : ''}
                </div>
            `;
            document.getElementById('viewModalContent').innerHTML = content;
            document.getElementById('viewModal').classList.add('active');
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target === modal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html>