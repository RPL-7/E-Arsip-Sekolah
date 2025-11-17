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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/arsip_siswa.css">
</head>
<body class="light-theme">

    <!-- Header -->
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">ARSIP</div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
            <div class="position-relative">
                <i class="fas fa-bell" style="font-size: 1.25rem; cursor: pointer;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">0</span>
            </div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($nama_siswa); ?>&background=10b981&color=fff" alt="Profile" class="profile-img">
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="../profil/profil_siswa.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../../login.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu-section">
            <div class="menu-section-title">Main Menu</div>
            <a href="../dashboard/dashboard_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>DASHBOARD</span>
            </a>
            <a href="../tugas/tugas_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Daftar Tugas">
                <i class="fas fa-tasks"></i>
                <span>DAFTAR TUGAS</span>
            </a>
            <a href="../nilai/nilai_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Daftar Nilai">
                <i class="fas fa-chart-line"></i>
                <span>DAFTAR NILAI</span>
            </a>
            <a href="../arsip/arsip_siswa.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Arsip Materi">
                <i class="fas fa-folder-open"></i>
                <span>ARSIP</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Profil</div>
            <a href="../profil/profil_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Profil Saya">
                <i class="fas fa-id-card"></i>
                <span>PROFIL SAYA</span>
            </a>
        </div>

        <div class="menu-section">
            <a href="../../login.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="mb-4">
            <h2 class="mb-1">📁 Arsip Saya</h2>
            <p class="text-secondary">Upload dan kelola file arsip pribadi Anda</p>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card blue">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Total File</div>
                            <div class="stat-value"><?php echo count($all_arsip); ?></div>
                            <small class="text-muted">Arsip yang Anda upload</small>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-file"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Total Ukuran</div>
                            <div class="stat-value"><?php echo formatSize($total_size); ?></div>
                            <small class="text-success"><i class="fas fa-check-circle"></i> Dalam batas aman</small>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-database"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card purple">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Folder</div>
                            <div class="stat-value"><?php echo htmlspecialchars($nis); ?></div>
                            <small class="text-muted">Kode siswa Anda</small>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-folder"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Upload -->
        <div class="dashboard-card mb-4">
            <h5 class="card-title">📤 Upload File Baru</h5>
            <div class="info-box mb-4 p-3 bg-light rounded">
                <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Ketentuan Upload</h6>
                <ul class="mb-0">
                    <li>Ukuran file maksimal: <strong>10MB</strong></li>
                    <li>Tipe file yang diizinkan: <strong>PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP, RAR</strong></li>
                    <li>File akan disimpan di folder pribadi Anda: <strong>arsip/siswa/<?php echo htmlspecialchars($nis); ?>/</strong></li>
                    <li>Gunakan untuk menyimpan: <strong>Catatan, Tugas, Materi, atau File Penting Lainnya</strong></li>
                </ul>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">

                <div class="mb-3">
                    <label class="form-label">Judul Arsip <span style="color: red;">*</span></label>
                    <input type="text" name="judul_arsip" class="form-control" required placeholder="Contoh: Catatan Matematika, Tugas IPA, dll">
                </div>

                <div class="mb-3">
                    <label class="form-label">Pilih File <span style="color: red;">*</span></label>
                    <input class="form-control" type="file" name="file" required>
                    <div class="form-text">Ukuran maksimal 10MB. Tipe file: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG, ZIP, RAR</div>
                </div>

                <button type="submit" class="btn btn-primary w-100">✓ Upload File</button>
            </form>
        </div>

        <!-- Filter Section -->
        <div class="dashboard-card mb-4">
            <form method="GET" class="filter-bar row g-3 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Cari judul atau nama file..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">🔍 Cari</button>
                </div>
                <div class="col-md-1">
                    <?php if (!empty($search)): ?>
                    <a href="arsip_siswa.php" class="btn btn-outline-secondary w-100">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Daftar Arsip -->
        <div class="dashboard-card">
            <h5 class="card-title">📂 Daftar Arsip Saya</h5>
            <?php if (count($all_arsip) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Judul Arsip</th>
                            <th>Nama File</th>
                            <th>Ukuran</th>
                            <th>Tipe File</th>
                            <th>Tanggal Upload</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_arsip as $index => $arsip): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($arsip['judul_arsip']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($arsip['file_name']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($arsip['file_name']); ?></td>
                            <td><?php echo formatSize($arsip['file_size']); ?></td>
                            <td>
                                <span class="badge bg-primary"><?php echo strtoupper($arsip['file_type']); ?></span>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($arsip['tanggal_upload'])); ?></td>
                            <td>
                                <a href="<?php echo $arsip['file_path']; ?>" class="btn btn-info btn-sm" target="_blank">
                                    <i class="fas fa-download"></i> Buka
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus arsip ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_arsip" value="<?php echo $arsip['id_arsip']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div style="font-size: 64px; margin-bottom: 20px;">📂</div>
                <h3 class="text-muted">Belum Ada Arsip</h3>
                <p class="text-muted">Anda belum memiliki file arsip. Silakan upload file terlebih dahulu.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Delete -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">🗑️ Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>
                        Apakah Anda yakin ingin menghapus file <strong id="delete_file_name"></strong>?
                    </p>
                    <p class="text-danger">
                        ⚠️ File yang sudah dihapus tidak dapat dikembalikan!
                    </p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_arsip" id="delete_id_arsip">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function deleteFile(id, nama) {
            document.getElementById('delete_id_arsip').value = id;
            document.getElementById('delete_file_name').textContent = nama;
            var myModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            myModal.show();
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

        // Sidebar Toggle
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

        // Theme Toggle
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

        // Inisialisasi dropdown
        document.addEventListener('DOMContentLoaded', function() {
            var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
            var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
        });
    </script>
</body>
</html>