<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah siswa
checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Koneksi database
$pdo = getDBConnection();

// Ambil data siswa
$stmt = $pdo->prepare("SELECT id_kelas, nama_siswa, nis FROM user_siswa WHERE id_siswa = ?");
$stmt->execute([$user_id]);
$siswa_data = $stmt->fetch();
$id_kelas = $siswa_data['id_kelas'];
$nama_siswa = $siswa_data['nama_siswa'];
$nis = $siswa_data['nis'];

// Filter
$search = $_GET['search'] ?? '';
$tipe_filter = $_GET['tipe'] ?? '';

// Ambil semua arsip yang diupload oleh guru
$query = "
    SELECT a.*, g.nama_guru
    FROM arsip a
    JOIN user_guru g ON a.id_uploader = g.id_guru
    WHERE a.tipe_uploader = 'guru'
";
$params = [];

if (!empty($search)) {
    $query .= " AND (a.judul_arsip LIKE ? OR a.file_name LIKE ? OR g.nama_guru LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($tipe_filter)) {
    $query .= " AND a.file_type = ?";
    $params[] = $tipe_filter;
}

$query .= " ORDER BY a.tanggal_upload DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_arsip = $stmt->fetchAll();

// Hitung total ukuran file
$stmt = $pdo->prepare("SELECT COUNT(*) as total_files, SUM(file_size) as total_size FROM arsip WHERE tipe_uploader = 'guru'");
$stmt->execute();
$stats = $stmt->fetch();
$total_files = $stats['total_files'];
$total_size = $stats['total_size'] ?? 0;

// Ambil daftar tipe file untuk filter
$stmt = $pdo->prepare("SELECT DISTINCT file_type FROM arsip WHERE tipe_uploader = 'guru' ORDER BY file_type");
$stmt->execute();
$file_types = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Function untuk format ukuran file
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
    <title>Arsip Pembelajaran - Siswa</title>
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
        }
        
        .card-header h3 {
            font-size: 20px;
        }
        
        .card-body {
            padding: 25px;
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
        
        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .file-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .file-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102,126,234,0.1);
            transform: translateY(-2px);
        }
        
        .file-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .file-info h4 {
            color: #2d3748;
            font-size: 16px;
            margin-bottom: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .file-meta {
            font-size: 13px;
            color: #718096;
            margin-bottom: 5px;
        }
        
        .file-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .info-box p {
            color: #1976d2;
            font-size: 14px;
            line-height: 1.6;
        }
        
        @media (max-width: 768px) {
            .file-grid {
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
        <h1>📚 Arsip Pembelajaran</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($nama_siswa); ?></strong> (<?php echo htmlspecialchars($nis); ?>)</span>
            <a href="../dashboard/dashboard_siswa.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Arsip & Materi Pembelajaran</h2>
            <p>Akses semua materi dan file dari guru</p>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Arsip</h3>
                <div class="number"><?php echo $total_files; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Total Ukuran</h3>
                <div class="number" style="color: #38ef7d; font-size: 20px;"><?php echo formatSize($total_size); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Hasil Pencarian</h3>
                <div class="number" style="color: #ffa726;"><?php echo count($all_arsip); ?></div>
            </div>
        </div>

        <div class="info-box">
            <p>
                💡 <strong>Info:</strong> Semua materi dan file di sini diupload oleh guru. 
                Anda dapat melihat, mendownload, dan mempelajari materi yang tersedia.
            </p>
        </div>

        <!-- Daftar Arsip -->
        <div class="card">
            <div class="card-header">
                <h3>📂 Daftar Arsip</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari judul, nama file, atau nama guru..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="tipe">
                        <option value="">Semua Tipe File</option>
                        <?php foreach ($file_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $tipe_filter === $type ? 'selected' : ''; ?>>
                            <?php echo strtoupper($type); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($tipe_filter)): ?>
                    <a href="arsip_siswa.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (count($all_arsip) > 0): ?>
                <div class="file-grid">
                    <?php foreach ($all_arsip as $arsip): ?>
                    <?php
                    // Icon berdasarkan tipe file
                    $icon_map = [
                        'pdf' => '📄',
                        'doc' => '📝', 'docx' => '📝',
                        'xls' => '📊', 'xlsx' => '📊',
                        'ppt' => '📊', 'pptx' => '📊',
                        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
                        'zip' => '🗜️', 'rar' => '🗜️'
                    ];
                    $icon = $icon_map[$arsip['file_type']] ?? '📁';
                    ?>
                    <div class="file-card">
                        <div class="file-icon"><?php echo $icon; ?></div>
                        <div class="file-info">
                            <h4 title="<?php echo htmlspecialchars($arsip['judul_arsip']); ?>">
                                <?php echo htmlspecialchars($arsip['judul_arsip']); ?>
                            </h4>
                            <div class="file-meta">
                                📎 <?php echo htmlspecialchars($arsip['file_name']); ?>
                            </div>
                            <div class="file-meta">
                                👨‍🏫 <?php echo htmlspecialchars($arsip['nama_guru']); ?>
                            </div>
                            <div class="file-meta">
                                💾 <?php echo formatSize($arsip['file_size']); ?> • <?php echo strtoupper($arsip['file_type']); ?>
                            </div>
                            <div class="file-meta">
                                🕐 <?php echo date('d/m/Y H:i', strtotime($arsip['tanggal_upload'])); ?>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" target="_blank" class="btn btn-info btn-sm">
                                👁️ Lihat
                            </a>
                            <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" download class="btn btn-success btn-sm">
                                ⬇️ Download
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; color: #a0aec0;">
                    <div style="font-size: 64px; margin-bottom: 20px;">📭</div>
                    <h3 style="margin-bottom: 10px; color: #718096;">
                        <?php if (!empty($search) || !empty($tipe_filter)): ?>
                        Tidak Ada Hasil
                        <?php else: ?>
                        Belum Ada Arsip
                        <?php endif; ?>
                    </h3>
                    <p>
                        <?php if (!empty($search) || !empty($tipe_filter)): ?>
                        Coba ubah kata kunci pencarian atau filter Anda
                        <?php else: ?>
                        Belum ada file arsip yang diupload oleh guru
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>