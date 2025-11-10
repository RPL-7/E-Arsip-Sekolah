<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah admin
checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$user_username = $_SESSION['user_username'];

// Koneksi database
$pdo = getDBConnection();

// Ambil statistik
try {
    // Total siswa aktif
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'aktif'");
    $total_siswa = $stmt->fetch()['total'];

    // Total guru aktif
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_guru WHERE status = 'aktif'");
    $total_guru = $stmt->fetch()['total'];

    // Total kelas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas");
    $total_kelas = $stmt->fetch()['total'];

    // Total pelajaran
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pelajaran");
    $total_pelajaran = $stmt->fetch()['total'];

    // Total arsip
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM arsip");
    $total_arsip = $stmt->fetch()['total'];

    // Total admin
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM admin");
    $total_admin = $stmt->fetch()['total'];

    // Data siswa terbaru (5 terakhir)
    $stmt = $pdo->query("
        SELECT s.nis, s.nama_siswa, k.nama_kelas, s.status
        FROM user_siswa s
        LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
        ORDER BY s.id_siswa DESC
        LIMIT 5
    ");
    $siswa_terbaru = $stmt->fetchAll();

    // Data guru terbaru (5 terakhir)
    $stmt = $pdo->query("
        SELECT id_guru, nama_guru, email, status
        FROM user_guru
        ORDER BY id_guru DESC
        LIMIT 5
    ");
    $guru_terbaru = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard_admin.css">
</head>

<body class="light-theme">
    <!-- Header -->
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">DASHBOARD ADMIN</div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
            <div class="position-relative">
                <i class="fas fa-bell" style="font-size: 1.25rem; cursor: pointer;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                    style="font-size: 0.65rem;">3</span>
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

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu-section">
            <div class="menu-section-title">Main Menu</div>
            <a href="dashboard_admin.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>DASHBOARD</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Manajemen Data</div>
            <a href="../manage/manage_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Siswa">
                <i class="fas fa-user-graduate"></i>
                <span>MANAJEMEN SISWA</span>
            </a>
            <a href="../manage/manage_guru.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Guru">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>MANAJEMEN GURU</span>
            </a>
            <a href="../manage/manage_kelas.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Kelas">
                <i class="fas fa-door-open"></i>
                <span>MANAJEMEN KELAS</span>
            </a>
            <a href="../manage/manage_pelajaran.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Pelajaran">
                <i class="fas fa-book"></i>
                <span>MANAJEMEN PELAJARAN</span>
            </a>
            <a href="../manage/manage_admin.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Admin">
                <i class="fas fa-user-shield"></i>
                <span>MANAJEMEN ADMIN</span>
            </a>
            <a href="../manage/manage_arsip.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Arsip">
                <i class="fas fa-archive"></i>
                <span>MANAJEMEN ARSIP</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Tambah Data</div>
            <a href="../add/tambah_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Tambah Siswa">
                <i class="fas fa-user-plus"></i>
                <span>TAMBAH SISWA</span>
            </a>
            <a href="../add/tambah_guru.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Tambah Guru">
                <i class="fas fa-user-plus"></i>
                <span>TAMBAH GURU</span>
            </a>
            <a href="../add/tambah_kelas.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Tambah Kelas">
                <i class="fas fa-plus-square"></i>
                <span>TAMBAH KELAS</span>
            </a>
            <a href="../add/tambah_pelajaran.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Tambah Pelajaran">
                <i class="fas fa-plus-circle"></i>
                <span>TAMBAH PELAJARAN</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Pengaturan</div>
            <a href="../logout.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h2>Selamat Datang, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
            <p>Berikut adalah ringkasan sistem pada <?php echo date('d F Y'); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid row mb-4">
            <div class="col-md-2 mb-3">
                <div class="stat-card stat-card-blue">
                    <div class="stat-card-icon icon-blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_siswa; ?></div>
                    <div class="stat-label">Total Siswa</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card stat-card-green">
                    <div class="stat-card-icon icon-green">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_guru; ?></div>
                    <div class="stat-label">Total Guru</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card stat-card-purple">
                    <div class="stat-card-icon icon-purple">
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_kelas; ?></div>
                    <div class="stat-label">Total Kelas</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card stat-card-orange">
                    <div class="stat-card-icon icon-orange">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_pelajaran; ?></div>
                    <div class="stat-label">Total Pelajaran</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card stat-card-red">
                    <div class="stat-card-icon icon-red">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_arsip; ?></div>
                    <div class="stat-label">Total Arsip</div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="stat-card stat-card-teal">
                    <div class="stat-card-icon icon-teal">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_admin; ?></div>
                    <div class="stat-label">Total Admin</div>
                </div>
            </div>
        </div>

        <!-- Quick Menu -->
        <div class="row mb-4">
            <div class="col-md-2 mb-3">
                <a href="../add/tambah_siswa.php" class="menu-card d-block text-decoration-none">
                    <div class="stat-card-icon icon-blue mb-2">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="h6">Tambah Siswa Baru</h3>
                    <p class="small">Daftarkan siswa baru ke dalam sistem</p>
                </a>
            </div>
            <div class="col-md-2 mb-3">
                <a href="../add/tambah_guru.php" class="menu-card d-block text-decoration-none">
                    <div class="stat-card-icon icon-green mb-2">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="h6">Tambah Guru Baru</h3>
                    <p class="small">Daftarkan guru baru dan buat akun</p>
                </a>
            </div>
            <div class="col-md-2 mb-3">
                <a href="../add/tambah_kelas.php" class="menu-card d-block text-decoration-none">
                    <div class="stat-card-icon icon-purple mb-2">
                        <i class="fas fa-plus-square"></i>
                    </div>
                    <h3 class="h6">Tambah Kelas Baru</h3>
                    <p class="small">Buat kelas baru dan tentukan wali kelas</p>
                </a>
            </div>
            <div class="col-md-2 mb-3">
                <a href="../add/tambah_pelajaran.php" class="menu-card d-block text-decoration-none">
                    <div class="stat-card-icon icon-orange mb-2">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <h3 class="h6">Tambah Pelajaran</h3>
                    <p class="small">Tambahkan mata pelajaran baru</p>
                </a>
            </div>
            <div class="col-md-2 mb-3">
                <a href="../manage/manage_arsip.php" class="menu-card d-block text-decoration-none">
                    <div class="stat-card-icon icon-red mb-2">
                        <i class="fas fa-upload"></i>
                    </div>
                    <h3 class="h6">Manajemen Arsip</h3>
                    <p class="small">Upload dokumen dan arsip sekolah</p>
                </a>
            </div>
            <div class="col-md-2 mb-3">
                <a href="#" class="menu-card d-block text-decoration-none">
                    <div class="stat-card-icon icon-teal mb-2">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h3 class="h6">Lihat Laporan</h3>
                    <p class="small">Lihat laporan lengkap sistem</p>
                </a>
            </div>
        </div>

        <!-- Recent Data Section -->
        <div class="row">
            <!-- Recent Students -->
            <div class="col-md-6 mb-4">
                <div class="dashboard-card">
                    <h3 class="card-title">Siswa Terbaru</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siswa_terbaru as $siswa): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_siswa']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $siswa['status'] === 'aktif' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo htmlspecialchars($siswa['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($siswa_terbaru)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Tidak ada data siswa</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Teachers -->
            <div class="col-md-6 mb-4">
                <div class="dashboard-card">
                    <h3 class="card-title">Guru Terbaru</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guru_terbaru as $guru): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($guru['nama_guru']); ?></td>
                                    <td><?php echo htmlspecialchars($guru['email']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $guru['status'] === 'aktif' ? 'badge-success' : 'badge-warning'; ?>">
                                            <?php echo htmlspecialchars($guru['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($guru_terbaru)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">Tidak ada data guru</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
    </script>
</body>
</html>