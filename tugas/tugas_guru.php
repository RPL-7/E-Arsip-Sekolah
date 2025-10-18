<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah guru
checkUserType(['guru']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Koneksi database
$pdo = getDBConnection();

// Buat folder tugas jika belum ada
$upload_base_dir = '../arsip/tugas/';
if (!file_exists($upload_base_dir)) {
    mkdir($upload_base_dir, 0777, true);
}

// Proses form
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        try {
            $judul_tugas = trim($_POST['judul_tugas']);
            $deskripsi = trim($_POST['deskripsi']);
            $id_pelajaran = $_POST['id_pelajaran'];
            $id_kelas = $_POST['id_kelas'];
            $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
            
            // Validasi
            if (empty($judul_tugas) || empty($id_pelajaran) || empty($id_kelas)) {
                throw new Exception("Judul tugas, mata pelajaran, dan kelas wajib diisi!");
            }
            
            // Cek apakah guru mengajar pelajaran di kelas tersebut
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM kelas_pelajaran 
                WHERE id_kelas = ? AND id_pelajaran = ? AND id_guru = ?
            ");
            $stmt->execute([$id_kelas, $id_pelajaran, $user_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("Anda tidak mengajar mata pelajaran ini di kelas tersebut!");
            }
            
            $file_path = null;
            $file_name = null;
            
            // Cek apakah menggunakan file dari arsip atau upload baru
            $use_arsip = $_POST['use_arsip'] ?? 'upload';
            
            if ($use_arsip === 'arsip' && !empty($_POST['id_arsip'])) {
                // Ambil file dari arsip
                $id_arsip = $_POST['id_arsip'];
                $stmt = $pdo->prepare("SELECT file_path, file_name FROM arsip WHERE id_arsip = ? AND id_uploader = ? AND tipe_uploader = 'guru'");
                $stmt->execute([$id_arsip, $user_id]);
                $arsip = $stmt->fetch();
                
                if ($arsip) {
                    $file_path = $arsip['file_path'];
                    $file_name = $arsip['file_name'];
                }
            } elseif ($use_arsip === 'upload' && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Handle file upload baru
                $file = $_FILES['file'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Error saat upload file!");
                }
                
                // Validasi ukuran (max 10MB)
                $max_size = 10 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    throw new Exception("Ukuran file maksimal 10MB!");
                }
                
                // Validasi tipe file
                $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, $allowed_types)) {
                    throw new Exception("Tipe file tidak diizinkan!");
                }
                
                $file_name = $file['name'];
                $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                $file_path = $upload_base_dir . $unique_name;
                
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception("Gagal mengupload file!");
                }
            }
            
            // Insert tugas
            $stmt = $pdo->prepare("
                INSERT INTO tugas (judul_tugas, deskripsi, id_pelajaran, id_kelas, id_guru, file_path, file_name, deadline)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $judul_tugas,
                $deskripsi,
                $id_pelajaran,
                $id_kelas,
                $user_id,
                $file_path,
                $file_name,
                $deadline
            ]);
            
            $success_message = "Tugas berhasil dibuat!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            // Hapus file jika ada error
            if (isset($file_path) && file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    if ($action === 'update_status') {
        try {
            $id_tugas = $_POST['id_tugas'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE tugas SET status = ? WHERE id_tugas = ? AND id_guru = ?");
            $stmt->execute([$status, $id_tugas, $user_id]);
            
            $success_message = "Status tugas berhasil diubah!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $id_tugas = $_POST['id_tugas'];
            
            // Ambil data tugas
            $stmt = $pdo->prepare("SELECT * FROM tugas WHERE id_tugas = ? AND id_guru = ?");
            $stmt->execute([$id_tugas, $user_id]);
            $tugas = $stmt->fetch();
            
            if (!$tugas) {
                throw new Exception("Tugas tidak ditemukan!");
            }

            // Cek apakah file_path ada dan file-nya benar-benar ada
            if (!empty($tugas['file_path']) && file_exists($tugas['file_path'])) {
                $filePath = $tugas['file_path'];

                // Jika file ada di folder tugas/, hapus
                if (strpos($filePath, 'tugas/') !== false) {
                    if (unlink($filePath)) {
                        // Berhasil dihapus
                    } else {
                        throw new Exception("Gagal menghapus file tugas.");
                    }
                }
                // Jika file di dalam folder arsip/, jangan dihapus
            }

            // Hapus data tugas dari database
            $stmt = $pdo->prepare("DELETE FROM tugas WHERE id_tugas = ?");
            $stmt->execute([$id_tugas]);

            $success_message = "Tugas berhasil dihapus!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Ambil semua tugas guru
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';

$query = "
    SELECT t.*, 
           p.nama_pelajaran,
           k.nama_kelas, k.tahun_ajaran,
           (SELECT COUNT(*) FROM user_siswa WHERE id_kelas = t.id_kelas AND status = 'aktif') as jumlah_siswa
    FROM tugas t
    JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
    JOIN kelas k ON t.id_kelas = k.id_kelas
    WHERE t.id_guru = ?
";
$params = [$user_id];

if (!empty($search)) {
    $query .= " AND (t.judul_tugas LIKE ? OR p.nama_pelajaran LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $query .= " AND t.status = ?";
    $params[] = $status_filter;
}

if (!empty($kelas_filter)) {
    $query .= " AND t.id_kelas = ?";
    $params[] = $kelas_filter;
}

$query .= " ORDER BY t.tanggal_dibuat DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_tugas = $stmt->fetchAll();

// Ambil kelas dan pelajaran yang diajar guru
$stmt = $pdo->prepare("
    SELECT DISTINCT k.id_kelas, k.nama_kelas, k.tahun_ajaran, p.id_pelajaran, p.nama_pelajaran
    FROM kelas_pelajaran kp
    JOIN kelas k ON kp.id_kelas = k.id_kelas
    JOIN pelajaran p ON kp.id_pelajaran = p.id_pelajaran
    WHERE kp.id_guru = ?
    ORDER BY k.nama_kelas, p.nama_pelajaran
");
$stmt->execute([$user_id]);
$kelas_pelajaran = $stmt->fetchAll();

// Ambil daftar arsip guru untuk dropdown
$stmt = $pdo->prepare("
    SELECT id_arsip, judul_arsip, file_name, file_size, file_type, tanggal_upload
    FROM arsip
    WHERE id_uploader = ? AND tipe_uploader = 'guru'
    ORDER BY tanggal_upload DESC
");
$stmt->execute([$user_id]);
$arsip_list = $stmt->fetchAll();

// Group by kelas
$kelas_list = [];
foreach ($kelas_pelajaran as $kp) {
    if (!isset($kelas_list[$kp['id_kelas']])) {
        $kelas_list[$kp['id_kelas']] = [
            'nama_kelas' => $kp['nama_kelas'],
            'tahun_ajaran' => $kp['tahun_ajaran'],
            'pelajaran' => []
        ];
    }
    $kelas_list[$kp['id_kelas']]['pelajaran'][] = [
        'id_pelajaran' => $kp['id_pelajaran'],
        'nama_pelajaran' => $kp['nama_pelajaran']
    ];
}

// Statistik
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas WHERE id_guru = ?");
$stmt->execute([$user_id]);
$total_tugas = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas WHERE id_guru = ? AND status = 'aktif'");
$stmt->execute([$user_id]);
$tugas_aktif = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas WHERE id_guru = ? AND deadline IS NOT NULL AND deadline < NOW() AND status = 'aktif'");
$stmt->execute([$user_id]);
$tugas_lewat = $stmt->fetch()['total'];

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
    <title>Tugas & Materi - Guru</title>
    <link rel="stylesheet" href="../css/tugas_guru.css">
</head>
<body>
    <div class="navbar">
        <h1>📝 Tugas & Materi</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="../dashboard/dashboard_guru.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <div class="page-header-text">
                <h2>Manajemen Tugas & Materi</h2>
                <p>Buat dan kelola tugas untuk siswa</p>
            </div>
            <button class="btn btn-success" onclick="openCreateModal()">➕ Buat Tugas Baru</button>
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
                <h3>Total Tugas</h3>
                <div class="number"><?php echo $total_tugas; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Tugas Aktif</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $tugas_aktif; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f5576c;">
                <h3>Melewati Deadline</h3>
                <div class="number" style="color: #f5576c;"><?php echo $tugas_lewat; ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card">
            <div class="card-body" style="padding: 20px;">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari judul tugas atau mata pelajaran..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="kelas">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelas_list as $id_kelas => $kelas_data): ?>
                        <option value="<?php echo $id_kelas; ?>" <?php echo $kelas_filter == $id_kelas ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kelas_data['nama_kelas']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                        <option value="tertutup" <?php echo $status_filter === 'tertutup' ? 'selected' : ''; ?>>Tertutup</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($kelas_filter)): ?>
                    <a href="tugas_guru.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Daftar Tugas -->
        <?php if (count($all_tugas) > 0): ?>
        <div class="tugas-grid">
            <?php foreach ($all_tugas as $tugas): ?>
            <?php
            $is_expired = $tugas['deadline'] && strtotime($tugas['deadline']) < time() && $tugas['status'] == 'aktif';
            $status_badge_class = 'badge-success';
            $status_text = 'Aktif';
            if ($tugas['status'] == 'selesai') {
                $status_badge_class = 'badge-warning';
                $status_text = 'Selesai';
            } elseif ($tugas['status'] == 'tertutup') {
                $status_badge_class = 'badge-secondary';
                $status_text = 'Tertutup';
            } elseif ($is_expired) {
                $status_badge_class = 'badge-danger';
                $status_text = 'Lewat Deadline';
            }
            ?>
            <div class="tugas-card">
                <div class="tugas-header">
                    <div class="tugas-title">
                        <h4><?php echo htmlspecialchars($tugas['judul_tugas']); ?></h4>
                    </div>
                    <span class="badge <?php echo $status_badge_class; ?>"><?php echo $status_text; ?></span>
                </div>
                
                <div class="tugas-meta">
                    <div>📚 <?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></div>
                    <div>🏫 <?php echo htmlspecialchars($tugas['nama_kelas']); ?> (<?php echo $tugas['jumlah_siswa']; ?> siswa)</div>
                    <div>📅 Dibuat: <?php echo date('d/m/Y H:i', strtotime($tugas['tanggal_dibuat'])); ?></div>
                    <?php if ($tugas['deadline']): ?>
                    <div style="color: <?php echo $is_expired ? '#f5576c' : '#2d3748'; ?>">
                        ⏰ Deadline: <?php echo date('d/m/Y H:i', strtotime($tugas['deadline'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($tugas['file_name']): ?>
                    <div>📎 File: <?php echo htmlspecialchars($tugas['file_name']); ?></div>
                    <?php endif; ?>
                </div>
                
                <?php if ($tugas['deskripsi']): ?>
                <div class="tugas-desc">
                    <?php echo nl2br(htmlspecialchars($tugas['deskripsi'])); ?>
                </div>
                <?php endif; ?>
                
                <div class="tugas-actions">
                    <button class="btn btn-info btn-sm" onclick="viewTugas(<?php echo htmlspecialchars(json_encode($tugas)); ?>)">
                        👁️ Detail
                    </button>
                    <?php if ($tugas['file_path']): ?>
                    <a href="<?php echo htmlspecialchars($tugas['file_path']); ?>" target="_blank" class="btn btn-success btn-sm">
                        📥 Download File
                    </a>
                    <?php endif; ?>
                    <button class="btn btn-warning btn-sm" onclick="changeStatus(<?php echo $tugas['id_tugas']; ?>, '<?php echo $tugas['status']; ?>')">
                        🔄 Ubah Status
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteTugas(<?php echo $tugas['id_tugas']; ?>, '<?php echo htmlspecialchars($tugas['judul_tugas']); ?>')">
                        🗑️ Hapus
                    </button>
                    <!-- Tambahkan di bagian tugas-actions -->
                    <a href="nilai_tugas.php?id=<?php echo $tugas['id_tugas']; ?>" class="btn btn-success btn-sm">
                        📊 Lihat & Nilai
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body" style="text-align: center; padding: 60px 20px; color: #a0aec0;">
                <div style="font-size: 64px; margin-bottom: 20px;">📝</div>
                <h3 style="margin-bottom: 10px; color: #718096;">Belum Ada Tugas</h3>
                <p>Buat tugas pertama Anda untuk siswa</p>
                <button class="btn btn-success" onclick="openCreateModal()" style="margin-top: 20px;">
                    ➕ Buat Tugas Baru
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal Create Tugas -->
    <div class="modal" id="createModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3>➕ Buat Tugas Baru</h3>
                <button class="modal-close" onclick="closeCreateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (empty($kelas_list)): ?>
                <div class="info-box" style="background: #fff3cd; border-left-color: #ffa726;">
                    <h4>⚠️ Perhatian</h4>
                    <p>Anda belum mengajar di kelas manapun. Silakan hubungi admin untuk ditambahkan sebagai guru pengajar.</p>
                </div>
                <?php else: ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="info-box">
                        <h4>ℹ️ Informasi</h4>
                        <ul>
                            <li>Pilih kelas dan mata pelajaran yang Anda ajar</li>
                            <li>File materi/soal bersifat opsional (max 10MB)</li>
                            <li>Deadline bersifat opsional</li>
                        </ul>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Kelas <span style="color: red;">*</span></label>
                            <select name="id_kelas" id="id_kelas" required onchange="updatePelajaranOptions()">
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list as $id_kelas => $kelas_data): ?>
                                <option value="<?php echo $id_kelas; ?>">
                                    <?php echo htmlspecialchars($kelas_data['nama_kelas']) . ' - ' . htmlspecialchars($kelas_data['tahun_ajaran']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Mata Pelajaran <span style="color: red;">*</span></label>
                            <select name="id_pelajaran" id="id_pelajaran" required disabled>
                                <option value="">-- Pilih Kelas Dulu --</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Judul Tugas <span style="color: red;">*</span></label>
                            <input type="text" name="judul_tugas" required placeholder="Contoh: Tugas Bab 1 - Pengenalan">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Deskripsi Tugas</label>
                            <textarea name="deskripsi" placeholder="Jelaskan detail tugas, instruksi pengerjaan, dll (opsional)"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Deadline (Opsional)</label>
                            <input type="datetime-local" name="deadline">
                            <small style="color: #718096; font-size: 12px; display: block; margin-top: 5px;">Batas waktu pengumpulan</small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>File Materi/Soal (Opsional)</label>
                            
                            <!-- Pilihan: Upload baru atau dari arsip -->
                            <div style="margin-bottom: 15px;">
                                <label style="display: inline-flex; align-items: center; margin-right: 20px; font-weight: normal; cursor: pointer;">
                                    <input type="radio" name="use_arsip" value="upload" checked onchange="toggleFileSource()" style="margin-right: 8px; width: auto;">
                                    Upload File Baru
                                </label>
                                <label style="display: inline-flex; align-items: center; font-weight: normal; cursor: pointer;">
                                    <input type="radio" name="use_arsip" value="arsip" onchange="toggleFileSource()" style="margin-right: 8px; width: auto;">
                                    Pilih dari Arsip
                                </label>
                            </div>
                            
                            <!-- Upload file baru -->
                            <div id="upload_section">
                                <div class="file-input-wrapper">
                                    <input type="file" name="file" id="file_create" onchange="displayCreateFileName()">
                                    <label for="file_create" class="file-input-label">
                                        📁 Klik untuk memilih file
                                    </label>
                                    <div class="file-name" id="file_create_name"></div>
                                </div>
                                <small style="color: #718096; font-size: 12px; display: block; margin-top: 5px;">PDF, DOC, PPT, Excel, Gambar, ZIP (max 10MB)</small>
                            </div>
                            
                            <!-- Pilih dari arsip -->
                            <div id="arsip_section" style="display: none;">
                                <?php if (count($arsip_list) > 0): ?>
                                <select name="id_arsip" id="id_arsip" style="width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 8px;">
                                    <option value="">-- Pilih File dari Arsip --</option>
                                    <?php foreach ($arsip_list as $arsip): ?>
                                    <option value="<?php echo $arsip['id_arsip']; ?>" 
                                            data-filename="<?php echo htmlspecialchars($arsip['file_name']); ?>"
                                            data-size="<?php echo formatSize($arsip['file_size']); ?>"
                                            data-type="<?php echo strtoupper($arsip['file_type']); ?>">
                                        <?php echo htmlspecialchars($arsip['judul_arsip']); ?> 
                                        (<?php echo htmlspecialchars($arsip['file_name']); ?> - <?php echo formatSize($arsip['file_size']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="arsip_preview" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px; display: none;">
                                    <strong style="color: #11998e;">File dipilih:</strong><br>
                                    <span id="arsip_filename"></span> 
                                    (<span id="arsip_size"></span> • <span id="arsip_type"></span>)
                                </div>
                                <small style="color: #718096; font-size: 12px; display: block; margin-top: 8px;">
                                    File akan digunakan dari arsip Anda. 
                                    <a href="arsip_guru.php" target="_blank" style="color: #11998e;">Kelola Arsip →</a>
                                </small>
                                <?php else: ?>
                                <div style="text-align: center; padding: 20px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffa726;">
                                    <p style="color: #856404; margin-bottom: 10px;">📁 Belum ada file di arsip</p>
                                    <a href="arsip_guru.php" target="_blank" class="btn btn-warning btn-sm">
                                        Upload ke Arsip Dulu
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">✓ Buat Tugas</button>
                        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Batal</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
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

    <!-- Modal Change Status -->
    <div class="modal" id="statusModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>🔄 Ubah Status Tugas</h3>
                <button class="modal-close" onclick="closeStatusModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="id_tugas" id="status_id_tugas">
                    
                    <div class="form-group">
                        <label>Pilih Status Baru</label>
                        <select name="status" id="status_new" required>
                            <option value="aktif">Aktif</option>
                            <option value="selesai">Selesai</option>
                            <option value="tertutup">Tertutup</option>
                        </select>
                        <small style="color: #718096; font-size: 12px; display: block; margin-top: 8px;">
                            • Aktif: Tugas dapat dikerjakan siswa<br>
                            • Selesai: Tugas sudah selesai dikerjakan<br>
                            • Tertutup: Tugas ditutup, tidak dapat dikerjakan
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">✓ Ubah Status</button>
                        <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Batal</button>
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
                    Apakah Anda yakin ingin menghapus tugas <strong id="delete_tugas_name"></strong>?
                </p>
                <p style="color: #e53e3e; font-size: 14px; margin-bottom: 20px;">
                    ⚠️ Data yang sudah dihapus tidak dapat dikembalikan!
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_tugas" id="delete_id_tugas">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Data kelas dan pelajaran dari PHP
        const kelasPelajaran = <?php echo json_encode($kelas_list); ?>;
        
        function updatePelajaranOptions() {
            const kelasSelect = document.getElementById('id_kelas');
            const pelajaranSelect = document.getElementById('id_pelajaran');
            const idKelas = kelasSelect.value;
            
            pelajaranSelect.innerHTML = '<option value="">-- Pilih Mata Pelajaran --</option>';
            
            if (idKelas && kelasPelajaran[idKelas]) {
                pelajaranSelect.disabled = false;
                kelasPelajaran[idKelas].pelajaran.forEach(pel => {
                    const option = document.createElement('option');
                    option.value = pel.id_pelajaran;
                    option.textContent = pel.nama_pelajaran;
                    pelajaranSelect.appendChild(option);
                });
            } else {
                pelajaranSelect.disabled = true;
            }
        }
        
        function displayCreateFileName() {
            const input = document.getElementById('file_create');
            const fileNameDisplay = document.getElementById('file_create_name');
            
            if (input.files.length > 0) {
                const file = input.files[0];
                const size = (file.size / 1024 / 1024).toFixed(2);
                fileNameDisplay.textContent = `📎 ${file.name} (${size} MB)`;
            } else {
                fileNameDisplay.textContent = '';
            }
        }
        
        function toggleFileSource() {
            const useArsip = document.querySelector('input[name="use_arsip"]:checked').value;
            const uploadSection = document.getElementById('upload_section');
            const arsipSection = document.getElementById('arsip_section');
            
            if (useArsip === 'arsip') {
                uploadSection.style.display = 'none';
                arsipSection.style.display = 'block';
                // Clear file input
                document.getElementById('file_create').value = '';
                document.getElementById('file_create_name').textContent = '';
            } else {
                uploadSection.style.display = 'block';
                arsipSection.style.display = 'none';
                // Clear arsip selection
                const arsipSelect = document.getElementById('id_arsip');
                if (arsipSelect) {
                    arsipSelect.value = '';
                    document.getElementById('arsip_preview').style.display = 'none';
                }
            }
        }
        
        // Preview file dari arsip
        document.addEventListener('DOMContentLoaded', function() {
            const arsipSelect = document.getElementById('id_arsip');
            if (arsipSelect) {
                arsipSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const preview = document.getElementById('arsip_preview');
                    
                    if (this.value) {
                        document.getElementById('arsip_filename').textContent = selectedOption.dataset.filename;
                        document.getElementById('arsip_size').textContent = selectedOption.dataset.size;
                        document.getElementById('arsip_type').textContent = selectedOption.dataset.type;
                        preview.style.display = 'block';
                    } else {
                        preview.style.display = 'none';
                    }
                });
            }
        });
        
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
        }
        
        function viewTugas(tugas) {
            const content = `
                <div style="line-height: 1.8;">
                    <h4 style="color: #2d3748; margin-bottom: 15px; font-size: 20px;">${tugas.judul_tugas}</h4>
                    
                    <div style="margin-bottom: 20px;">
                        <strong style="color: #555;">📚 Mata Pelajaran:</strong> ${tugas.nama_pelajaran}<br>
                        <strong style="color: #555;">🏫 Kelas:</strong> ${tugas.nama_kelas} - ${tugas.tahun_ajaran}<br>
                        <strong style="color: #555;">👥 Jumlah Siswa:</strong> ${tugas.jumlah_siswa} siswa<br>
                        <strong style="color: #555;">📅 Dibuat:</strong> ${new Date(tugas.tanggal_dibuat).toLocaleString('id-ID')}<br>
                        ${tugas.deadline ? '<strong style="color: #555;">⏰ Deadline:</strong> ' + new Date(tugas.deadline).toLocaleString('id-ID') + '<br>' : ''}
                        <strong style="color: #555;">📊 Status:</strong> <span class="badge badge-${tugas.status === 'aktif' ? 'success' : tugas.status === 'selesai' ? 'warning' : 'secondary'}">${tugas.status.charAt(0).toUpperCase() + tugas.status.slice(1)}</span>
                    </div>
                    
                    ${tugas.deskripsi ? '<div style="margin-bottom: 20px;"><strong style="color: #555;">📝 Deskripsi:</strong><div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 8px; white-space: pre-wrap;">' + tugas.deskripsi + '</div></div>' : ''}
                    
                    ${tugas.file_name ? '<div style="margin-bottom: 20px;"><strong style="color: #555;">📎 File Lampiran:</strong><br><a href="' + tugas.file_path + '" target="_blank" class="btn btn-success btn-sm" style="margin-top: 8px;">📥 Download ' + tugas.file_name + '</a></div>' : ''}
                </div>
            `;
            document.getElementById('viewModalContent').innerHTML = content;
            document.getElementById('viewModal').classList.add('active');
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
        
        function changeStatus(idTugas, currentStatus) {
            document.getElementById('status_id_tugas').value = idTugas;
            document.getElementById('status_new').value = currentStatus;
            document.getElementById('statusModal').classList.add('active');
        }
        
        function closeStatusModal() {
            document.getElementById('statusModal').classList.remove('active');
        }
        
        function deleteTugas(id, nama) {
            document.getElementById('delete_id_tugas').value = id;
            document.getElementById('delete_tugas_name').textContent = nama;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['createModal', 'viewModal', 'statusModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
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