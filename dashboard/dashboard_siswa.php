<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah siswa
checkUserType(['siswa']);

// Koneksi database
$pdo = getDBConnection();

// Ambil ID siswa dari session
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Ambil data siswa dari database
$stmt = $pdo->prepare("SELECT nis, nama_siswa, id_kelas FROM user_siswa WHERE id_siswa = ?");
$stmt->execute([$user_id]);
$siswa_data = $stmt->fetch();

if (!$siswa_data) {
    // Jika data siswa tidak ditemukan, logout
    header("Location: ../login.php");
    exit();
}

$user_nis = $siswa_data['nis'];
$user_nama = $siswa_data['nama_siswa'];
$id_kelas = $siswa_data['id_kelas'];

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

// Fungsi untuk mengecek apakah tugas sudah dikumpulkan
function isTugasDikumpulkan($tugas, $user_id) {
    $file_jawaban = json_decode($tugas['file_jawaban'] ?? '[]', true);
    if (!is_array($file_jawaban)) return false;

    foreach ($file_jawaban as $jawaban) {
        if ($jawaban['id_siswa'] == $user_id) {
            return true;
        }
    }
    return false;
}

// Fungsi untuk mengecek apakah tugas sudah dinilai
function isTugasDinilai($tugas, $user_id) {
    $nilai_siswa = json_decode($tugas['nilai_siswa'] ?? '[]', true);
    if (!is_array($nilai_siswa)) return false;

    foreach ($nilai_siswa as $nilai) {
        if ($nilai['id_siswa'] == $user_id) {
            return true;
        }
    }
    return false;
}

// Ambil statistik tugas berdasarkan kelas siswa
$stats_tugas = [
    'total_tugas' => 0,
    'tugas_belum_dikumpulkan' => 0,
    'tugas_dikumpulkan' => 0
];

if ($id_kelas) {
    // Ambil semua tugas untuk kelas ini
    $stmt = $pdo->prepare("SELECT * FROM tugas WHERE id_kelas = ?");
    $stmt->execute([$id_kelas]);
    $all_tugas = $stmt->fetchAll();

    $stats_tugas['total_tugas'] = count($all_tugas);

    foreach ($all_tugas as $tugas) {
        $sudah_dikumpulkan = isTugasDikumpulkan($tugas, $user_id);
        $deadline = new DateTime($tugas['deadline']);
        $now = new DateTime();

        if ($deadline < $now && !$sudah_dikumpulkan) {
            $stats_tugas['tugas_belum_dikumpulkan']++;
        }

        if ($sudah_dikumpulkan) {
            $stats_tugas['tugas_dikumpulkan']++;
        }
    }
}

// Hitung jumlah nilai tugas
$jumlah_nilai = 0;
if ($id_kelas) {
    $stmt = $pdo->prepare("SELECT * FROM tugas WHERE id_kelas = ?");
    $stmt->execute([$id_kelas]);
    $all_tugas = $stmt->fetchAll();

    foreach ($all_tugas as $tugas) {
        $nilai_siswa = json_decode($tugas['nilai_siswa'] ?? '[]', true);
        if (!is_array($nilai_siswa)) continue;

        foreach ($nilai_siswa as $nilai) {
            if ($nilai['id_siswa'] == $user_id) {
                $jumlah_nilai++;
                break;
            }
        }
    }
}

// Hitung jumlah arsip milik siswa
$stmt = $pdo->prepare("SELECT COUNT(*) as jumlah_arsip FROM arsip WHERE id_uploader = ? AND tipe_uploader = 'siswa'");
$stmt->execute([$user_id]);
$jumlah_arsip = $stmt->fetch()['jumlah_arsip'] ?? 0;

// Ambil tugas terbaru
$tugas_terbaru = [];
if ($id_kelas) {
    $stmt = $pdo->prepare("
        SELECT t.*, p.nama_pelajaran, g.nama_guru
        FROM tugas t
        JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
        JOIN user_guru g ON t.id_guru = g.id_guru
        WHERE t.id_kelas = ?
        ORDER BY t.tanggal_dibuat DESC
        LIMIT 3
    ");
    $stmt->execute([$id_kelas]);
    $tugas_terbaru = $stmt->fetchAll();
}
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
                <div class="stat-value"><?php echo $stats_tugas['tugas_belum_dikumpulkan']; ?></div>
                <div class="stat-label">Tugas Belum Dikumpulkan</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stat-value"><?php echo $stats_tugas['tugas_dikumpulkan']; ?></div>
                <div class="stat-label">Tugas Selesai</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon purple">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?php echo $jumlah_nilai; ?></div>
                <div class="stat-label">Nilai Tugas</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon orange">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="stat-value"><?php echo $jumlah_arsip; ?></div>
                <div class="stat-label">Arsip Materi</div>
            </div>
        </div>

        <div class="section-title">
            <h3>Tugas Terbaru</h3>
        </div>

        <div class="task-list">
            <?php if (count($tugas_terbaru) > 0): ?>
                <?php foreach ($tugas_terbaru as $tugas): ?>
                <div class="task-card <?php echo (new DateTime($tugas['deadline']) < new DateTime() && !isTugasDikumpulkan($tugas, $user_id)) ? 'urgent' : ''; ?>">
                    <div class="task-header">
                        <div>
                            <div class="task-title"><?php echo htmlspecialchars($tugas['judul_tugas']); ?></div>
                            <div class="task-subject"><?php echo htmlspecialchars($tugas['nama_pelajaran']); ?> - Kelas <?php echo $siswa_data['id_kelas']; ?></div>
                        </div>
                        <div class="task-badge <?php echo (new DateTime($tugas['deadline']) < new DateTime() && !isTugasDikumpulkan($tugas, $user_id)) ? 'badge-urgent' : 'badge-pending'; ?>">
                            <?php echo (new DateTime($tugas['deadline']) < new DateTime() && !isTugasDikumpulkan($tugas, $user_id)) ? 'URGENT' : 'PENDING'; ?>
                        </div>
                    </div>
                    <div class="task-meta">
                        <div><i class="fas fa-calendar me-1"></i>Deadline: <?php echo date('d M Y', strtotime($tugas['deadline'])); ?></div>
                        <div><i class="fas fa-file me-1"></i>1 Tugas</div>
                    </div>
                    <div class="task-description">
                        <?php echo htmlspecialchars($tugas['deskripsi'] ?? 'Deskripsi tugas tidak tersedia'); ?>
                    </div>
                    <div class="task-footer">
                        <div class="teacher-info">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($tugas['nama_guru']); ?>&background=3b82f6&color=fff" alt="Teacher" class="teacher-avatar">
                            <div class="teacher-name"><?php echo htmlspecialchars($tugas['nama_guru']); ?></div>
                        </div>
                        <a href="../tugas/tugas_siswa.php" class="task-action">Kerjakan</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="task-card">
                    <div class="task-header">
                        <div>
                            <div class="task-title">Belum Ada Tugas</div>
                            <div class="task-subject">Tidak ada tugas terbaru untuk saat ini</div>
                        </div>
                    </div>
                    <div class="task-description">
                        Guru belum memberikan tugas baru. Silakan periksa kembali nanti atau hubungi guru mata pelajaran.
                    </div>
                </div>
            <?php endif; ?>
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