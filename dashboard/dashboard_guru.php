<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah guru
checkUserType(['guru']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

$pdo = getDBConnection();

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

// Hitung statistik
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_siswa
    FROM user_siswa s
    JOIN kelas_pelajaran kp ON s.id_kelas = kp.id_kelas
    WHERE kp.id_guru = ?
");
$stmt->execute([$user_id]);
$total_siswa = $stmt->fetch()['total_siswa'];

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT k.id_kelas) as total_kelas
    FROM kelas k
    JOIN kelas_pelajaran kp ON k.id_kelas = kp.id_kelas
    WHERE kp.id_guru = ?
");
$stmt->execute([$user_id]);
$total_kelas = $stmt->fetch()['total_kelas'];

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_tugas
    FROM tugas
    WHERE id_guru = ? AND status = 'aktif'
");
$stmt->execute([$user_id]);
$total_tugas = $stmt->fetch()['total_tugas'];

// Karena tabel nilai tampaknya tidak ada, kita ganti dengan jumlah yang telah dinilai dari kolom nilai_siswa di tabel tugas
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_nilai
    FROM tugas
    WHERE id_guru = ? AND JSON_LENGTH(nilai_siswa) > 0
");
$stmt->execute([$user_id]);
$total_nilai = $stmt->fetch()['total_nilai'];

// Ambil kelas dan pelajaran yang diajar oleh guru ini
$stmt = $pdo->prepare("
    SELECT k.nama_kelas, p.nama_pelajaran, k.id_kelas
    FROM kelas_pelajaran kp
    JOIN kelas k ON kp.id_kelas = k.id_kelas
    JOIN pelajaran p ON kp.id_pelajaran = p.id_pelajaran
    WHERE kp.id_guru = ?
    ORDER BY k.nama_kelas
");
$stmt->execute([$user_id]);
$kelas_pelajaran = $stmt->fetchAll();

// Ambil tugas terbaru
$stmt = $pdo->prepare("
    SELECT t.judul_tugas, t.tanggal_dibuat, t.deadline, k.nama_kelas, p.nama_pelajaran,
           JSON_LENGTH(t.nilai_siswa) as jumlah_dinilai
    FROM tugas t
    JOIN kelas k ON t.id_kelas = k.id_kelas
    JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
    WHERE t.id_guru = ?
    ORDER BY t.tanggal_dibuat DESC
    LIMIT 3
");
$stmt->execute([$user_id]);
$tugas_terbaru = $stmt->fetchAll();

// Ambil arsip terbaru milik guru ini
$stmt = $pdo->prepare("
    SELECT judul_arsip, tanggal_upload, file_type, file_name
    FROM arsip
    WHERE id_uploader = ? AND tipe_uploader = 'guru'
    ORDER BY tanggal_upload DESC
    LIMIT 3
");
$stmt->execute([$user_id]);
$arsip_terbaru = $stmt->fetchAll();

// Hitung jumlah siswa per kelas yang diajar
$kelas_data = [];
foreach ($kelas_pelajaran as $kp) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as jumlah_siswa FROM user_siswa WHERE id_kelas = ? AND status = 'aktif'");
    $stmt->execute([$kp['id_kelas']]);
    $jumlah_siswa = $stmt->fetch()['jumlah_siswa'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as jumlah_tugas FROM tugas WHERE id_kelas = ? AND id_guru = ?");
    $stmt->execute([$kp['id_kelas'], $user_id]);
    $jumlah_tugas = $stmt->fetch()['jumlah_tugas'];

    $kelas_data[] = [
        'nama_kelas' => $kp['nama_kelas'],
        'nama_pelajaran' => $kp['nama_pelajaran'],
        'jumlah_siswa' => $jumlah_siswa,
        'jumlah_tugas' => $jumlah_tugas
    ];
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
                <div class="stat-value"><?php echo $total_siswa; ?></div>
                <div class="stat-label">Jumlah Siswa</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon green">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value"><?php echo $total_kelas; ?></div>
                <div class="stat-label">Jumlah Kelas</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-icon purple">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-value"><?php echo $total_tugas; ?></div>
                <div class="stat-label">Tugas Aktif</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon orange">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?php echo $total_nilai; ?></div>
                <div class="stat-label">Nilai Tugas</div>
            </div>
        </div>

        <div class="class-grid">
            <?php foreach ($kelas_data as $kelas): ?>
            <div class="class-card">
                <div class="class-header">
                    <div>
                        <div class="class-name"><?php echo htmlspecialchars($kelas['nama_pelajaran']); ?></div>
                        <div class="class-subject"><?php echo htmlspecialchars($kelas['nama_kelas']); ?></div>
                    </div>
                    <div class="class-badge">Aktif</div>
                </div>
                <div class="class-stats">
                    <div class="class-stat-item">
                        <i class="fas fa-users"></i>
                        <span><?php echo $kelas['jumlah_siswa']; ?> Siswa</span>
                    </div>
                    <div class="class-stat-item">
                        <i class="fas fa-tasks"></i>
                        <span><?php echo $kelas['jumlah_tugas']; ?> Tugas</span>
                    </div>
                </div>
                <div class="class-actions">
                    <a href="../tugas/tugas_guru.php" class="btn-class-action">Lihat</a>
                    <a href="../nilai/penilaian.php" class="btn-class-action">Nilai</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="section-title">
                    <h3>Tugas Terbaru</h3>
                </div>
                <div class="dashboard-card">
                    <div class="activity-list">
                        <?php if (count($tugas_terbaru) > 0): ?>
                        <?php foreach ($tugas_terbaru as $tugas): ?>
                        <div class="activity-card">
                            <div class="activity-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($tugas['judul_tugas']); ?></div>
                                <div class="activity-desc"><?php echo htmlspecialchars($tugas['nama_kelas']); ?> - <?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></div>
                                <?php if ($tugas['deadline']): ?>
                                <div class="activity-desc">Deadline: <?php echo date('d M Y', strtotime($tugas['deadline'])); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="activity-time"><?php echo time_elapsed_string($tugas['tanggal_dibuat']); ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>Belum ada tugas</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="section-title">
                    <h3>Arsip Terbaru</h3>
                </div>
                <div class="dashboard-card">
                    <div class="activity-list">
                        <?php if (count($arsip_terbaru) > 0): ?>
                        <?php foreach ($arsip_terbaru as $arsip): ?>
                        <div class="activity-card">
                            <div class="activity-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                                <?php
                                $icon_map = [
                                    'pdf' => 'fa-file-pdf',
                                    'doc' => 'fa-file-word', 'docx' => 'fa-file-word',
                                    'xls' => 'fa-file-excel', 'xlsx' => 'fa-file-excel',
                                    'ppt' => 'fa-file-powerpoint', 'pptx' => 'fa-file-powerpoint',
                                    'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image',
                                    'zip' => 'fa-file-archive', 'rar' => 'fa-file-archive'
                                ];
                                $icon = $icon_map[$arsip['file_type']] ?? 'fa-file';
                                ?>
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title"><?php echo htmlspecialchars($arsip['judul_arsip']); ?></div>
                                <div class="activity-desc"><?php echo htmlspecialchars($arsip['file_name']); ?></div>
                            </div>
                            <div class="activity-time"><?php echo time_elapsed_string($arsip['tanggal_upload']); ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>Belum ada arsip</p>
                        </div>
                        <?php endif; ?>
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

<?php
// Fungsi untuk menghitung waktu yang telah berlalu
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' lalu' : 'baru saja';
}
?>