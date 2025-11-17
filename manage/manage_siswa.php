<?php
session_start();
require_once '../config.php';

checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

$success_message = '';
$error_message = '';

// Proses tambah siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $nis = trim($_POST['nis']);
        $nama_siswa = trim($_POST['nama_siswa']);
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $tanggal_lahir = $_POST['tanggal_lahir'];
        $alamat = trim($_POST['alamat']);
        $no_hp = trim($_POST['no_hp']);
        $id_kelas = $_POST['id_kelas'] ?? null;
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validasi
        if (empty($nis) || empty($nama_siswa) || empty($jenis_kelamin) || empty($tanggal_lahir)) {
            throw new Exception("Field wajib diisi!");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Password dan konfirmasi password tidak cocok!");
        }

        if (strlen($password) < 6) {
            throw new Exception("Password minimal 6 karakter!");
        }

        // Cek apakah NIS sudah digunakan
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_siswa WHERE nis = ?");
        $stmt->execute([$nis]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("NIS sudah digunakan!");
        }

        // Cek apakah kelas valid
        if ($id_kelas) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE id_kelas = ?");
            $stmt->execute([$id_kelas]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("Kelas tidak ditemukan!");
            }
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert siswa
        $stmt = $pdo->prepare("
            INSERT INTO user_siswa (nis, nama_siswa, jenis_kelamin, tanggal_lahir, alamat, no_hp, id_kelas, password_login, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif')
        ");
        $stmt->execute([$nis, $nama_siswa, $jenis_kelamin, $tanggal_lahir, $alamat, $no_hp, $id_kelas, $hashed_password]);

        $success_message = "Siswa berhasil ditambahkan!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Proses update siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id_siswa = $_POST['id_siswa'];
        $nis = trim($_POST['nis']);
        $nama_siswa = trim($_POST['nama_siswa']);
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $tanggal_lahir = $_POST['tanggal_lahir'];
        $alamat = trim($_POST['alamat']);
        $no_hp = trim($_POST['no_hp']);
        $id_kelas = $_POST['id_kelas'] ?? null;
        $status = $_POST['status'];
        $password = $_POST['password'] ?? '';

        // Validasi
        if (empty($nis) || empty($nama_siswa) || empty($jenis_kelamin) || empty($tanggal_lahir)) {
            throw new Exception("Field wajib diisi!");
        }

        // Cek apakah NIS digunakan siswa lain
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_siswa WHERE nis = ? AND id_siswa != ?");
        $stmt->execute([$nis, $id_siswa]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("NIS sudah digunakan oleh siswa lain!");
        }

        // Update data siswa
        if (!empty($password)) {
            if (strlen($password) < 6) {
                throw new Exception("Password minimal 6 karakter!");
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE user_siswa
                SET nis = ?, nama_siswa = ?, jenis_kelamin = ?, tanggal_lahir = ?, alamat = ?, 
                    no_hp = ?, id_kelas = ?, status = ?, password_login = ?
                WHERE id_siswa = ?
            ");
            $stmt->execute([$nis, $nama_siswa, $jenis_kelamin, $tanggal_lahir, $alamat, $no_hp, $id_kelas, $status, $hashed_password, $id_siswa]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE user_siswa
                SET nis = ?, nama_siswa = ?, jenis_kelamin = ?, tanggal_lahir = ?, alamat = ?, 
                    no_hp = ?, id_kelas = ?, status = ?
                WHERE id_siswa = ?
            ");
            $stmt->execute([$nis, $nama_siswa, $jenis_kelamin, $tanggal_lahir, $alamat, $no_hp, $id_kelas, $status, $id_siswa]);
        }

        $success_message = "Data siswa berhasil diupdate!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Proses hapus siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $id_siswa = $_POST['id_siswa'];

        // Hapus siswa
        $stmt = $pdo->prepare("DELETE FROM user_siswa WHERE id_siswa = ?");
        $stmt->execute([$id_siswa]);

        $success_message = "Data siswa berhasil dihapus!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';

// Query siswa
$query = "
    SELECT s.*,
           k.nama_kelas, k.tahun_ajaran
    FROM user_siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.nama_siswa LIKE ? OR s.nis LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $query .= " AND s.status = ?";
    $params[] = $status_filter;
}

if (!empty($kelas_filter)) {
    $query .= " AND s.id_kelas = ?";
    $params[] = $kelas_filter;
}

$query .= " ORDER BY s.id_siswa DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_siswa = $stmt->fetchAll();

// Ambil daftar kelas
$stmt = $pdo->query("SELECT id_kelas, nama_kelas, tahun_ajaran FROM kelas ORDER BY nama_kelas");
$all_kelas = $stmt->fetchAll();

// Statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa");
$total_siswa = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'aktif'");
$siswa_aktif = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'nonaktif'");
$siswa_nonaktif = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'lulus'");
$siswa_lulus = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_siswa WHERE status = 'pindah'");
$siswa_pindah = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Siswa - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/manage_siswa.css">
</head>
<body class="light-theme">
    
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">MANAJEMEN SISWA</div>
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
            <a href="../manage/manage_siswa.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Siswa">
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
            <a href="../add/tambah_siswa.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Tambah Siswa">
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
            <h2 class="mb-1">👥 Manajemen Siswa</h2>
            <p class="text-secondary">Kelola data siswa dan akun pelajar</p>
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_siswa; ?></div>
                    <div class="stat-label">Total Siswa</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $siswa_aktif; ?></div>
                    <div class="stat-label">Siswa Aktif</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="stat-value"><?php echo $siswa_lulus; ?></div>
                    <div class="stat-label">Siswa Lulus</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="dashboard-card mb-4">
            <div class="row g-3">
                <div class="col-md-5">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Cari NIS atau nama siswa..." value="<?php echo htmlspecialchars($search); ?>">
                        <select name="kelas" class="form-select">
                            <option value="">Semua Kelas</option>
                            <?php foreach ($all_kelas as $kelas): ?>
                            <option value="<?php echo $kelas['id_kelas']; ?>" <?php echo $kelas_filter === $kelas['id_kelas'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($kelas['nama_kelas'] . ' - ' . $kelas['tahun_ajaran']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo $status_filter === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                            <option value="lulus" <?php echo $status_filter === 'lulus' ? 'selected' : ''; ?>>Lulus</option>
                            <option value="pindah" <?php echo $status_filter === 'pindah' ? 'selected' : ''; ?>>Pindah</option>
                        </select>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <?php if (!empty($search) || !empty($kelas_filter) || !empty($status_filter)): ?>
                        <a href="manage_siswa.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-2"></i>Tambah Siswa Baru
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabel Siswa -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Daftar Siswa (<?php echo $total_siswa; ?>)</h5>
            </div>

            <?php if (count($all_siswa) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Jenis Kelamin</th>
                            <th>No. HP</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_siswa as $idx => $siswa): 
                        $jenis_kelamin_label = $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan';
                        $status_badge = 'bg-success';
                        if ($siswa['status'] === 'nonaktif') $status_badge = 'bg-danger';
                        elseif ($siswa['status'] === 'lulus') $status_badge = 'bg-warning text-dark';
                        elseif ($siswa['status'] === 'pindah') $status_badge = 'bg-info';
                        ?>
                        <tr>
                            <td><strong><?php echo ($idx + 1); ?></strong></td>
                            <td><?php echo htmlspecialchars($siswa['nis']); ?></td>
                            <td><strong><?php echo htmlspecialchars($siswa['nama_siswa']); ?></strong></td>
                            <td>
                                <?php if ($siswa['nama_kelas']): ?>
                                <?php echo htmlspecialchars($siswa['nama_kelas'] . ' - ' . $siswa['tahun_ajaran']); ?>
                                <?php else: ?>
                                <span class="badge bg-secondary">Belum Ada Kelas</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $jenis_kelamin_label; ?></td>
                            <td><?php echo htmlspecialchars($siswa['no_hp'] ?? '-'); ?></td>
                            <td>
                                <span class="badge <?php echo $status_badge; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $siswa['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm me-1" onclick='editSiswa(<?php echo json_encode($siswa); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick='deleteSiswa(<?php echo $siswa['id_siswa']; ?>, "<?php echo addslashes(htmlspecialchars($siswa['nama_siswa'])); ?>")'>
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
                    <i class="fas fa-user-graduate fa-3x text-muted me-3"></i>
                    <div>
                        <h3 class="mb-2">Tidak Ada Siswa</h3>
                        <p class="text-muted">Belum ada data siswa di sistem</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Tambah Siswa -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Siswa Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">NIS <span class="text-danger">*</span></label>
                                <input type="text" name="nis" class="form-control" required placeholder="Nomor Induk Siswa">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama_siswa" class="form-control" required placeholder="Nama lengkap siswa">
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select name="jenis_kelamin" class="form-select" required>
                                    <option value="">Pilih Jenis Kelamin</option>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_lahir" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-select" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                    <option value="lulus">Lulus</option>
                                    <option value="pindah">Pindah</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" name="no_hp" class="form-control" placeholder="Contoh: 08123456789">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kelas</label>
                                <select name="id_kelas" class="form-select">
                                    <option value="">Pilih Kelas</option>
                                    <?php foreach ($all_kelas as $kelas): ?>
                                    <option value="<?php echo $kelas['id_kelas']; ?>">
                                        <?php echo htmlspecialchars($kelas['nama_kelas'] . ' - ' . $kelas['tahun_ajaran']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required placeholder="Minimal 6 karakter">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
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

    <!-- Modal Edit Siswa -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Siswa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_siswa" id="edit_id_siswa">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">NIS <span class="text-danger">*</span></label>
                                <input type="text" name="nis" id="edit_nis" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama_siswa" id="edit_nama_siswa" class="form-control" required>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                                <select name="jenis_kelamin" id="edit_jenis_kelamin" class="form-select" required>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Lahir <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_lahir" id="edit_tanggal_lahir" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="status" id="edit_status" class="form-select" required>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                    <option value="lulus">Lulus</option>
                                    <option value="pindah">Pindah</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">Nomor HP</label>
                                <input type="text" name="no_hp" id="edit_no_hp" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kelas</label>
                                <select name="id_kelas" id="edit_id_kelas" class="form-select">
                                    <option value="">Tidak Ada Kelas</option>
                                    <?php foreach ($all_kelas as $kelas): ?>
                                    <option value="<?php echo $kelas['id_kelas']; ?>">
                                        <?php echo htmlspecialchars($kelas['nama_kelas'] . ' - ' . $kelas['tahun_ajaran']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Alamat</label>
                            <textarea name="alamat" id="edit_alamat" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Password Baru (kosongkan jika tidak ingin mengganti)</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <input type="password" name="password" class="form-control" placeholder="Password baru">
                                </div>
                                <div class="col-md-6">
                                    <input type="password" name="confirm_password" class="form-control" placeholder="Konfirmasi password baru">
                                </div>
                            </div>
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

    <!-- Modal Hapus Siswa -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_siswa" id="delete_id_siswa">
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus siswa <strong id="delete_nama_siswa"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
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

        function editSiswa(siswa) {
            document.getElementById('edit_id_siswa').value = siswa.id_siswa;
            document.getElementById('edit_nis').value = siswa.nis;
            document.getElementById('edit_nama_siswa').value = siswa.nama_siswa;
            document.getElementById('edit_jenis_kelamin').value = siswa.jenis_kelamin;
            document.getElementById('edit_tanggal_lahir').value = siswa.tanggal_lahir;
            document.getElementById('edit_no_hp').value = siswa.no_hp || '';
            document.getElementById('edit_id_kelas').value = siswa.id_kelas || '';
            document.getElementById('edit_alamat').value = siswa.alamat || '';
            document.getElementById('edit_status').value = siswa.status;
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

        function deleteSiswa(id, nama) {
            document.getElementById('delete_id_siswa').value = id;
            document.getElementById('delete_nama_siswa').textContent = nama;
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