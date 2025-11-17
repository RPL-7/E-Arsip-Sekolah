<?php
session_start();
require_once '../config.php';

checkUserType(['guru']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Ambil data guru
$stmt = $pdo->prepare("SELECT nama_guru FROM user_guru WHERE id_guru = ?");
$stmt->execute([$user_id]);
$nama_guru = $stmt->fetch()['nama_guru'];

// Filter
$search = $_GET['search'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';
$pelajaran_filter = $_GET['pelajaran'] ?? '';

// Ambil semua tugas dengan statistik nilai
$query = "
    SELECT t.*,
           p.nama_pelajaran,
           k.nama_kelas, k.tahun_ajaran,
           (SELECT COUNT(*) FROM user_siswa WHERE id_kelas = t.id_kelas AND status = 'aktif') as total_siswa,
           JSON_LENGTH(t.file_jawaban) as jumlah_dikumpulkan,
           JSON_LENGTH(t.nilai_siswa) as jumlah_dinilai
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

if (!empty($kelas_filter)) {
    $query .= " AND t.id_kelas = ?";
    $params[] = $kelas_filter;
}

if (!empty($pelajaran_filter)) {
    $query .= " AND t.id_pelajaran = ?";
    $params[] = $pelajaran_filter;
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

// Group by kelas dan pelajaran
$kelas_list = [];
$pelajaran_list = [];
foreach ($kelas_pelajaran as $kp) {
    $kelas_list[$kp['id_kelas']] = $kp['nama_kelas'] . ' - ' . $kp['tahun_ajaran'];
    $pelajaran_list[$kp['id_pelajaran']] = $kp['nama_pelajaran'];
}
$kelas_list = array_unique($kelas_list);
$pelajaran_list = array_unique($pelajaran_list);

// Statistik
$total_tugas = count($all_tugas);
$total_dinilai = 0;
$total_belum_dinilai = 0;

foreach ($all_tugas as $t) {
    $dikumpulkan = $t['jumlah_dikumpulkan'] ?? 0;
    $dinilai = $t['jumlah_dinilai'] ?? 0;
    $total_dinilai += $dinilai;
    $total_belum_dinilai += ($dikumpulkan - $dinilai);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/penilaian.css">
</head>
<body class="light-theme">

    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">NILAI GURU</div>
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
                    <li><a class="dropdown-item" href="../login.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
            <a href="#" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Penilaian">
                <i class="fas fa-clipboard-check"></i>
                <span>PENILAIAN</span>
            </a>
            <a href="../arsip/arsip_guru.php" class="menu-item " data-bs-toggle="tooltip" data-bs-placement="right" title="Arsip">
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
            <h2 class="mb-1">📝 Penilaian</h2>
            <p class="text-secondary">Rekap nilai tugas dan penilaian siswa</p>
        </div>

        <!-- Filter Section -->
        <div class="dashboard-card mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Cari Tugas</label>
                    <input type="text" name="search" class="form-control" placeholder="Cari judul tugas..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pilih Kelas</label>
                    <select name="kelas" class="form-select">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelas_list as $id => $nama): ?>
                        <option value="<?php echo $id; ?>" <?php echo $kelas_filter == $id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nama); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mata Pelajaran</label>
                    <select name="pelajaran" class="form-select">
                        <option value="">Semua Pelajaran</option>
                        <?php foreach ($pelajaran_list as $id => $nama): ?>
                        <option value="<?php echo $id; ?>" <?php echo $pelajaran_filter == $id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nama); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Cari
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card blue">
                    <div class="stat-icon blue">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_tugas; ?></div>
                    <div class="stat-label">Total Tugas</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_dinilai; ?></div>
                    <div class="stat-label">Sudah Dinilai</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_belum_dinilai; ?></div>
                    <div class="stat-label">Belum Dinilai</div>
                </div>
            </div>
        </div>

        <!-- Tabel Rekap -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Daftar Tugas & Statistik Penilaian</h5>
            </div>

            <?php if (count($all_tugas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Judul Tugas</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Total Siswa</th>
                            <th>Dikumpulkan</th>
                            <th>Dinilai</th>
                            <th>Progress</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_tugas as $idx => $tugas): ?>
                        <?php
                        $total_siswa = $tugas['total_siswa'];
                        $dikumpulkan = $tugas['jumlah_dikumpulkan'] ?? 0;
                        $dinilai = $tugas['jumlah_dinilai'] ?? 0;
                        $progress = $dikumpulkan > 0 ? ($dinilai / $dikumpulkan * 100) : 0;

                        $status_badge = 'bg-success';
                        $status_text = 'Selesai';
                        if ($dikumpulkan == 0) {
                            $status_badge = 'bg-danger';
                            $status_text = 'Belum Ada';
                        } elseif ($dinilai < $dikumpulkan) {
                            $status_badge = 'bg-warning text-dark';
                            $status_text = 'Proses';
                        }
                        ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($tugas['judul_tugas']); ?></strong><br>
                                <small class="text-muted">
                                    <?php echo date('d M Y', strtotime($tugas['tanggal_dibuat'])); ?>
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></td>
                            <td><?php echo htmlspecialchars($tugas['nama_kelas']); ?></td>
                            <td><strong><?php echo $total_siswa; ?></strong></td>
                            <td>
                                <span class="badge bg-info"><?php echo $dikumpulkan; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $status_badge; ?>"><?php echo $dinilai; ?></span>
                            </td>
                            <td style="min-width: 120px;">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%;"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo number_format($progress, 0); ?>% dinilai
                                </small>
                            </td>
                            <td>
                                <a href="../tugas/nilai_tugas.php?id=<?php echo $tugas['id_tugas']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye me-1"></i>Detail
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div style="font-size: 64px; margin-bottom: 20px;">📊</div>
                <h3 class="mb-3">Belum Ada Tugas</h3>
                <p class="text-muted">Buat tugas terlebih dahulu untuk melihat rekap penilaian</p>
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
    </script>
</body>
</html>