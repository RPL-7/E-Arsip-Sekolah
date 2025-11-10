<?php
session_start();
require_once '../config.php';

checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// PROSES UPLOAD TUGAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload') {
        try {
            $id_tugas = $_POST['id_tugas'];
            
            // Ambil data siswa
            $stmt = $pdo->prepare("SELECT id_kelas, nama_siswa, nis FROM user_siswa WHERE id_siswa = ?");
            $stmt->execute([$user_id]);
            $siswa = $stmt->fetch();
            
            // Cek tugas
            $stmt = $pdo->prepare("SELECT * FROM tugas WHERE id_tugas = ? AND id_kelas = ?");
            $stmt->execute([$id_tugas, $siswa['id_kelas']]);
            $tugas = $stmt->fetch();
            
            if (!$tugas) throw new Exception('Tugas tidak ditemukan');
            
            // Cek apakah sudah upload
            $file_jawaban = json_decode($tugas['file_jawaban'] ?? '[]', true);
            foreach ($file_jawaban as $fw) {
                if ($fw['id_siswa'] == $user_id) {
                    throw new Exception('Anda sudah mengumpulkan tugas ini');
                }
            }
            
            // Validasi file
            if (!isset($_FILES['file_tugas']) || $_FILES['file_tugas']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File tidak valid');
            }
            
            $file = $_FILES['file_tugas'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'docx', 'jpeg', 'jpg', 'png'];
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Tipe file tidak diizinkan. Hanya PDF, DOCX, JPEG, JPG, PNG');
            }
            
            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('Ukuran file maksimal 10MB');
            }
            
            // Upload file
            $upload_dir = "../uploads/tugas_siswa/{$siswa['nis']}_{$siswa['nama_siswa']}";
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $file_path = $upload_dir . '/' . $unique_name;
            
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Gagal mengupload file');
            }
            
            // Simpan ke database (JSON)
            $is_terlambat = ($tugas['deadline'] && strtotime($tugas['deadline']) < time());
            $file_jawaban[] = [
                'id_siswa' => $user_id,
                'nis' => $siswa['nis'],
                'nama' => $siswa['nama_siswa'],
                'file_path' => $file_path,
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'tanggal_upload' => date('Y-m-d H:i:s'),
                'terlambat' => $is_terlambat
            ];
            
            $stmt = $pdo->prepare("UPDATE tugas SET file_jawaban = ? WHERE id_tugas = ?");
            $stmt->execute([json_encode($file_jawaban), $id_tugas]);
            
            $_SESSION['success'] = 'Tugas berhasil dikumpulkan!' . ($is_terlambat ? ' (Terlambat)' : '');
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        header('Location: tugas_siswa.php');
        exit();
    }
}

// Ambil data siswa
$stmt = $pdo->prepare("SELECT id_kelas, nama_siswa, nis FROM user_siswa WHERE id_siswa = ?");
$stmt->execute([$user_id]);
$siswa_data = $stmt->fetch();
$id_kelas = $siswa_data['id_kelas'];
$nama_siswa = $siswa_data['nama_siswa'];
$nis = $siswa_data['nis'];

if (!$id_kelas) {
    $error_no_class = true;
} else {
    $error_no_class = false;
    
    // Ambil semua tugas DAN materi (DIPERBAIKI)
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $pelajaran_filter = $_GET['pelajaran'] ?? '';
    $tipe_filter = $_GET['tipe'] ?? '';
    
    // Query untuk tugas
    $query_tugas = "
        SELECT 'tugas' as tipe, t.id_tugas as id, t.judul_tugas as judul, t.deskripsi, t.tanggal_dibuat, t.deadline, t.status, t.file_path, t.file_name,
               t.file_jawaban, t.nilai_siswa,
               p.nama_pelajaran, g.nama_guru
        FROM tugas t
        JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
        JOIN user_guru g ON t.id_guru = g.id_guru
        WHERE t.id_kelas = ?
    ";
    
    // Query untuk materi
    $query_materi = "
        SELECT 'materi' as tipe, m.id_materi as id, m.judul_materi as judul, m.deskripsi, m.tanggal_dibuat, NULL as deadline, m.status, m.file_path, m.file_name,
               NULL as file_jawaban, NULL as nilai_siswa,
               p.nama_pelajaran, g.nama_guru
        FROM materi m
        JOIN pelajaran p ON m.id_pelajaran = p.id_pelajaran
        JOIN user_guru g ON m.id_guru = g.id_guru
        WHERE m.id_kelas = ?
    ";
    
    $params = [$id_kelas, $id_kelas];
    
    $where_conditions = [];
    if (!empty($search)) {
        $where_conditions[] = "(judul LIKE ? OR nama_pelajaran LIKE ?)";
    }
    
    if (!empty($pelajaran_filter)) {
        $where_conditions[] = "id_pelajaran = ?";
    }
    
    // Gabungkan query
    $query = "SELECT * FROM (($query_tugas) UNION ALL ($query_materi)) as combined WHERE 1=1";
    
    if (!empty($where_conditions)) {
        $query .= " AND " . implode(" AND ", $where_conditions);
    }
    
    if (!empty($tipe_filter)) {
        $query .= " AND tipe = ?";
    }
    
    $query .= " ORDER BY tanggal_dibuat DESC";
    
    $stmt = $pdo->prepare($query);
    
    $exec_params = [$id_kelas, $id_kelas];
    if (!empty($search)) {
        $search_term = "%$search%";
        $exec_params[] = $search_term;
        $exec_params[] = $search_term;
    }
    if (!empty($pelajaran_filter)) {
        $exec_params[] = $pelajaran_filter;
    }
    if (!empty($tipe_filter)) {
        $exec_params[] = $tipe_filter;
    }
    
    $stmt->execute($exec_params);
    $all_items = $stmt->fetchAll();
    
    // Proses data (tambahkan info pengumpulan untuk tugas)
    foreach ($all_items as &$item) {
        $item['sudah_kumpul'] = false;
        $item['data_kumpul'] = null;
        $item['nilai'] = null;
        
        // Hanya proses untuk tugas
        if ($item['tipe'] === 'tugas') {
            $file_jawaban = json_decode($item['file_jawaban'] ?? '[]', true);
            $nilai_siswa = json_decode($item['nilai_siswa'] ?? '[]', true);
            
            foreach ($file_jawaban as $fw) {
                if ($fw['id_siswa'] == $user_id) {
                    $item['sudah_kumpul'] = true;
                    $item['data_kumpul'] = $fw;
                    break;
                }
            }
            
            foreach ($nilai_siswa as $ns) {
                if ($ns['id_siswa'] == $user_id) {
                    $item['nilai'] = $ns;
                    break;
                }
            }
        }
    }
    unset($item);
    
    // Filter berdasarkan status pengumpulan (hanya untuk tugas)
    if ($status_filter === 'sudah_kumpul') {
        $all_items = array_filter($all_items, fn($t) => $t['tipe'] === 'tugas' && $t['sudah_kumpul']);
    } elseif ($status_filter === 'belum_kumpul') {
        $all_items = array_filter($all_items, fn($t) => $t['tipe'] === 'tugas' && !$t['sudah_kumpul'] && $t['status'] === 'aktif');
    }
    
    // Ambil pelajaran untuk filter
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id_pelajaran, p.nama_pelajaran
        FROM (
            SELECT id_pelajaran FROM tugas WHERE id_kelas = ?
            UNION
            SELECT id_pelajaran FROM materi WHERE id_kelas = ?
        ) as combined
        JOIN pelajaran p ON combined.id_pelajaran = p.id_pelajaran
        ORDER BY p.nama_pelajaran
    ");
    $stmt->execute([$id_kelas, $id_kelas]);
    $pelajaran_list = $stmt->fetchAll();
    
    // Statistik
    $total_aktif = 0;
    $total_materi = 0;
    $sudah_kumpul = 0;
    $sudah_dinilai = 0;
    
    foreach ($all_items as $t) {
        if ($t['tipe'] === 'tugas' && $t['status'] === 'aktif') $total_aktif++;
        if ($t['tipe'] === 'materi') $total_materi++;
        if ($t['tipe'] === 'tugas' && $t['sudah_kumpul']) $sudah_kumpul++;
        if ($t['tipe'] === 'tugas' && $t['nilai']) $sudah_dinilai++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas & Materi - Siswa</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #38ef7d; color: white; }
        .btn-info { background: #26c6da; color: white; }
        .btn-warning { background: #ffa726; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-back { background: rgba(255,255,255,0.2); color: white; border: 2px solid white; }
        .btn-back:hover { background: white; color: #667eea; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffa726; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea; }
        .stat-card h3 { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #667eea; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .card-body { padding: 25px; }
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-bar input, .filter-bar select { padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .filter-bar input { flex: 1; min-width: 250px; }
        .tugas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px; }
        .tugas-card { background: white; border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; transition: all 0.3s ease; }
        .tugas-card:hover { border-color: #667eea; box-shadow: 0 5px 15px rgba(102,126,234,0.1); transform: translateY(-2px); }
        .tugas-card.submitted { border-color: #38ef7d; background: #f0fdf4; }
        .tugas-card.graded { border-color: #ffa726; background: #fffbf0; }
        .tugas-card.materi { border-color: #26c6da; background: #f0fdff; }
        .tugas-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .tugas-title h4 { color: #2d3748; font-size: 18px; margin-bottom: 5px; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-materi { background: #d1f2eb; color: #0a6552; }
        .tugas-meta { font-size: 13px; color: #718096; margin-bottom: 12px; }
        .tugas-meta div { margin-bottom: 8px; }
        .tugas-desc { color: #2d3748; font-size: 14px; line-height: 1.6; margin-bottom: 15px; max-height: 60px; overflow: hidden; }
        .tugas-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .nilai-display { background: #ffa726; color: white; padding: 8px 15px; border-radius: 8px; font-weight: bold; font-size: 16px; margin-top: 10px; display: inline-block; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; margin: 20px; }
        .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0; }
        .modal-header h3 { font-size: 20px; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
        .modal-body { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input[type="file"] { width: 100%; padding: 10px; border: 2px dashed #e2e8f0; border-radius: 8px; cursor: pointer; }
        .file-info { font-size: 13px; color: #718096; margin-top: 5px; }
        @media (max-width: 768px) {
            .tugas-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .filter-bar input { min-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📚 Tugas & Materi</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($nama_siswa); ?></strong> (<?php echo htmlspecialchars($nis); ?>)</span>
            <a href="../dashboard/dashboard_siswa.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <strong>✔ Berhasil!</strong> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <strong>✗ Error!</strong> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error_no_class): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Perhatian!</strong><br>
            Anda belum terdaftar di kelas manapun. Silakan hubungi admin.
        </div>
        <?php else: ?>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Tugas Aktif</h3>
                <div class="number"><?php echo $total_aktif; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #26c6da;">
                <h3>Materi Tersedia</h3>
                <div class="number" style="color: #26c6da;"><?php echo $total_materi; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Sudah Dikumpulkan</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $sudah_kumpul; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Sudah Dinilai</h3>
                <div class="number" style="color: #ffa726;"><?php echo $sudah_dinilai; ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card">
            <div class="card-body" style="padding: 20px;">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari judul..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="pelajaran">
                        <option value="">Semua Pelajaran</option>
                        <?php foreach ($pelajaran_list as $pel): ?>
                        <option value="<?php echo $pel['id_pelajaran']; ?>" <?php echo $pelajaran_filter == $pel['id_pelajaran'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pel['nama_pelajaran']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tipe">
                        <option value="">Semua Tipe</option>
                        <option value="tugas" <?php echo $tipe_filter === 'tugas' ? 'selected' : ''; ?>>Tugas</option>
                        <option value="materi" <?php echo $tipe_filter === 'materi' ? 'selected' : ''; ?>>Materi</option>
                    </select>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="sudah_kumpul" <?php echo $status_filter === 'sudah_kumpul' ? 'selected' : ''; ?>>Sudah Dikumpulkan</option>
                        <option value="belum_kumpul" <?php echo $status_filter === 'belum_kumpul' ? 'selected' : ''; ?>>Belum Dikumpulkan</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($pelajaran_filter) || !empty($tipe_filter)): ?>
                    <a href="tugas_siswa.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Daftar Tugas & Materi -->
        <?php if (count($all_items) > 0): ?>
        <div class="tugas-grid">
            <?php foreach ($all_items as $item): ?>
            <?php
            $is_tugas = $item['tipe'] === 'tugas';
            $card_class = '';
            $status_badge = 'badge-success';
            $status_text = 'Aktif';
            
            if (!$is_tugas) {
                // Materi
                $card_class = 'materi';
                $status_badge = 'badge-materi';
                $status_text = 'Materi';
            } elseif ($item['nilai']) {
                $card_class = 'graded';
                $status_badge = 'badge-warning';
                $status_text = 'Dinilai';
            } elseif ($item['sudah_kumpul']) {
                $card_class = 'submitted';
                $status_badge = 'badge-info';
                $status_text = 'Sudah Dikumpulkan';
                if ($item['data_kumpul']['terlambat']) {
                    $status_badge = 'badge-danger';
                    $status_text = 'Terlambat';
                }
            } elseif ($item['status'] !== 'aktif') {
                $status_badge = 'badge-secondary';
                $status_text = ucfirst($item['status']);
            }
            ?>
            <div class="tugas-card <?php echo $card_class; ?>">
                <div class="tugas-header">
                    <div class="tugas-title">
                        <h4>
                            <?php echo $is_tugas ? '📝' : '📖'; ?> 
                            <?php echo htmlspecialchars($item['judul']); ?>
                        </h4>
                    </div>
                    <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                </div>
                
                <div class="tugas-meta">
                    <div>📚 <?php echo htmlspecialchars($item['nama_pelajaran']); ?></div>
                    <div>👨‍🏫 <?php echo htmlspecialchars($item['nama_guru']); ?></div>
                    <div>📅 Dibuat: <?php echo date('d/m/Y H:i', strtotime($item['tanggal_dibuat'])); ?></div>
                    <?php if ($is_tugas && $item['deadline']): ?>
                    <div>⏰ Deadline: <?php echo date('d/m/Y H:i', strtotime($item['deadline'])); ?></div>
                    <?php endif; ?>
                    <?php if ($is_tugas && $item['sudah_kumpul']): ?>
                    <div style="color: #38ef7d; font-weight: 600;">
                        ✓ Dikumpulkan: <?php echo date('d/m/Y H:i', strtotime($item['data_kumpul']['tanggal_upload'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($item['deskripsi']): ?>
                <div class="tugas-desc">
                    <?php echo nl2br(htmlspecialchars($item['deskripsi'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($is_tugas && $item['nilai']): ?>
                <div class="nilai-display">
                    🏆 Nilai: <?php echo number_format($item['nilai']['nilai'], 2); ?>
                </div>
                <?php if ($item['nilai']['catatan']): ?>
                <div style="background: #f7fafc; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 13px;">
                    💬 <strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($item['nilai']['catatan'])); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <div class="tugas-actions">
                    <?php if ($item['file_path']): ?>
                    <a href="<?php echo htmlspecialchars($item['file_path']); ?>" target="_blank" class="btn btn-success btn-sm">
                        📥 Download <?php echo $is_tugas ? 'Materi' : 'File'; ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($is_tugas && !$item['sudah_kumpul'] && $item['status'] == 'aktif'): ?>
                    <button class="btn btn-primary btn-sm" onclick="uploadTugas(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['judul']); ?>')">
                        📤 Kumpulkan Tugas
                    </button>
                    <?php endif; ?>
                    <?php if ($is_tugas && $item['sudah_kumpul']): ?>
                    <a href="<?php echo htmlspecialchars($item['data_kumpul']['file_path']); ?>" target="_blank" class="btn btn-info btn-sm">
                        📄 Lihat File Saya
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 60px 20px; color: #a0aec0;">
                <div style="font-size: 64px; margin-bottom: 20px;">📚</div>
                <h3 style="margin-bottom: 10px; color: #718096;">Belum Ada Data</h3>
                <p>Belum ada tugas atau materi dari guru untuk kelas Anda</p>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>

    <!-- Modal Upload Tugas -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📤 Kumpulkan Tugas</h3>
                <button class="modal-close" onclick="closeUploadModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form action="tugas_siswa.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="id_tugas" id="upload_id_tugas">
                    
                    <div class="form-group">
                        <label>Judul Tugas:</label>
                        <div id="upload_judul_tugas" style="font-weight: 600; color: #2d3748;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="file_tugas">Upload File Tugas: <span style="color: red;">*</span></label>
                        <input type="file" name="file_tugas" id="file_tugas" required accept=".pdf,.docx,.jpeg,.jpg,.png">
                        <div class="file-info">
                            📌 Format: PDF, DOCX, JPEG, JPG, PNG (Max 10MB)
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Batal</button>
                        <button type="submit" class="btn btn-success">📤 Kirim Tugas</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function uploadTugas(idTugas, judulTugas) {
            document.getElementById('upload_id_tugas').value = idTugas;
            document.getElementById('upload_judul_tugas').textContent = judulTugas;
            document.getElementById('file_tugas').value = '';
            document.getElementById('uploadModal').classList.add('active');
        }
        
        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('uploadModal');
            if (event.target === modal) {
                closeUploadModal();
            }
        }
        
        // Auto hide alerts after 5 seconds
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