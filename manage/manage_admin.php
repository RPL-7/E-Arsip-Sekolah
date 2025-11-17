<?php
session_start();
require_once '../config.php';

checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

$success_message = '';
$error_message = '';

// PROSES TAMBAH ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    try {
        $username = trim($_POST['username']);
        $nama_admin = trim($_POST['nama_admin']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Validasi
        if (empty($username) || empty($nama_admin) || empty($email) || empty($password)) {
            throw new Exception("Semua field wajib diisi!");
        }

        if ($password !== $confirm_password) {
            throw new Exception("Password dan konfirmasi password tidak cocok!");
        }

        if (strlen($password) < 6) {
            throw new Exception("Password minimal 6 karakter!");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid!");
        }

        // Cek username sudah ada
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Username sudah digunakan!");
        }

        // Cek email sudah ada
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Email sudah terdaftar!");
        }

        // Insert admin
        $stmt = $pdo->prepare("INSERT INTO admin (username, nama_admin, email, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $nama_admin, $email, $password]);

        $success_message = "Admin baru berhasil ditambahkan!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// PROSES UPDATE ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    try {
        $id_admin = $_POST['id_admin'];
        $username = trim($_POST['username']);
        $nama_admin = trim($_POST['nama_admin']);
        $email = trim($_POST['email']);
        $password = $_POST['password'] ?? '';

        // Validasi
        if (empty($username) || empty($nama_admin) || empty($email)) {
            throw new Exception("Username, nama, dan email wajib diisi!");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid!");
        }

        // Cek username sudah ada (kecuali milik sendiri)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE username = ? AND id_admin != ?");
        $stmt->execute([$username, $id_admin]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Username sudah digunakan!");
        }

        // Cek email sudah ada (kecuali milik sendiri)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin WHERE email = ? AND id_admin != ?");
        $stmt->execute([$email, $id_admin]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Email sudah terdaftar!");
        }

        // Update
        if (!empty($password)) {
            if (strlen($password) < 6) {
                throw new Exception("Password minimal 6 karakter!");
            }
            $stmt = $pdo->prepare("UPDATE admin SET username = ?, nama_admin = ?, email = ?, password = ? WHERE id_admin = ?");
            $stmt->execute([$username, $nama_admin, $email, $password, $id_admin]);
        } else {
            $stmt = $pdo->prepare("UPDATE admin SET username = ?, nama_admin = ?, email = ? WHERE id_admin = ?");
            $stmt->execute([$username, $nama_admin, $email, $id_admin]);
        }

        $success_message = "Data admin berhasil diupdate!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// PROSES HAPUS ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $id_admin = $_POST['id_admin'];

        // Cek jangan hapus diri sendiri
        if ($id_admin == $user_id) {
            throw new Exception("Anda tidak dapat menghapus akun sendiri!");
        }

        // Cek admin minimal 1
        $stmt = $pdo->query("SELECT COUNT(*) FROM admin");
        if ($stmt->fetchColumn() <= 1) {
            throw new Exception("Minimal harus ada 1 admin di sistem!");
        }

        $stmt = $pdo->prepare("DELETE FROM admin WHERE id_admin = ?");
        $stmt->execute([$id_admin]);

        $success_message = "Admin berhasil dihapus!";

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Ambil semua admin
$search = $_GET['search'] ?? '';

$query = "SELECT * FROM admin WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR nama_admin LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY id_admin ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_admin = $stmt->fetchAll();

// Statistik
$total_admin = count($all_admin);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Admin - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/manage_admin.css">
</head>
<body class="light-theme">
    
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">MANAJEMEN ADMIN</div>
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
                <i class="fas fa-door-open"></i>
                <span>MANAJEMEN KELAS</span>
            </a>
            <a href="../manage/manage_pelajaran.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Pelajaran">
                <i class="fas fa-book"></i>
                <span>MANAJEMEN PELAJARAN</span>
            </a>
            <a href="../manage/manage_admin.php" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Manajemen Admin">
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
            <h2 class="mb-1">👤 Manajemen Admin</h2>
            <p class="text-secondary">Kelola akun administrator sistem</p>
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
                    <div class="stat-value"><?php echo $total_admin; ?></div>
                    <div class="stat-label">Total Admin</div>
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
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="stat-value">Full Access</div>
                    <div class="stat-label">Akses Level</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="dashboard-card mb-4">
            <div class="row g-3">
                <div class="col-md-8">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control" placeholder="Cari username, nama, atau email..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i>Cari
                        </button>
                        <?php if (!empty($search)): ?>
                        <a href="manage_admin.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#createModal">
                        <i class="fas fa-plus me-2"></i>Tambah Admin Baru
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabel Admin -->
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold mb-0">Daftar Administrator (<?php echo $total_admin; ?>)</h5>
            </div>

            <?php if (count($all_admin) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_admin as $admin): ?>
                        <tr>
                            <td><strong><?php echo $admin['id_admin']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($admin['username']); ?></strong>
                                <?php if ($admin['id_admin'] == $user_id): ?>
                                <span class="badge bg-warning text-dark ms-2">Anda</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($admin['nama_admin']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><span class="badge bg-success">Aktif</span></td>
                            <td>
                                <button class="btn btn-warning btn-sm me-1" onclick='editAdmin(<?php echo json_encode($admin); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($admin['id_admin'] != $user_id): ?>
                                <button class="btn btn-danger btn-sm" onclick='deleteAdmin(<?php echo $admin['id_admin']; ?>, "<?php echo addslashes($admin['username']); ?>")'>
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <div class="d-flex align-items-center justify-content-center">
                    <i class="fas fa-user-shield fa-3x text-muted me-3"></i>
                    <div>
                        <h3 class="mb-2">Tidak Ada Administrator</h3>
                        <p class="text-muted">Belum ada administrator di sistem</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Tambah Admin -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Administrator Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_admin" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Konfirmasi Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
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

    <!-- Modal Edit Admin -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Administrator</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_admin" id="edit_id_admin">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_admin" id="edit_nama_admin" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password Baru (kosongkan jika tidak ingin mengganti)</label>
                            <input type="password" name="password" class="form-control">
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

    <!-- Modal Hapus Admin -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_admin" id="delete_id_admin">
                    <div class="modal-body">
                        <p>Apakah Anda yakin ingin menghapus administrator <strong id="delete_username"></strong>? Tindakan ini tidak dapat dibatalkan.</p>
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

        function editAdmin(admin) {
            document.getElementById('edit_id_admin').value = admin.id_admin;
            document.getElementById('edit_username').value = admin.username;
            document.getElementById('edit_nama_admin').value = admin.nama_admin;
            document.getElementById('edit_email').value = admin.email;
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }

        function deleteAdmin(id, username) {
            document.getElementById('delete_id_admin').value = id;
            document.getElementById('delete_username').textContent = username;
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