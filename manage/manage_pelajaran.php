<?php
session_start();
require_once '../config.php';

checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

$success_message = '';
$error_message = '';

// PROSES TAMBAH PELAJARAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $nama_pelajaran = trim($_POST['nama_pelajaran']);
        $kelas_guru = $_POST['kelas_guru'] ?? [];

        // Validasi
        if (empty($nama_pelajaran)) {
            throw new Exception("Nama pelajaran wajib diisi!");
        }

        // Cek nama pelajaran sudah ada
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pelajaran WHERE nama_pelajaran = ?");
        $stmt->execute([$nama_pelajaran]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Nama pelajaran sudah digunakan!");
        }

        // Mulai transaksi
        $pdo->beginTransaction();

        // Insert pelajaran
        $stmt = $pdo->prepare("INSERT INTO pelajaran (nama_pelajaran) VALUES (?)");
        $stmt->execute([$nama_pelajaran]);
        $id_pelajaran_baru = $pdo->lastInsertId();

        // Insert ke kelas_pelajaran jika ada kelas yang dipilih
        if (!empty($kelas_guru)) {
            $stmt_kelas_pelajaran = $pdo->prepare("INSERT INTO kelas_pelajaran (id_kelas, id_pelajaran, id_guru) VALUES (?, ?, ?)");
            foreach ($kelas_guru as $id_kelas => $data) {
                if (isset($data['aktif']) && !empty($data['id_guru'])) {
                    $stmt_kelas_pelajaran->execute([$id_kelas, $id_pelajaran_baru, $data['id_guru']]);
                }
            }
        }

        $pdo->commit();
        $success_message = "Mata pelajaran berhasil ditambahkan!";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        $error_message = $e->getMessage();
    }
}

// PROSES UPDATE PELAJARAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id_pelajaran = $_POST['id_pelajaran'];
        $nama_pelajaran = trim($_POST['nama_pelajaran']);

        // Validasi
        if (empty($nama_pelajaran)) {
            throw new Exception("Nama pelajaran wajib diisi!");
        }

        // Cek nama pelajaran sudah ada (kecuali milik sendiri)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pelajaran WHERE nama_pelajaran = ? AND id_pelajaran != ?");
        $stmt->execute([$nama_pelajaran, $id_pelajaran]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Nama pelajaran sudah digunakan!");
        }

        // Update pelajaran
        $stmt = $pdo->prepare("UPDATE pelajaran SET nama_pelajaran = ? WHERE id_pelajaran = ?");
        $stmt->execute([$nama_pelajaran, $id_pelajaran]);

        $success_message = "Data pelajaran berhasil diupdate!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// PROSES HAPUS PELAJARAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $id_pelajaran = $_POST['id_pelajaran'];

        // Cek apakah pelajaran digunakan di kelas_pelajaran
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas_pelajaran WHERE id_pelajaran = ?");
        $stmt->execute([$id_pelajaran]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Mata pelajaran tidak dapat dihapus karena sedang digunakan di kelas!");
        }

        // Hapus pelajaran
        $stmt = $pdo->prepare("DELETE FROM pelajaran WHERE id_pelajaran = ?");
        $stmt->execute([$id_pelajaran]);

        $success_message = "Mata pelajaran berhasil dihapus!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Filter
$search = $_GET['search'] ?? '';

// Query pelajaran
$query = "SELECT * FROM pelajaran WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND nama_pelajaran LIKE ?";
    $search_term = "%$search%";
    $params[] = $search_term;
}

$query .= " ORDER BY nama_pelajaran ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_pelajaran = $stmt->fetchAll();

// Statistik
$total_pelajaran = count($all_pelajaran);

// Ambil jumlah kelas per pelajaran
$jumlah_kelas_per_pelajaran = [];
foreach ($all_pelajaran as $pelajaran) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as jumlah FROM kelas_pelajaran WHERE id_pelajaran = ?");
    $stmt->execute([$pelajaran['id_pelajaran']]);
    $jumlah_kelas_per_pelajaran[$pelajaran['id_pelajaran']] = $stmt->fetch()['jumlah'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pelajaran - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/manage_pelajaran.css">
</head>
<body class="light-theme">
    
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">MANAJEMEN PELAJARAN</div>
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
            <a href="../manage/manage_kelas.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Kelas">
                <i class="fas fa-school"></i>
                <span>MANAJEMEN KELAS</span>
            </a>
            <a href="../manage/manage_pelajaran.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Pelajaran">
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
            <h2 class="mb-1">📚 Manajemen Pelajaran</h2>
            <p class="text-secondary">Kelola mata pelajaran yang diajarkan di sekolah</p>
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
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_pelajaran; ?></div>
                    <div class="stat-label">Total Pelajaran</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value">Aktif</div>
                    <div class="stat-label">Status</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-value"><?php echo array_sum($jumlah_kelas_per_pelajaran); ?></div>
                    <div class="stat-label">Kelas Terhubung</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="dashboard-card mb-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama pelajaran..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <?php if (!empty($search)): ?>
                        <a href="manage_pelajaran.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-2"></i>Tambah Pelajaran Baru
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabel Pelajaran -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Daftar Mata Pelajaran (<?php echo $total_pelajaran; ?>)</h5>
            </div>

            <?php if (count($all_pelajaran) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Pelajaran</th>
                            <th>Kelas Terhubung</th>
                            <th>Tanggal Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_pelajaran as $idx => $pelajaran): ?>
                        <tr>
                            <td><strong><?php echo ($idx + 1); ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($pelajaran['nama_pelajaran']); ?></strong></td>
                            <td>
                                <?php
                                $jumlah_kelas = $jumlah_kelas_per_pelajaran[$pelajaran['id_pelajaran']] ?? 0;
                                $badge_class = 'bg-success';
                                if ($jumlah_kelas == 0) $badge_class = 'bg-warning text-dark';
                                elseif ($jumlah_kelas >= 5) $badge_class = 'bg-info';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo $jumlah_kelas; ?> Kelas
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($pelajaran['created_at'] ?? $pelajaran['tanggal_dibuat'] ?? date('Y-m-d'))); ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm me-1" onclick='editPelajaran(<?php echo json_encode($pelajaran); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick='deletePelajaran(<?php echo $pelajaran['id_pelajaran']; ?>, "<?php echo addslashes(htmlspecialchars($pelajaran['nama_pelajaran'])); ?>")'>
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
                    <i class="fas fa-book fa-3x text-muted me-3"></i>
                    <div>
                        <h3 class="mb-2">Tidak Ada Pelajaran</h3>
                        <p class="text-muted">Belum ada pelajaran di sistem</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Tambah Pelajaran -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pelajaran Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Pelajaran</label>
                            <input type="text" name="nama_pelajaran" class="form-control" required placeholder="Contoh: Matematika, Bahasa Indonesia">
                        </div>

                        <div class="mb-4">
                            <div class="alert alert-info">
                                <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Informasi Penting</h6>
                                <ul class="mb-0">
                                    <li>Nama pelajaran harus <strong>unik</strong> dan belum terdaftar</li>
                                    <li>Pilih kelas dan <strong>tetapkan guru pengajar</strong> untuk setiap kelas</li>
                                    <li>Jika checkbox kelas dicentang, <strong>wajib pilih guru</strong> untuk kelas tersebut</li>
                                    <li>Satu mata pelajaran bisa diajarkan oleh <strong>guru berbeda</strong> di kelas yang berbeda</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label fw-bold">Pilih Kelas dan Guru Pengajar</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAllKelas" onchange="toggleSelectAllKelas(this)">
                                    <label class="form-check-label" for="selectAllKelas">Pilih Semua Kelas</label>
                                </div>
                            </div>

                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex gap-2 mb-2 p-2 bg-light rounded">
                                            <div style="width: 30px;">Pilih</div>
                                            <div class="flex-grow-1">Kelas</div>
                                            <div style="min-width: 200px;">Guru Pengajar</div>
                                        </div>
                                    </div>

                                    <?php
                                    // Ambil daftar kelas
                                    $stmt_kelas = $pdo->query("SELECT id_kelas, nama_kelas, tahun_ajaran FROM kelas ORDER BY tahun_ajaran DESC, nama_kelas ASC");
                                    $all_kelas = $stmt_kelas->fetchAll();

                                    // Ambil daftar guru aktif
                                    $stmt_guru = $pdo->query("SELECT id_guru, nama_guru FROM user_guru WHERE status = 'aktif' ORDER BY nama_guru ASC");
                                    $all_guru = $stmt_guru->fetchAll();
                                    ?>

                                    <?php foreach ($all_kelas as $kelas): ?>
                                    <div class="col-12">
                                        <div class="d-flex gap-2 align-items-center p-2 border rounded">
                                            <input class="form-check-input kelas-checkbox" type="checkbox"
                                                   id="kelas_<?php echo $kelas['id_kelas']; ?>"
                                                   name="kelas_guru[<?php echo $kelas['id_kelas']; ?>][aktif]"
                                                   value="1"
                                                   onchange="toggleGuruSelect(<?php echo $kelas['id_kelas']; ?>)">
                                            <label class="form-check-label flex-grow-1 mb-0" for="kelas_<?php echo $kelas['id_kelas']; ?>">
                                                <?php echo htmlspecialchars($kelas['nama_kelas'] . ' - ' . $kelas['tahun_ajaran']); ?>
                                            </label>
                                            <select name="kelas_guru[<?php echo $kelas['id_kelas']; ?>][id_guru]"
                                                    id="guru_<?php echo $kelas['id_kelas']; ?>"
                                                    class="form-select"
                                                    style="min-width: 200px;"
                                                    disabled required>
                                                <option value="">-- Pilih Guru --</option>
                                                <?php foreach ($all_guru as $guru): ?>
                                                <option value="<?php echo $guru['id_guru']; ?>">
                                                    <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-text mt-2">
                                <i class="fas fa-check-circle text-success me-1"></i> Centang kelas dan pilih guru untuk menghubungkan pelajaran ke kelas tersebut
                            </div>
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

    <!-- Modal Edit Pelajaran -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_pelajaran" id="edit_id_pelajaran">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Pelajaran</label>
                            <input type="text" name="nama_pelajaran" id="edit_nama_pelajaran" class="form-control" required>
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

    <!-- Modal Hapus Pelajaran -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_pelajaran" id="delete_id_pelajaran">
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus mata pelajaran <strong id="delete_pelajaran_name"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
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

        function editPelajaran(pelajaran) {
            document.getElementById('edit_id_pelajaran').value = pelajaran.id_pelajaran;
            document.getElementById('edit_nama_pelajaran').value = pelajaran.nama_pelajaran;
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

        function deletePelajaran(id, nama) {
            document.getElementById('delete_id_pelajaran').value = id;
            document.getElementById('delete_pelajaran_name').textContent = nama;
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

        // Fungsi untuk toggle select all kelas
        function toggleSelectAllKelas(checkbox) {
            const kelasCheckboxes = document.querySelectorAll('.kelas-checkbox');
            kelasCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                const idKelas = cb.id.replace('kelas_', '');
                toggleGuruSelect(idKelas);
            });
        }

        // Fungsi untuk toggle select guru
        function toggleGuruSelect(idKelas) {
            const checkbox = document.getElementById('kelas_' + idKelas);
            const select = document.getElementById('guru_' + idKelas);

            if (checkbox.checked) {
                select.disabled = false;
                select.required = true;
                select.style.borderColor = '#10b981';
                select.style.backgroundColor = 'white';
            } else {
                select.disabled = true;
                select.required = false;
                select.value = '';
                select.style.borderColor = '#e2e8f0';
                select.style.backgroundColor = '#f8f9fa';
            }
        }

        // Validasi sebelum submit form tambah pelajaran
        document.querySelector('#createModal form').addEventListener('submit', function(e) {
            const form = this;
            const kelasCheckboxes = form.querySelectorAll('.kelas-checkbox');
            let hasError = false;
            let errorMsg = '⚠️ Perhatian!\n\nHarap periksa kelas berikut yang belum memiliki guru pengajar:\n\n';

            kelasCheckboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    const idKelas = checkbox.id.replace('kelas_', '');
                    const select = form.querySelector(`#guru_${idKelas}`);

                    if (!select.value) {
                        hasError = true;
                        const labelElement = checkbox.nextElementSibling;
                        const label = labelElement ? labelElement.textContent.trim() : '';
                        errorMsg += `• ${label}\n`;
                        select.style.borderColor = '#f5576c';
                    } else {
                        select.style.borderColor = '#10b981';
                    }
                }
            });

            if (hasError) {
                e.preventDefault();
                alert(errorMsg);
            }
        });

        // Update border color saat memilih guru
        document.querySelectorAll('select[id^="guru_"]').forEach(select => {
            select.addEventListener('change', function() {
                if (this.value) {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#e2e8f0';
                }
            });
        });
    </script>
</body>
</html>