<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah guru
checkUserType(['guru']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Koneksi database
$pdo = getDBConnection();

// Ambil nama guru untuk folder
$stmt = $pdo->prepare("SELECT nama_guru FROM user_guru WHERE id_guru = ?");
$stmt->execute([$user_id]);
$guru_data = $stmt->fetch();
$nama_guru = $guru_data['nama_guru'];

// Buat folder arsip jika belum ada
$upload_base_dir = '../arsip/guru/';
$guru_folder = $upload_base_dir . $nama_guru . '/';

if (!file_exists($upload_base_dir)) {
    mkdir($upload_base_dir, 0777, true);
}

if (!file_exists($guru_folder)) {
    mkdir($guru_folder, 0777, true);
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
            $file_path = $guru_folder . $unique_name;

            // Upload file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception("Gagal mengupload file!");
            }

            // Simpan ke database
            $stmt = $pdo->prepare("
                INSERT INTO arsip (judul_arsip, file_path, file_name, file_size, file_type, id_uploader, tipe_uploader)
                VALUES (?, ?, ?, ?, ?, ?, 'guru')
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
            $stmt = $pdo->prepare("SELECT * FROM arsip WHERE id_arsip = ? AND id_uploader = ? AND tipe_uploader = 'guru'");
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

// Ambil semua arsip guru
$search = $_GET['search'] ?? '';

$query = "
    SELECT * FROM arsip
    WHERE id_uploader = ? AND tipe_uploader = 'guru'
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
$stmt = $pdo->prepare("SELECT SUM(file_size) as total_size FROM arsip WHERE id_uploader = ? AND tipe_uploader = 'guru'");
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
    <title>Arsip - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/arsip_guru.css">
</head>
<body class="light-theme">

    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">ARSIP GURU</div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
            <div class="position-relative">
                <i class="fas fa-bell" style="font-size: 1.25rem; cursor: pointer;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">3</span>
            </div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($nama_guru); ?>&background=10b981&color=fff" alt="Profile" class="profile-img">
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sidebar" id="sidebar">
        <div class="menu-section">
            <div class="menu-section-title">Main Menu</div>
            <a href="../dashboard/dashboard_guru.php" class="menu-item " data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>DASHBOARD</span>
            </a>
            <a href="../tugas/tugas_guru.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Tugas & Materi">
                <i class="fas fa-book-open"></i>
                <span>TUGAS & MATERI</span>
            </a>
            <a href="../nilai/penilaian.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Penilaian">
                <i class="fas fa-clipboard-check"></i>
                <span>PENILAIAN</span>
            </a>
            <a href="#" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Arsip">
                <i class="fas fa-archive"></i>
                <span>ARSIP</span>
            </a>
        </div>

        <div class="menu-section">
            <a href="../login.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="mb-4">
            <h2 class="mb-1">📁 Arsip Guru</h2>
            <p class="text-secondary">Upload dan kelola file arsip pembelajaran Anda</p>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Stats Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card blue">
                    <div class="stat-icon blue">
                        <i class="fas fa-files"></i>
                    </div>
                    <div class="stat-value"><?php echo count($all_arsip); ?></div>
                    <div class="stat-label">Total File</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-value"><?php echo formatSize($total_size); ?></div>
                    <div class="stat-label">Total Ukuran</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="stat-value"><?php echo htmlspecialchars($nama_guru); ?></div>
                    <div class="stat-label">Folder</div>
                </div>
            </div>
        </div>

        <!-- Form Upload -->
        <div class="dashboard-card mb-4">
            <h5 class="fw-bold mb-4">📤 Upload File Baru</h5>

            <div class="alert alert-info">
                <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Ketentuan Upload</h6>
                <ul class="mb-0">
                    <li>Ukuran file maksimal: <strong>10MB</strong></li>
                    <li>Tipe file yang diizinkan: <strong>PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP, RAR</strong></li>
                    <li>File akan disimpan di folder: <strong>arsip/<?php echo htmlspecialchars($nama_guru); ?>/</strong></li>
                </ul>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">

                <div class="mb-3">
                    <label class="form-label">Judul Arsip <span class="text-danger">*</span></label>
                    <input type="text" name="judul_arsip" class="form-control" required placeholder="Masukkan judul/deskripsi file">
                </div>

                <div class="mb-3">
                    <label class="form-label">Pilih File <span class="text-danger">*</span></label>
                    <input type="file" name="file" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="fas fa-upload me-2"></i>Upload File
                </button>
            </form>
        </div>

        <!-- Daftar Arsip -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">📂 Daftar Arsip</h5>

                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control" placeholder="Cari judul atau nama file..." value="<?php echo htmlspecialchars($search); ?>" style="width: 300px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                    <?php if (!empty($search)): ?>
                    <a href="arsip_guru.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (count($all_arsip) > 0): ?>
            <div class="row g-4">
                <?php foreach ($all_arsip as $arsip): ?>
                <?php
                // Icon berdasarkan tipe file
                $icon_map = [
                    'pdf' => 'fa-file-pdf text-danger',
                    'doc' => 'fa-file-word text-primary', 'docx' => 'fa-file-word text-primary',
                    'xls' => 'fa-file-excel text-success', 'xlsx' => 'fa-file-excel text-success',
                    'ppt' => 'fa-file-powerpoint text-warning', 'pptx' => 'fa-file-powerpoint text-warning',
                    'jpg' => 'fa-file-image text-info', 'jpeg' => 'fa-file-image text-info', 'png' => 'fa-file-image text-info',
                    'zip' => 'fa-file-archive text-warning', 'rar' => 'fa-file-archive text-warning'
                ];
                $icon = $icon_map[$arsip['file_type']] ?? 'fa-file text-secondary';
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="dashboard-card h-100">
                        <div class="d-flex align-items-center mb-3">
                            <div class="stat-icon me-3" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas <?php echo $icon; ?>" style="font-size: 1.5rem;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-0 text-truncate" title="<?php echo htmlspecialchars($arsip['judul_arsip']); ?>">
                                    <?php echo htmlspecialchars($arsip['judul_arsip']); ?>
                                </h6>
                                <small class="text-muted"><?php echo htmlspecialchars($arsip['file_name']); ?></small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between text-sm">
                                <span>
                                    <i class="fas fa-database me-1"></i>
                                    <?php echo formatSize($arsip['file_size']); ?>
                                </span>
                                <span class="badge bg-primary"><?php echo strtoupper($arsip['file_type']); ?></span>
                            </div>
                            <div class="text-sm text-muted">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d M Y', strtotime($arsip['tanggal_upload'])); ?>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary flex-grow-1">
                                <i class="fas fa-eye me-1"></i>Lihat
                            </a>
                            <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" download class="btn btn-sm btn-outline-success flex-grow-1">
                                <i class="fas fa-download me-1"></i>Download
                            </a>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteFile(<?php echo $arsip['id_arsip']; ?>, '<?php echo htmlspecialchars($arsip['judul_arsip']); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div style="font-size: 64px; margin-bottom: 20px;">📁</div>
                <h3 class="mb-3">Belum Ada File Arsip</h3>
                <p class="text-muted">Upload file pertama Anda di form di atas</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        let tooltipList = [];

        function isMobile() {
            return window.innerWidth <= 768;
        }

        if (isMobile()) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            updateTooltips();
        });

        function updateTooltips() {
            tooltipList.forEach(tooltip => tooltip.dispose());
            tooltipList = [];

            if (sidebar.classList.contains('collapsed')) {
                const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipList = [...tooltipElements].map(el => {
                    return new bootstrap.Tooltip(el, {
                        trigger: 'hover'
                    });
                });
            }
        }

        updateTooltips();

        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-theme');
            body.classList.toggle('light-theme');

            const icon = themeToggle.querySelector('i');
            if (body.classList.contains('dark-theme')) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });

        // Load saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.remove('light-theme');
            body.classList.add('dark-theme');
            document.querySelector('#themeToggle i').classList.replace('fa-moon', 'fa-sun');
        }

        window.addEventListener('resize', () => {
            if (isMobile() && !sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                updateTooltips();
            }
        });

        function deleteFile(id, nama) {
            if(confirm('Apakah Anda yakin ingin menghapus file "' + nama + '" ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id_arsip" value="'+id+'">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function displayFileName() {
            const input = document.getElementById('file');
            const file = input.files[0];

            if (file) {
                alert('File dipilih: ' + file.name);
            }
        }
    </script>
</body>
</html>
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
    </script>
</body>
</html>