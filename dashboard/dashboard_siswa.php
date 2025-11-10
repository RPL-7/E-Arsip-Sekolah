<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah siswa
checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_nis = $_SESSION['user_nis'];

// Ambil parameter page dari URL, default ke 'dashboard'
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Daftar halaman yang diizinkan
$allowed_pages = [
    'dashboard',
    'daftar_tugas',
    'daftar_nilai',
    'riwayat_tugas',
    'arsip_materi',
    'wali_kelas',
    'profil_saya'
];

// Validasi halaman
if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Map menu text ke file
$page_files = [
    'dashboard' => 'dashboard_siswa.php',
    'daftar_tugas' => '../tugas/tugas_siswa.php',
    'daftar_nilai' => '../nilai/nilai_siswa.php',
    'arsip_materi' => '../arsip/arsip_siswa.php',
    'profil_saya' => '../profil/profil_siswa.php'
];

// Judul halaman
$page_titles = [
    'dashboard' => 'Dashboard Siswa',
    'daftar_tugas' => 'Daftar Tugas',
    'daftar_nilai' => 'Daftar Nilai',
    'arsip_materi' => 'Arsip Materi',
    'profil_saya' => 'Profil Saya'
];

$current_title = $page_titles[$page] ?? 'Dashboard Siswa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_title; ?> - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard_siswa.css">
</head>
<body class="light-theme">
    
    <!-- Header -->
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">DASHBOARD SISWA</div>
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
                    <li><a class="dropdown-item" href="../profil/profil_siswa.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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
            <a href="dashboard_siswa.php" class="menu-item <?php echo $page == 'dashboard' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>DASHBOARD</span>
            </a>
            <a href="../tugas/tugas_siswa.php" class="menu-item <?php echo $page == 'daftar_tugas' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Daftar Tugas">
                <i class="fas fa-tasks"></i>
                <span>DAFTAR TUGAS</span>
            </a>
            <a href="../nilai/nilai_siswa.php" class="menu-item <?php echo $page == 'daftar_nilai' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Daftar Nilai">
                <i class="fas fa-chart-line"></i>
                <span>DAFTAR NILAI</span>
            </a>
            <a href="../arsip/arsip_siswa.php" class="menu-item <?php echo $page == 'arsip_materi' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Arsip Materi">
                <i class="fas fa-folder-open"></i>
                <span>ARSIP</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Profil</div>
            <a href="../profil/profil_siswa.php" class="menu-item <?php echo $page == 'profil_saya' ? 'active' : ''; ?>" data-bs-toggle="tooltip" data-bs-placement="right" title="Profil Saya">
                <i class="fas fa-id-card"></i>
                <span>PROFIL SAYA</span>
            </a>
        </div>

        <div class="menu-section">
            <a href="../logout.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Default dashboard content -->
        <div class="page-header">
            <h2>Selamat Datang, <?php echo htmlspecialchars($user_name); ?>! 👋</h2>
            <p>Berikut adalah ringkasan sistem pada <?php echo date('d F Y'); ?></p>
        </div>

        <div class="welcome-card">
            <h2>Selamat Datang di Sistem Sekolah</h2>
            <p>Anda login sebagai siswa dengan NIS: <strong><?php echo htmlspecialchars($user_nis); ?></strong></p>
            <div class="student-info">
                <i class="fas fa-user-graduate"></i>
                <span>Aktif</span>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-icon blue">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value">12</div>
                <div class="stat-label">Tugas Belum Dikumpulkan</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-value">8</div>
                <div class="stat-label">Tugas Selesai</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon purple">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value">7</div>
                <div class="stat-label">Nilai Tugas</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon orange">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="stat-value">15</div>
                <div class="stat-label">Arsip Materi</div>
            </div>
        </div>

        <div class="section-title">
            <h3>Tugas Terbaru</h3>
        </div>
        
        <div class="task-list">
            <div class="task-card urgent">
                <div class="task-header">
                    <div>
                        <div class="task-title">Tugas Matematika - Aljabar</div>
                        <div class="task-subject">Matematika - Kelas 7A</div>
                    </div>
                    <div class="task-badge badge-urgent">URGENT</div>
                </div>
                <div class="task-meta">
                    <div><i class="fas fa-calendar me-1"></i>Deadline: 15 Nov 2025</div>
                    <div><i class="fas fa-file me-1"></i>3 File</div>
                </div>
                <div class="task-description">
                    Kerjakan soal-soal aljabar dari buku halaman 45-50. Kumpulkan dalam bentuk PDF.
                </div>
                <div class="task-footer">
                    <div class="teacher-info">
                        <img src="https://ui-avatars.com/api/?name=Guru+Matematika&background=3b82f6&color=fff" alt="Teacher" class="teacher-avatar">
                        <div class="teacher-name">Pak Budi</div>
                    </div>
                    <button class="task-action">Kerjakan</button>
                </div>
            </div>
            
            <div class="task-card">
                <div class="task-header">
                    <div>
                        <div class="task-title">Laporan Praktikum Fisika</div>
                        <div class="task-subject">Fisika - Kelas 7A</div>
                    </div>
                    <div class="task-badge badge-pending">PENDING</div>
                </div>
                <div class="task-meta">
                    <div><i class="fas fa-calendar me-1"></i>Deadline: 20 Nov 2025</div>
                    <div><i class="fas fa-file me-1"></i>2 File</div>
                </div>
                <div class="task-description">
                    Buat laporan praktikum fisika tentang hukum Newton. Sertakan data pengamatan dan analisis.
                </div>
                <div class="task-footer">
                    <div class="teacher-info">
                        <img src="https://ui-avatars.com/api/?name=Guru+Fisika&background=10b981&color=fff" alt="Teacher" class="teacher-avatar">
                        <div class="teacher-name">Bu Siti</div>
                    </div>
                    <button class="task-action">Kerjakan</button>
                </div>
            </div>
            
            <div class="task-card">
                <div class="task-header">
                    <div>
                        <div class="task-title">Membaca Bab Baru</div>
                        <div class="task-subject">Bahasa Indonesia - Kelas 7A</div>
                    </div>
                    <div class="task-badge badge-pending">PENDING</div>
                </div>
                <div class="task-meta">
                    <div><i class="fas fa-calendar me-1"></i>Deadline: 25 Nov 2025</div>
                    <div><i class="fas fa-book me-1"></i>1 Tugas Baca</div>
                </div>
                <div class="task-description">
                    Baca dan buat ringkasan dari bab 5 buku pelajaran Bahasa Indonesia.
                </div>
                <div class="task-footer">
                    <div class="teacher-info">
                        <img src="https://ui-avatars.com/api/?name=Guru+Indo&background=8b5cf6&color=fff" alt="Teacher" class="teacher-avatar">
                        <div class="teacher-name">Pak Joko</div>
                    </div>
                    <button class="task-action">Baca</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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