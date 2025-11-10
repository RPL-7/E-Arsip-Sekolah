<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah guru
checkUserType(['guru']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

// Ambil parameter page dari URL, default ke 'dashboard'
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Daftar halaman yang diizinkan
$allowed_pages = [
    'dashboard',
    'data_siswa',
    'tugas_materi',
    'penilaian',
    'arsip',
    'wali_kelas',
    'jadwal_mengajar',
    'laporan'
];

// Validasi halaman
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Map menu ke file
$page_files = [
    'dashboard' => 'dashboard_guru.php',
    'tugas_materi' => '../tugas/tugas_guru.php',
    'penilaian' => '../nilai/penilaian.php',
    'arsip' => '../arsip/arsip_guru.php',
];

// Judul halaman
$page_titles = [
    'dashboard' => 'Dashboard Guru',
    'tugas_materi' => 'Tugas & Materi',
    'penilaian' => 'Penilaian',
    'arsip' => 'Arsip',
];

$current_title = $page_titles[$page] ?? 'Dashboard Guru';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_title; ?> - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard_guru.css">
</head>
<body class="light-theme">
    
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">DASHBOARD GURU</div>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
            <div class="position-relative">
                <i class="fas fa-bell" style="font-size: 1.25rem; cursor: pointer;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">5</span>
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
            <div class="menu-section-title">Main Menu</div>
            <a href="dashboard_guru.php" class="menu-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>DASHBOARD</span>
            </a>
            <a href="../tugas/tugas_guru.php" class="menu-item <?php echo $page == 'tugas_materi' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Tugas & Materi">
                <i class="fas fa-book-open"></i>
                <span>TUGAS & MATERI</span>
            </a>
            <a href="../nilai/penilaian.php" class="menu-item <?php echo $page == 'penilaian' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Penilaian">
                <i class="fas fa-clipboard-check"></i>
                <span>PENILAIAN</span>
            </a>
            <a href="../arsip/arsip_guru.php" class="menu-item <?php echo $page == 'arsip' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Arsip">
                <i class="fas fa-archive"></i>
                <span>ARSIP</span>
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
        <!-- Default dashboard content -->
        <div class="page-header">
            <h2>Selamat Datang, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
            <p>Berikut adalah ringkasan sistem pada <?php echo date('d F Y'); ?></p>
        </div>

        <div class="welcome-card">
            <h2>Selamat Datang di Sistem Sekolah</h2>
            <p>Anda login sebagai guru dengan ID: <strong><?php echo htmlspecialchars($user_id); ?></strong></p>
            <div class="teacher-info">
                <i class="fas fa-id-card"></i>
                <span>Guru Aktif</span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value">128</div>
                <div class="stat-label">Jumlah Siswa</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value">12</div>
                <div class="stat-label">Jumlah Kelas</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon purple">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value">15</div>
                <div class="stat-label">Tugas Aktif</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon orange">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value">42</div>
                <div class="stat-label">Nilai Tugas</div>
            </div>
        </div>

        <div class="class-grid">
            <div class="class-card">
                <div class="class-header">
                    <div>
                        <div class="class-name">Matematika</div>
                        <div class="class-subject">Kelas 7A</div>
                    </div>
                    <div class="class-badge">Aktif</div>
                </div>
                <div class="class-stats">
                    <div class="class-stat-item">
                        <i class="fas fa-users"></i>
                        <span>32 Siswa</span>
                    </div>
                    <div class="class-stat-item">
                        <i class="fas fa-tasks"></i>
                        <span>5 Tugas</span>
                    </div>
                </div>
                <div class="class-actions">
                    <button class="btn-class-action">Lihat</button>
                    <button class="btn-class-action">Nilai</button>
                </div>
            </div>

            <div class="class-card">
                <div class="class-header">
                    <div>
                        <div class="class-name">Fisika</div>
                        <div class="class-subject">Kelas 10B</div>
                    </div>
                    <div class="class-badge">Aktif</div>
                </div>
                <div class="class-stats">
                    <div class="class-stat-item">
                        <i class="fas fa-users"></i>
                        <span>28 Siswa</span>
                    </div>
                    <div class="class-stat-item">
                        <i class="fas fa-tasks"></i>
                        <span>3 Tugas</span>
                    </div>
                </div>
                <div class="class-actions">
                    <button class="btn-class-action">Lihat</button>
                    <button class="btn-class-action">Nilai</button>
                </div>
            </div>

            <div class="class-card">
                <div class="class-header">
                    <div>
                        <div class="class-name">Kimia</div>
                        <div class="class-subject">Kelas 11A</div>
                    </div>
                    <div class="class-badge">Aktif</div>
                </div>
                <div class="class-stats">
                    <div class="class-stat-item">
                        <i class="fas fa-users"></i>
                        <span>26 Siswa</span>
                    </div>
                    <div class="class-stat-item">
                        <i class="fas fa-tasks"></i>
                        <span>4 Tugas</span>
                    </div>
                </div>
                <div class="class-actions">
                    <button class="btn-class-action">Lihat</button>
                    <button class="btn-class-action">Nilai</button>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="section-title">
                    <h3>Tugas Terbaru</h3>
                </div>
                <div class="dashboard-card">
                    <div class="activity-list">
                        <div class="activity-card">
                            <div class="activity-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Tugas Matematika - Aljabar</div>
                                <div class="activity-desc">Kelas 7A - Deadline: 15 Nov 2025</div>
                            </div>
                            <div class="activity-time">2 jam lalu</div>
                        </div>
                        <div class="activity-card">
                            <div class="activity-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Penilaian Fisika</div>
                                <div class="activity-desc">Kelas 10B - 24 siswa dinilai</div>
                            </div>
                            <div class="activity-time">1 hari lalu</div>
                        </div>
                        <div class="activity-card">
                            <div class="activity-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Pertemuan Wali Kelas</div>
                                <div class="activity-desc">Kelas 7A - 12 Nov 2025</div>
                            </div>
                            <div class="activity-time">3 hari lalu</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="section-title">
                    <h3>Arsip Terbaru</h3>
                </div>
                <div class="dashboard-card">
                    <div class="activity-list">
                        <div class="activity-card">
                            <div class="activity-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Materi Aljabar.pdf</div>
                                <div class="activity-desc">Matematika - 7A</div>
                            </div>
                            <div class="activity-time">5 jam lalu</div>
                        </div>
                        <div class="activity-card">
                            <div class="activity-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                                <i class="fas fa-file-powerpoint"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Presentasi Fisika.pptx</div>
                                <div class="activity-desc">Fisika - 10B</div>
                            </div>
                            <div class="activity-time">2 hari lalu</div>
                        </div>
                        <div class="activity-card">
                            <div class="activity-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                                <i class="fas fa-file-word"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">Laporan Praktikum.docx</div>
                                <div class="activity-desc">Kimia - 11A</div>
                            </div>
                            <div class="activity-time">4 hari lalu</div>
                        </div>
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