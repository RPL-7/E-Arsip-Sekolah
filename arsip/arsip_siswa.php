<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah siswa
checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Koneksi database
$pdo = getDBConnection();

// Ambil data siswa untuk folder
$stmt = $pdo->prepare("SELECT nama_siswa, nis FROM user_siswa WHERE id_siswa = ?");
$stmt->execute([$user_id]);
$siswa_data = $stmt->fetch();
$nama_siswa = $siswa_data['nama_siswa'];
$nis = $siswa_data['nis'];

// Buat folder arsip jika belum ada
$upload_base_dir = '../arsip/siswa/';
$siswa_folder = $upload_base_dir . $nis . '_' . $nama_siswa . '/';

if (!file_exists($upload_base_dir)) {
    mkdir($upload_base_dir, 0777, true);
}

if (!file_exists($siswa_folder)) {
    mkdir($siswa_folder, 0777, true);
}

// Proses form
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload') {
        try {
            $judul_arsip = trim($_POST['judul_arsip']);
            
            // Validasi
            if (empty($judul_arsip)) {
                throw new Exception("Judul arsip wajib diisi!");
            }
            
            if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new Exception("File wajib dipilih!");
            }
            
            $file = $_FILES['file'];
            
            // Validasi upload error
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error saat upload file!");
            }
            
            // Validasi ukuran file (max 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB
            if ($file['size'] > $max_size) {
                throw new Exception("Ukuran file maksimal 10MB!");
            }
            
            // Validasi tipe file
            $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
            $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_extension, $allowed_types)) {
                throw new Exception("Tipe file tidak diizinkan! Hanya: " . implode(', ', $allowed_types));
            }
            
            // Generate nama file unik
            $file_name = $file['name'];
            $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
            $file_path = $siswa_folder . $unique_name;
            
            // Upload file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception("Gagal mengupload file!");
            }
            
            // Simpan ke database
            $stmt = $pdo->prepare("
                INSERT INTO arsip (judul_arsip, file_path, file_name, file_size, file_type, id_uploader, tipe_uploader)
                VALUES (?, ?, ?, ?, ?, ?, 'siswa')
            ");
            
            $stmt->execute([
                $judul_arsip,
                $file_path,
                $file_name,
                $file['size'],
                $file_extension,
                $user_id
            ]);
            
            $success_message = "File berhasil diupload!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $id_arsip = $_POST['id_arsip'];
            
            // Ambil data arsip
            $stmt = $pdo->prepare("SELECT * FROM arsip WHERE id_arsip = ? AND id_uploader = ? AND tipe_uploader = 'siswa'");
            $stmt->execute([$id_arsip, $user_id]);
            $arsip = $stmt->fetch();
            
            if (!$arsip) {
                throw new Exception("File tidak ditemukan atau Anda tidak memiliki akses!");
            }
            
            // Hapus file fisik
            if (file_exists($arsip['file_path'])) {
                unlink($arsip['file_path']);
            }
            
            // Hapus dari database
            $stmt = $pdo->prepare("DELETE FROM arsip WHERE id_arsip = ?");
            $stmt->execute([$id_arsip]);
            
            $success_message = "File berhasil dihapus!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Ambil semua arsip siswa
$search = $_GET['search'] ?? '';

$query = "
    SELECT * FROM arsip 
    WHERE id_uploader = ? AND tipe_uploader = 'siswa'
";
$params = [$user_id];

if (!empty($search)) {
    $query .= " AND (judul_arsip LIKE ? OR file_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY tanggal_upload DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_arsip = $stmt->fetchAll();

// Hitung total ukuran file
$stmt = $pdo->prepare("SELECT SUM(file_size) as total_size FROM arsip WHERE id_uploader = ? AND tipe_uploader = 'siswa'");
$stmt->execute([$user_id]);
$total_size = $stmt->fetch()['total_size'] ?? 0;

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
    <title>Arsip Siswa - <?php echo htmlspecialchars($nama_siswa); ?></title>
    <link rel="stylesheet" href="../css/arsip_siswa.css">
</head>
<body>
    <div class="navbar">
        <h1>📁 Arsip Saya</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong> (<?php echo htmlspecialchars($nis); ?>)</span>
            <a href="../dashboard/dashboard_siswa.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <div class="page-header-text">
                <h2>Manajemen Arsip Pribadi</h2>
                <p>Upload dan kelola file arsip pribadi Anda</p>
            </div>
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
                <h3>Total File</h3>
                <div class="number"><?php echo count($all_arsip); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Total Ukuran</h3>
                <div class="number" style="color: #38ef7d; font-size: 20px;"><?php echo formatSize($total_size); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Folder</h3>
                <div class="number" style="color: #ffa726; font-size: 18px;"><?php echo htmlspecialchars($nis); ?></div>
            </div>
        </div>

        <!-- Form Upload -->
        <div class="card">
            <div class="card-header">
                <h3>📤 Upload File Baru</h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <h4>ℹ️ Ketentuan Upload</h4>
                    <ul>
                        <li>Ukuran file maksimal: <strong>10MB</strong></li>
                        <li>Tipe file yang diizinkan: <strong>PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP, RAR</strong></li>
                        <li>File akan disimpan di folder pribadi Anda: <strong>arsip/siswa/<?php echo htmlspecialchars($nis); ?>/</strong></li>
                        <li>Gunakan untuk menyimpan: <strong>Catatan, Tugas, Materi, atau File Penting Lainnya</strong></li>
                    </ul>
                </div>

                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="form-group">
                        <label>Judul Arsip <span style="color: red;">*</span></label>
                        <input type="text" name="judul_arsip" required placeholder="Contoh: Catatan Matematika, Tugas IPA, dll">
                    </div>
                    
                    <div class="form-group">
                        <label>Pilih File <span style="color: red;">*</span></label>
                        <div class="file-input-wrapper">
                            <input type="file" name="file" id="file" required onchange="displayFileName()">
                            <label for="file" class="file-input-label">
                                📁 Klik untuk memilih file
                            </label>
                            <div class="file-name" id="file-name"></div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-success">✓ Upload File</button>
                </form>
            </div>
        </div>

        <!-- Daftar Arsip -->
        <div class="card">
            <div class="card-header">
                <h3>📂 Daftar Arsip Saya</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari judul atau nama file..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search)): ?>
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
                            <button class="btn btn-danger btn-sm" onclick="deleteFile(<?php echo $arsip['id_arsip']; ?>, '<?php echo htmlspecialchars($arsip['judul_arsip']); ?>')">
                                🗑️ Hapus
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #a0aec0;">
                    <div style="font-size: 64px; margin-bottom: 20px;">📁</div>
                    <p>Belum ada file arsip</p>
                    <p style="font-size: 14px; margin-top: 10px;">Upload file pertama Anda di form di atas</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Delete -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Konfirmasi Hapus</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #2d3748;">
                    Apakah Anda yakin ingin menghapus file <strong id="delete_file_name"></strong>?
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
        function displayFileName() {
            const input = document.getElementById('file');
            const fileNameDisplay = document.getElementById('file-name');
            
            if (input.files.length > 0) {
                const file = input.files[0];
                const size = (file.size / 1024 / 1024).toFixed(2);
                fileNameDisplay.textContent = `📎 ${file.name} (${size} MB)`;
            } else {
                fileNameDisplay.textContent = '';
            }
        }
        
        function deleteFile(id, nama) {
            document.getElementById('delete_id_arsip').value = id;
            document.getElementById('delete_file_name').textContent = nama;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
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