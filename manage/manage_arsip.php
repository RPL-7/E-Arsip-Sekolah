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
    <title>Manajemen Arsip - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/manage_arsip.css">
</head>
<body class="light-theme">

    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">MANAJEMEN ARSIP</div>
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
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=10b981&color=fff" alt="Profile" class="profile-img">
                    <span class="ms-2 d-none d-md-block"><?php echo htmlspecialchars($user_name); ?></span>
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
            <div class="menu-section-title">Admin Menu</div>
            <a href="../dashboard/dashboard_admin.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>DASHBOARD</span>
            </a>
            <a href="../manage/manage_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Siswa">
                <i class="fas fa-user-graduate"></i>
                <span>MANAJEMEN SISWA</span>
            </a>
            <a href="../manage/manage_guru.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Guru">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>MANAJEMEN GURU</span>
            </a>
            <a href="../manage/manage_kelas.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Kelas">
                <i class="fas fa-school"></i>
                <span>MANAJEMEN KELAS</span>
            </a>
            <a href="../manage/manage_pelajaran.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Pelajaran">
                <i class="fas fa-book"></i>
                <span>MANAJEMEN PELAJARAN</span>
            </a>
            <a href="manage_arsip.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Arsip">
                <i class="fas fa-archive"></i>
                <span>MANAJEMEN ARSIP</span>
            </a>
        </div>

        <div class="menu-section">
            <a href="../logout.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="mb-4">
            <h2 class="mb-1">📁 Manajemen Arsip</h2>
            <p class="text-secondary">Kelola semua arsip yang diupload oleh guru dan siswa</p>
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
            <div class="col-md-3">
                <div class="stat-card blue">
                    <div class="stat-icon blue">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_arsip; ?></div>
                    <div class="stat-label">Total Arsip</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card teal">
                    <div class="stat-icon teal">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-value"><?php echo $arsip_guru; ?></div>
                    <div class="stat-label">Arsip Guru</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="stat-value"><?php echo $arsip_siswa; ?></div>
                    <div class="stat-label">Arsip Siswa</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-value"><?php echo formatSize($total_size); ?></div>
                    <div class="stat-label">Total Ukuran</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="dashboard-card mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Cari Arsip</label>
                    <input type="text" name="search" class="form-control" placeholder="Cari judul, nama file, atau uploader..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipe Uploader</label>
                    <select name="tipe" class="form-select">
                        <option value="">Semua Tipe</option>
                        <option value="guru" <?php echo $tipe_filter === 'guru' ? 'selected' : ''; ?>>Guru</option>
                        <option value="siswa" <?php echo $tipe_filter === 'siswa' ? 'selected' : ''; ?>>Siswa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Cari
                    </button>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <?php if (!empty($search) || !empty($tipe_filter) || !empty($uploader_filter)): ?>
                    <a href="manage_arsip.php" class="btn btn-secondary w-100">Reset</a>
                    <?php else: ?>
                    <div class="w-100"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tabel Arsip -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Daftar Arsip (<?php echo count($all_arsip); ?>)</h5>
            </div>

            <?php if (count($all_arsip) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
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
                        $badge_class = 'bg-info';
                        if ($arsip['tipe_uploader'] === 'siswa') $badge_class = 'bg-success';
                        elseif ($arsip['tipe_uploader'] === 'admin') $badge_class = 'bg-warning text-dark';

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
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($arsip['judul_arsip']); ?></strong>
                            </td>
                            <td>
                                <i class="fas <?php echo $icon; ?> me-2"></i><?php echo htmlspecialchars($arsip['file_name']); ?>
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
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($arsip['info_uploader']); ?>
                                </small>
                            </td>
                            <td>
                                <?php echo date('d M Y H:i', strtotime($arsip['tanggal_upload'])); ?>
                            </td>
                            <td>
                                <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="fas fa-eye me-1"></i>Lihat
                                </a>
                                <a href="<?php echo htmlspecialchars($arsip['file_path']); ?>" download class="btn btn-sm btn-outline-success me-1">
                                    <i class="fas fa-download me-1"></i>Download
                                </a>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteArsip(<?php echo $arsip['id_arsip']; ?>, '<?php echo htmlspecialchars($arsip['judul_arsip']); ?>', '<?php echo htmlspecialchars($arsip['nama_uploader']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div style="font-size: 64px; margin-bottom: 20px;">📁</div>
                <h3 class="mb-3">Tidak Ada Arsip</h3>
                <p class="text-muted">Belum ada arsip yang diupload di sistem</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Delete -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">🗑️ Konfirmasi Hapus Arsip</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Apakah Anda yakin ingin menghapus arsip <strong id="delete_arsip_name"></strong>?
                    </p>
                    <p class="text-muted">
                        Diupload oleh: <strong id="delete_uploader_name"></strong>
                    </p>
                    <p class="text-danger">
                        ⚠️ File yang sudah dihapus tidak dapat dikembalikan!
                    </p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_arsip" id="delete_id_arsip">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                </div>
            </div>
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

        function deleteArsip(id, nama, uploader) {
            document.getElementById('delete_id_arsip').value = id;
            document.getElementById('delete_arsip_name').textContent = nama;
            document.getElementById('delete_uploader_name').textContent = uploader;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Auto close alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>