<?php
session_start();
require_once '../config.php';

checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

$success_message = '';
$error_message = '';

// PROSES TAMBAH KELAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $nama_kelas = trim($_POST['nama_kelas']);
        $tahun_ajaran = trim($_POST['tahun_ajaran']);
        $id_guru_wali = $_POST['id_guru_wali'] ?? null;

        // Validasi
        if (empty($nama_kelas) || empty($tahun_ajaran)) {
            throw new Exception("Nama kelas dan tahun ajaran wajib diisi!");
        }

        // Cek apakah nama kelas dan tahun ajaran sudah digunakan
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE nama_kelas = ? AND tahun_ajaran = ?");
        $stmt->execute([$nama_kelas, $tahun_ajaran]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Nama kelas dan tahun ajaran sudah digunakan!");
        }

        // Insert kelas
        $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, tahun_ajaran, id_guru_wali) VALUES (?, ?, ?)");
        $stmt->execute([$nama_kelas, $tahun_ajaran, $id_guru_wali]);

        $success_message = "Kelas berhasil ditambahkan!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// PROSES UPDATE KELAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id_kelas = $_POST['id_kelas'];
        $nama_kelas = trim($_POST['nama_kelas']);
        $tahun_ajaran = trim($_POST['tahun_ajaran']);
        $id_guru_wali = $_POST['id_guru_wali'] ?? null;

        // Validasi
        if (empty($nama_kelas) || empty($tahun_ajaran)) {
            throw new Exception("Nama kelas dan tahun ajaran wajib diisi!");
        }

        // Cek apakah nama kelas dan tahun ajaran sudah digunakan (kecuali milik sendiri)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE nama_kelas = ? AND tahun_ajaran = ? AND id_kelas != ?");
        $stmt->execute([$nama_kelas, $tahun_ajaran, $id_kelas]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Nama kelas dan tahun ajaran sudah digunakan oleh kelas lain!");
        }

        // Update kelas
        $stmt = $pdo->prepare("UPDATE kelas SET nama_kelas = ?, tahun_ajaran = ?, id_guru_wali = ? WHERE id_kelas = ?");
        $stmt->execute([$nama_kelas, $tahun_ajaran, $id_guru_wali, $id_kelas]);

        $success_message = "Data kelas berhasil diupdate!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// PROSES HAPUS KELAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $id_kelas = $_POST['id_kelas'];

        // Cek apakah ada siswa di kelas ini
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_siswa WHERE id_kelas = ?");
        $stmt->execute([$id_kelas]);
        $jumlah_siswa = $stmt->fetchColumn();

        if ($jumlah_siswa > 0) {
            throw new Exception("Tidak dapat menghapus kelas! Masih ada " . $jumlah_siswa . " siswa di kelas ini. Pindahkan atau hapus siswa terlebih dahulu.");
        }

        // Hapus kelas
        $stmt = $pdo->prepare("DELETE FROM kelas WHERE id_kelas = ?");
        $stmt->execute([$id_kelas]);

        $success_message = "Kelas berhasil dihapus!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Filter
$search = $_GET['search'] ?? '';
$tahun_filter = $_GET['tahun'] ?? '';

// Query kelas
$query = "
    SELECT k.*,
           g.nama_guru as nama_wali_kelas
    FROM kelas k
    LEFT JOIN user_guru g ON k.id_guru_wali = g.id_guru
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (k.nama_kelas LIKE ? OR g.nama_guru LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($tahun_filter)) {
    $query .= " AND k.tahun_ajaran = ?";
    $params[] = $tahun_filter;
}

$query .= " ORDER BY k.tahun_ajaran DESC, k.nama_kelas ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_kelas = $stmt->fetchAll();

// Statistik
$total_kelas = count($all_kelas);

// Hitung kelas dengan wali dan tanpa wali
$kelas_dengan_wali = 0;
$kelas_tanpa_wali = 0;
foreach ($all_kelas as $kelas) {
    if ($kelas['id_guru_wali']) {
        $kelas_dengan_wali++;
    } else {
        $kelas_tanpa_wali++;
    }
}

// Ambil daftar guru aktif
$stmt = $pdo->prepare("SELECT id_guru, nama_guru FROM user_guru WHERE status = 'aktif' ORDER BY nama_guru ASC");
$stmt->execute();
$all_guru = $stmt->fetchAll();

// Ambil daftar tahun ajaran
$stmt = $pdo->prepare("SELECT DISTINCT tahun_ajaran FROM kelas ORDER BY tahun_ajaran DESC");
$stmt->execute();
$all_tahun_ajaran = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kelas - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/manage_kelas.css">
</head>
<body class="light-theme">
    
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">MANAJEMEN KELAS</div>
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
            <a href="../dashboard/dashboard_admin.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
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
            <a href="../manage/manage_kelas.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Kelas">
                <i class="fas fa-school"></i>
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
            <a href="../logout.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="mb-4">
            <h2 class="mb-1">🏫 Manajemen Kelas</h2>
            <p class="text-secondary">Kelola kelas dan wali kelas di sistem</p>
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
                        <i class="fas fa-school"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_kelas; ?></div>
                    <div class="stat-label">Total Kelas</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-value"><?php echo $kelas_dengan_wali; ?></div>
                    <div class="stat-label">Kelas dengan Wali</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value"><?php echo $kelas_tanpa_wali; ?></div>
                    <div class="stat-label">Kelas tanpa Wali</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="dashboard-card mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama kelas atau wali kelas..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="tahun" class="form-select">
                            <option value="">Semua Tahun Ajaran</option>
                            <?php foreach ($all_tahun_ajaran as $tahun): ?>
                            <option value="<?php echo htmlspecialchars($tahun['tahun_ajaran']); ?>" <?php echo $tahun_filter === $tahun['tahun_ajaran'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tahun['tahun_ajaran']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <?php if (!empty($search) || !empty($tahun_filter)): ?>
                        <a href="manage_kelas.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-2"></i>Tambah Kelas Baru
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabel Kelas -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Daftar Kelas (<?php echo $total_kelas; ?>)</h5>
            </div>

            <?php if (count($all_kelas) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kelas</th>
                            <th>Tahun Ajaran</th>
                            <th>Wali Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_kelas as $idx => $kelas): ?>
                        <tr>
                            <td><strong><?php echo ($idx + 1); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($kelas['nama_kelas']); ?></strong></td>
                            <td><?php echo htmlspecialchars($kelas['tahun_ajaran']); ?></td>
                            <td>
                                <?php if ($kelas['nama_wali_kelas']): ?>
                                <span class="badge bg-success"><?php echo htmlspecialchars($kelas['nama_wali_kelas']); ?></span>
                                <?php else: ?>
                                <span class="badge bg-warning text-dark">Belum Ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm me-1" onclick='editKelas(<?php echo json_encode($kelas); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick='deleteKelas(<?php echo $kelas['id_kelas']; ?>, "<?php echo addslashes(htmlspecialchars($kelas['nama_kelas'])); ?>")'>
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
                <div class="d-flex align-items-center justify-content-center">
                    <i class="fas fa-school fa-3x text-muted me-3"></i>
                    <div>
                        <h3 class="mb-2">Tidak Ada Kelas</h3>
                        <p class="text-muted">Belum ada kelas di sistem</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Tambah Kelas -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kelas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Kelas</label>
                            <input type="text" name="nama_kelas" class="form-control" required placeholder="Contoh: XII IPA 1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tahun Ajaran</label>
                            <input type="text" name="tahun_ajaran" class="form-control" required placeholder="Contoh: 2024/2025">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Wali Kelas (Opsional)</label>
                            <select name="id_guru_wali" class="form-select">
                                <option value="">Tidak Ada Wali Kelas</option>
                                <?php foreach ($all_guru as $guru): ?>
                                <option value="<?php echo $guru['id_guru']; ?>">
                                    <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit Kelas -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_kelas" id="edit_id_kelas">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Kelas</label>
                            <input type="text" name="nama_kelas" id="edit_nama_kelas" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tahun Ajaran</label>
                            <input type="text" name="tahun_ajaran" id="edit_tahun_ajaran" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Wali Kelas</label>
                            <select name="id_guru_wali" id="edit_id_guru_wali" class="form-select">
                                <option value="">Tidak Ada Wali Kelas</option>
                                <?php foreach ($all_guru as $guru): ?>
                                <option value="<?php echo $guru['id_guru']; ?>">
                                    <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Hapus Kelas -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_kelas" id="delete_id_kelas">
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus kelas <strong id="delete_kelas_name"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
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

        function editKelas(kelas) {
            document.getElementById('edit_id_kelas').value = kelas.id_kelas;
            document.getElementById('edit_nama_kelas').value = kelas.nama_kelas;
            document.getElementById('edit_tahun_ajaran').value = kelas.tahun_ajaran;
            document.getElementById('edit_id_guru_wali').value = kelas.id_guru_wali || '';
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

        function deleteKelas(id, nama) {
            document.getElementById('delete_id_kelas').value = id;
            document.getElementById('delete_kelas_name').textContent = nama;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        // Auto close alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>