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
            $upload_dir = "../arsip/tugas_siswa/{$siswa['nis']}_{$siswa['nama_siswa']}";
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
    
    // Ambil semua tugas
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $pelajaran_filter = $_GET['pelajaran'] ?? '';
    
    $query = "SELECT t.*, p.nama_pelajaran, g.nama_guru FROM tugas t
              JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
              JOIN user_guru g ON t.id_guru = g.id_guru
              WHERE t.id_kelas = ?";
    $params = [$id_kelas];
    
    if (!empty($search)) {
        $query .= " AND (t.judul_tugas LIKE ? OR p.nama_pelajaran LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        if ($status_filter === 'sudah_kumpul') {
            // Filter di PHP nanti
        } elseif ($status_filter === 'belum_kumpul') {
            // Filter di PHP nanti
        } else {
            $query .= " AND t.status = ?";
            $params[] = $status_filter;
        }
    }
    
    if (!empty($pelajaran_filter)) {
        $query .= " AND t.id_pelajaran = ?";
        $params[] = $pelajaran_filter;
    }
    
    $query .= " ORDER BY t.tanggal_dibuat DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_tugas = $stmt->fetchAll();
    
    // Proses data tugas (tambahkan info pengumpulan)
    foreach ($all_tugas as &$tugas) {
        $file_jawaban = json_decode($tugas['file_jawaban'] ?? '[]', true);
        $nilai_siswa = json_decode($tugas['nilai_siswa'] ?? '[]', true);
        
        $tugas['sudah_kumpul'] = false;
        $tugas['data_kumpul'] = null;
        $tugas['nilai'] = null;
        
        foreach ($file_jawaban as $fw) {
            if ($fw['id_siswa'] == $user_id) {
                $tugas['sudah_kumpul'] = true;
                $tugas['data_kumpul'] = $fw;
                break;
            }
        }
        
        foreach ($nilai_siswa as $ns) {
            if ($ns['id_siswa'] == $user_id) {
                $tugas['nilai'] = $ns;
                break;
            }
        }
    }
    
    // Filter berdasarkan status pengumpulan
    if ($status_filter === 'sudah_kumpul') {
        $all_tugas = array_filter($all_tugas, fn($t) => $t['sudah_kumpul']);
    } elseif ($status_filter === 'belum_kumpul') {
        $all_tugas = array_filter($all_tugas, fn($t) => !$t['sudah_kumpul'] && $t['status'] === 'aktif');
    }
    
    // Ambil pelajaran untuk filter
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
    $total_aktif = 0;
    $sudah_kumpul = 0;
    $sudah_dinilai = 0;
    
    foreach ($all_tugas as $t) {
        if ($t['status'] === 'aktif') $total_aktif++;
        if ($t['sudah_kumpul']) $sudah_kumpul++;
        if ($t['nilai']) $sudah_dinilai++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas Saya - Siswa</title>
    <link rel="stylesheet" href="../css/tugas_siswa.css">
</head>
<body>
    <div class="navbar">
        <h1>📚 Tugas Saya</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($nama_siswa); ?></strong> (<?php echo htmlspecialchars($nis); ?>)</span>
            <a href="../dashboard/dashboard_siswa.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <strong>✓ Berhasil!</strong> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
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
                    <input type="text" name="search" placeholder="Cari judul tugas..." value="<?php echo htmlspecialchars($search); ?>">
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
                        <option value="sudah_kumpul" <?php echo $status_filter === 'sudah_kumpul' ? 'selected' : ''; ?>>Sudah Dikumpulkan</option>
                        <option value="belum_kumpul" <?php echo $status_filter === 'belum_kumpul' ? 'selected' : ''; ?>>Belum Dikumpulkan</option>
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
            $card_class = '';
            $status_badge = 'badge-success';
            $status_text = 'Aktif';
            
            if ($tugas['nilai']) {
                $card_class = 'graded';
                $status_badge = 'badge-warning';
                $status_text = 'Dinilai';
            } elseif ($tugas['sudah_kumpul']) {
                $card_class = 'submitted';
                $status_badge = 'badge-info';
                $status_text = 'Sudah Dikumpulkan';
                if ($tugas['data_kumpul']['terlambat']) {
                    $status_badge = 'badge-danger';
                    $status_text = 'Terlambat';
                }
            } elseif ($tugas['status'] !== 'aktif') {
                $status_badge = 'badge-secondary';
                $status_text = ucfirst($tugas['status']);
            }
            ?>
            <div class="tugas-card <?php echo $card_class; ?>">
                <div class="tugas-header">
                    <div class="tugas-title">
                        <h4><?php echo htmlspecialchars($tugas['judul_tugas']); ?></h4>
                    </div>
                    <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                </div>
                
                <div class="tugas-meta">
                    <div>📚 <?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></div>
                    <div>👨‍🏫 <?php echo htmlspecialchars($tugas['nama_guru']); ?></div>
                    <div>📅 Dibuat: <?php echo date('d/m/Y H:i', strtotime($tugas['tanggal_dibuat'])); ?></div>
                    <?php if ($tugas['deadline']): ?>
                    <div>⏰ Deadline: <?php echo date('d/m/Y H:i', strtotime($tugas['deadline'])); ?></div>
                    <?php endif; ?>
                    <?php if ($tugas['sudah_kumpul']): ?>
                    <div style="color: #38ef7d; font-weight: 600;">
                        ✓ Dikumpulkan: <?php echo date('d/m/Y H:i', strtotime($tugas['data_kumpul']['tanggal_upload'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($tugas['deskripsi']): ?>
                <div class="tugas-desc">
                    <?php echo nl2br(htmlspecialchars($tugas['deskripsi'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($tugas['nilai']): ?>
                <div class="nilai-display">
                    🏆 Nilai: <?php echo number_format($tugas['nilai']['nilai'], 2); ?>
                </div>
                <?php if ($tugas['nilai']['catatan']): ?>
                <div style="background: #f7fafc; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 13px;">
                    💬 <strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($tugas['nilai']['catatan'])); ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                
                <div class="tugas-actions">
                    <?php if ($tugas['file_path']): ?>
                    <a href="<?php echo htmlspecialchars($tugas['file_path']); ?>" target="_blank" class="btn btn-success btn-sm">
                        📥 Download Materi
                    </a>
                    <?php endif; ?>
                    <?php if (!$tugas['sudah_kumpul'] && $tugas['status'] == 'aktif'): ?>
                    <button class="btn btn-primary btn-sm" onclick="uploadTugas(<?php echo $tugas['id_tugas']; ?>, '<?php echo htmlspecialchars($tugas['judul_tugas']); ?>')">
                        📤 Kumpulkan Tugas
                    </button>
                    <?php endif; ?>
                    <?php if ($tugas['sudah_kumpul']): ?>
                    <a href="<?php echo htmlspecialchars($tugas['data_kumpul']['file_path']); ?>" target="_blank" class="btn btn-info btn-sm">
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
                <h3 style="margin-bottom: 10px; color: #718096;">Belum Ada Tugas</h3>
                <p>Belum ada tugas dari guru untuk kelas Anda</p>
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
    </script>
</body>
</html>