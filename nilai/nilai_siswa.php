<?php
session_start();
require_once '../config.php';

checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Ambil data siswa
$stmt = $pdo->prepare("SELECT id_kelas, nama_siswa, nis FROM user_siswa WHERE id_siswa = ?");
$stmt->execute([$user_id]);
$siswa_data = $stmt->fetch();
$id_kelas = $siswa_data['id_kelas'];
$nama_siswa = $siswa_data['nama_siswa'];
$nis = $siswa_data['nis'];

if (!$id_kelas) {
    $error_no_class = true;
} else {
    $error_no_class = false;

    // Filter
    $search = $_GET['search'] ?? '';
    $pelajaran_filter = $_GET['pelajaran'] ?? '';

    // Ambil semua tugas dengan nilai
    $query = "
        SELECT t.*,
               p.nama_pelajaran,
               g.nama_guru
        FROM tugas t
        JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
        JOIN user_guru g ON t.id_guru = g.id_guru
        WHERE t.id_kelas = ?
    ";
    $params = [$id_kelas];

    if (!empty($search)) {
        $query .= " AND (t.judul_tugas LIKE ? OR p.nama_pelajaran LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($pelajaran_filter)) {
        $query .= " AND t.id_pelajaran = ?";
        $params[] = $pelajaran_filter;
    }

    $query .= " ORDER BY t.tanggal_dibuat DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_tugas = $stmt->fetchAll();

    $processed = [];

    foreach ($all_tugas as $raw) {

        $id = $raw['id_tugas'];

        // Jika sudah pernah diproses, skip baris duplikat
        if (isset($processed[$id])) continue;

        // Decode file dan nilai
        $nilai_siswa = json_decode($raw['nilai_siswa'] ?? '[]', true);
        $file_jawaban = json_decode($raw['file_jawaban'] ?? '[]', true);

        $raw['sudah_kumpul'] = false;
        $raw['tanggal_kumpul'] = null;
        $raw['nilai'] = null;

        // Cek file dikumpulkan
        foreach ($file_jawaban as $fw) {
            if ($fw['id_siswa'] == $user_id) {
                $raw['sudah_kumpul'] = true;
                $raw['tanggal_kumpul'] = $fw['tanggal_upload'];
                break;
            }
        }

        // Cek nilai siswa
        foreach ($nilai_siswa as $ns) {
            if ($ns['id_siswa'] == $user_id) {
                $raw['nilai'] = $ns;
                break;
            }
        }

        // Simpan hasil akhir (unique)
        $processed[$id] = $raw;
    }

    // Final array yang sudah bersih
    $all_tugas = array_values($processed);

    // Ambil pelajaran untuk filter
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id_pelajaran, p.nama_pelajaran
        FROM tugas t
        JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
        WHERE t.id_kelas = ?
        ORDER BY p.nama_pelajaran
    ");
    $stmt->execute([$id_kelas]);
    $pelajaran_list = $stmt->fetchAll();

    // Statistik
    $total_tugas = count($all_tugas);
    $total_dikumpulkan = 0;
    $total_dinilai = 0;
    $total_nilai = 0;

    foreach ($all_tugas as $t) {
        if ($t['sudah_kumpul']) $total_dikumpulkan++;
        if ($t['nilai']) {
            $total_dinilai++;
            $total_nilai += $t['nilai']['nilai'];
        }
    }

    $rata_rata = $total_dinilai > 0 ? $total_nilai / $total_dinilai : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Nilai - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/nilai_siswa.css">
</head>
<body class="light-theme">

    <!-- Header -->
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">DAFTAR NILAI</div>
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
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($nama_siswa); ?>&background=10b981&color=fff" alt="Profile" class="profile-img">
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="../profil/profil_saya.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
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
            <a href="../nilai/nilai_siswa.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Daftar Nilai">
                <i class="fas fa-chart-line"></i>
                <span>DAFTAR NILAI</span>
            </a>
            <a href="../arsip/arsip_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Arsip Materi">
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
            <a href="../../logout.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <?php if ($error_no_class): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Perhatian!</strong><br>
            Anda belum terdaftar di kelas manapun. Silakan hubungi admin.
        </div>
        <?php else: ?>

        <!-- Page Header -->
        <div class="mb-4">
            <h2 class="mb-1">📊 Daftar Nilai</h2>
            <p class="text-secondary">Lihat perkembangan akademik Anda</p>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card blue">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Total Tugas</div>
                            <div class="stat-value"><?php echo $total_tugas; ?></div>
                            <small class="text-muted">Semua tugas yang diberikan</small>
                        </div>
                        <div class="stat-icon blue">
                            <i class="fas fa-tasks"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card green">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Sudah Dinilai</div>
                            <div class="stat-value"><?php echo $total_dinilai; ?></div>
                            <small class="text-muted">Tugas yang sudah dinilai</small>
                        </div>
                        <div class="stat-icon green">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card purple">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Rata-rata Nilai</div>
                            <div class="stat-value"><?php echo number_format($rata_rata, 2); ?></div>
                            <?php if ($total_dinilai > 0): ?>
                            <small class="text-success"><i class="fas fa-arrow-up"></i> Nilai bagus</small>
                            <?php else: ?>
                            <small class="text-muted">Belum ada nilai</small>
                            <?php endif; ?>
                        </div>
                        <div class="stat-icon purple">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card orange">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="stat-label">Dikumpulkan</div>
                            <div class="stat-value"><?php echo $total_dikumpulkan; ?></div>
                            <small class="text-muted">Tugas yang sudah dikumpulkan</small>
                        </div>
                        <div class="stat-icon orange">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="dashboard-card mb-4">
            <form method="GET" class="filter-bar row g-3 align-items-center">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Cari judul tugas..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="pelajaran" class="form-select">
                        <option value="">Semua Pelajaran</option>
                        <?php foreach ($pelajaran_list as $pel): ?>
                        <option value="<?php echo $pel['id_pelajaran']; ?>" <?php echo $pelajaran_filter == $pel['id_pelajaran'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pel['nama_pelajaran']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">🔍 Cari</button>
                </div>
                <div class="col-md-1">
                    <?php if (!empty($search) || !empty($pelajaran_filter)): ?>
                    <a href="nilai_siswa.php" class="btn btn-outline-secondary w-100">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Daftar Nilai Tugas -->
        <div class="dashboard-card mb-4">
            <h5 class="card-title">Daftar Nilai Tugas</h5>
            <?php if (count($all_tugas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Judul Tugas</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th>Tanggal Tugas</th>
                            <th>Status</th>
                            <th>Nilai</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_tugas as $idx => $tugas): ?>
                        <?php
                        $status_badge = 'badge-pending';
                        $status_text = 'Belum Dikumpulkan';

                        if ($tugas['nilai']) {
                            $status_badge = 'badge-success';
                            $status_text = 'Sudah Dinilai';
                        } elseif ($tugas['sudah_kumpul']) {
                            $status_badge = 'badge-warning';
                            $status_text = 'Menunggu Nilai';
                        }

                        $nilai_class = '';
                        if ($tugas['nilai']) {
                            $nilai = $tugas['nilai']['nilai'];
                            if ($nilai >= 80) $nilai_class = 'nilai-excellent';
                            elseif ($nilai >= 70) $nilai_class = 'nilai-good';
                            else $nilai_class = 'nilai-poor';
                        }
                        ?>
                        <tr>
                            <td><?php echo $idx + 1; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($tugas['judul_tugas']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></td>
                            <td><?php echo htmlspecialchars($tugas['nama_guru']); ?></td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($tugas['tanggal_dibuat'])); ?>
                                <?php if ($tugas['deadline']): ?>
                                <br><small class="text-muted">Deadline: <?php echo date('d/m/Y', strtotime($tugas['deadline'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                                <?php if ($tugas['sudah_kumpul']): ?>
                                <br><small class="text-muted">
                                    Dikumpulkan: <?php echo date('d/m/Y H:i', strtotime($tugas['tanggal_kumpul'])); ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tugas['nilai']): ?>
                                    <div class="nilai-display <?php echo $nilai_class; ?>">
                                        <?php echo number_format($tugas['nilai']['nilai'], 2); ?>
                                    </div>
                                    <small class="text-muted">
                                        Dinilai: <?php echo date('d/m/Y', strtotime($tugas['nilai']['tanggal_nilai'])); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tugas['nilai'] && !empty($tugas['nilai']['catatan'])): ?>
                                    <button class="btn btn-info btn-sm" onclick="showCatatan('<?php echo htmlspecialchars($tugas['judul_tugas']); ?>', '<?php echo htmlspecialchars($tugas['nilai']['catatan']); ?>')">
                                        <i class="fas fa-comment"></i> Lihat
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div style="font-size: 64px; margin-bottom: 20px;">📊</div>
                <h3 class="text-muted">Belum Ada Tugas</h3>
                <p class="text-muted">Belum ada tugas dari guru untuk kelas Anda</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Rekap Per Mata Pelajaran -->
        <?php if ($total_dinilai > 0): ?>
        <div class="dashboard-card">
            <h5 class="card-title">Rekap Nilai Per Mata Pelajaran</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mata Pelajaran</th>
                            <th>Jumlah Tugas Dinilai</th>
                            <th>Rata-rata Nilai</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rekap_pelajaran = [];
                        foreach ($all_tugas as $t) {
                            if ($t['nilai']) {
                                $pelajaran = $t['nama_pelajaran'];
                                if (!isset($rekap_pelajaran[$pelajaran])) {
                                    $rekap_pelajaran[$pelajaran] = [
                                        'total' => 0,
                                        'sum' => 0
                                    ];
                                }
                                $rekap_pelajaran[$pelajaran]['total']++;
                                $rekap_pelajaran[$pelajaran]['sum'] += $t['nilai']['nilai'];
                            }
                        }

                        foreach ($rekap_pelajaran as $pelajaran => $data):
                            $avg = $data['sum'] / $data['total'];
                            $avg_class = '';
                            if ($avg >= 80) $avg_class = 'nilai-excellent';
                            elseif ($avg >= 70) $avg_class = 'nilai-good';
                            else $avg_class = 'nilai-poor';

                            // Menentukan grade berdasarkan nilai rata-rata
                            if ($avg >= 85) $grade = 'A';
                            elseif ($avg >= 75) $grade = 'B';
                            elseif ($avg >= 65) $grade = 'C';
                            elseif ($avg >= 55) $grade = 'D';
                            else $grade = 'E';
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($pelajaran); ?></strong></td>
                            <td><?php echo $data['total']; ?> tugas</td>
                            <td>
                                <span class="nilai-display <?php echo $avg_class; ?>">
                                    <?php echo number_format($avg, 2); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo $grade; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <!-- Modal Catatan -->
    <div class="modal fade" id="catatanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title">💬 Catatan dari Guru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h4 id="catatan_judul" class="mb-3"></h4>
                    <div id="catatan_isi" class="p-3 bg-light rounded"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showCatatan(judul, catatan) {
            document.getElementById('catatan_judul').textContent = judul;
            document.getElementById('catatan_isi').textContent = catatan;
            var myModal = new bootstrap.Modal(document.getElementById('catatanModal'));
            myModal.show();
        }

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
    </script>
</body>
</html>