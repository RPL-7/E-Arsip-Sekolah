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
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admin SET username = ?, nama_admin = ?, email = ?, password = ? WHERE id_admin = ?");
            $stmt->execute([$username, $nama_admin, $email, $hashed_password, $id_admin]);
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
    <title>Manajemen Admin</title>
    <link rel="stylesheet" href="../css/dashboard_admin.css">
    <style>
        .main-content { padding: 30px; }
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header-text h2 { color: #2d3748; font-size: 28px; margin-bottom: 5px; }
        .page-header-text p { color: #718096; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea; }
        .stat-card h3 { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #667eea; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .card-body { padding: 25px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-bar input { padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; flex: 1; min-width: 250px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #f5576c; color: white; }
        .btn-warning { background: #ffa726; color: white; }
        .btn-success { background: #38ef7d; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; font-weight: 600; color: #2d3748; font-size: 14px; }
        tr:hover { background: #f7fafc; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-primary { background: #d1ecf1; color: #0c5460; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; margin: 20px; }
        .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0; }
        .modal-header h3 { font-size: 20px; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
        .modal-body { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input { width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        .info-box p { color: #1976d2; font-size: 14px; line-height: 1.6; }
        .empty-state { text-align: center; padding: 60px 20px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="navbar-content">
            <h1><span>👤</span> Manajemen Admin</h1>
            <div class="user-info">
                <div class="user-badge"><strong><?php echo htmlspecialchars($user_name); ?></strong></div>
                <a href="../dashboard/dashboard_admin.php" class="logout-btn">← Kembali</a>
            </div>
        </div>
    </div>

    <div class="container">
        <aside class="sidebar">
            <a href="../dashboard/dashboard_admin.php" class="menu-item">
                <span>📊</span> Dashboard
            </a>
            <a href="../manage/manage_siswa.php" class="menu-item">
                <span>👥</span> Manajemen Siswa
            </a>
            <a href="../manage/manage_guru.php" class="menu-item">
                <span>👨‍🏫</span> Manajemen Guru
            </a>
            <a href="../manage/manage_kelas.php" class="menu-item">
                <span>🏫</span> Manajemen Kelas
            </a>
            <a href="../manage/manage_pelajaran.php" class="menu-item">
                <span>📚</span> Manajemen Pelajaran
            </a>
            <a href="../manage/manage_arsip.php" class="menu-item">
                <span>📁</span> Manajemen Arsip
            </a>
            <a href="manage_admin.php" class="menu-item active">
                <span>👤</span> Manajemen Admin
            </a>
        </aside>

        <main class="main-content">
            <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="page-header">
                <div class="page-header-text">
                    <h2>Manajemen Administrator</h2>
                    <p>Kelola akun administrator sistem</p>
                </div>
                <button class="btn btn-success" onclick="openCreateModal()">➕ Tambah Admin Baru</button>
            </div>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card">
                    <h3>Total Admin</h3>
                    <div class="number"><?php echo $total_admin; ?></div>
                </div>
                <div class="stat-card" style="border-left-color: #38ef7d;">
                    <h3>Status</h3>
                    <div class="number" style="color: #38ef7d; font-size: 18px;">Aktif</div>
                </div>
                <div class="stat-card" style="border-left-color: #ffa726;">
                    <h3>Akses Level</h3>
                    <div class="number" style="color: #ffa726; font-size: 18px;">Full Access</div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card">
                <div class="card-body" style="padding: 20px;">
                    <form method="GET" class="filter-bar">
                        <input type="text" name="search" placeholder="Cari username, nama, atau email..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">🔍 Cari</button>
                        <?php if (!empty($search)): ?>
                        <a href="manage_admin.php" class="btn btn-secondary">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Tabel Admin -->
            <div class="card">
                <div class="card-body">
                    <h3 style="margin-bottom: 20px;">Daftar Administrator (<?php echo $total_admin; ?>)</h3>
                    
                    <?php if (count($all_admin) > 0): ?>
                    <div class="table-responsive">
                        <table>
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
                                        <span class="badge badge-warning" style="margin-left: 8px;">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['nama_admin']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                    <td><span class="badge badge-primary">Aktif</span></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" onclick='editAdmin(<?php echo json_encode($admin); ?>)'>
                                            ✏️ Edit
                                        </button>
                                        <?php if ($admin['id_admin'] != $user_id): ?>
                                        <button class="btn btn-danger btn-sm" onclick="deleteAdmin(<?php echo $admin['id_admin']; ?>, '<?php echo htmlspecialchars($admin['username']); ?>')">
                                            🗑️ Hapus
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div style="font-size: 64px; margin-bottom: 20px;">👤</div>
                        <h3 style="margin-bottom: 10px; color: #718096;">Tidak Ada Data</h3>
                        <p>Belum ada admin di sistem</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Create -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Tambah Admin Baru</h3>
                <button class="modal-close" onclick="closeCreateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <p><strong>ℹ️ Informasi:</strong><br>
                    Admin memiliki akses penuh ke semua fitur sistem. Pastikan hanya memberikan akses admin kepada orang yang terpercaya.</p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="form-group">
                        <label>Username <span style="color: red;">*</span></label>
                        <input type="text" name="username" required placeholder="Contoh: admin2">
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap <span style="color: red;">*</span></label>
                        <input type="text" name="nama_admin" required placeholder="Contoh: John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span style="color: red;">*</span></label>
                        <input type="email" name="email" required placeholder="Contoh: admin@sekolah.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Password <span style="color: red;">*</span></label>
                        <input type="password" name="password" required placeholder="Minimal 6 karakter">
                    </div>
                    
                    <div class="form-group">
                        <label>Konfirmasi Password <span style="color: red;">*</span></label>
                        <input type="password" name="confirm_password" required placeholder="Ulangi password">
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">✓ Simpan</button>
                        <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Admin</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_admin" id="edit_id_admin">
                    
                    <div class="form-group">
                        <label>Username <span style="color: red;">*</span></label>
                        <input type="text" name="username" id="edit_username" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Nama Lengkap <span style="color: red;">*</span></label>
                        <input type="text" name="nama_admin" id="edit_nama_admin" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span style="color: red;">*</span></label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Password Baru (Opsional)</label>
                        <input type="password" name="password" id="edit_password" placeholder="Kosongkan jika tidak ingin mengubah password">
                        <small style="color: #718096; font-size: 12px; display: block; margin-top: 5px;">
                            Isi hanya jika ingin mengubah password
                        </small>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">✓ Update</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Delete -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);">
                <h3>🗑️ Konfirmasi Hapus</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #2d3748;">
                    Apakah Anda yakin ingin menghapus admin <strong id="delete_username"></strong>?
                </p>
                <p style="color: #e53e3e; font-size: 14px; margin-bottom: 20px;">
                    ⚠️ Admin yang dihapus tidak dapat login lagi ke sistem!
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_admin" id="delete_id_admin">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
        }
        
        function editAdmin(admin) {
            document.getElementById('edit_id_admin').value = admin.id_admin;
            document.getElementById('edit_username').value = admin.username;
            document.getElementById('edit_nama_admin').value = admin.nama_admin;
            document.getElementById('edit_email').value = admin.email;
            document.getElementById('edit_password').value = '';
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function deleteAdmin(id, username) {
            document.getElementById('delete_id_admin').value = id;
            document.getElementById('delete_username').textContent = username;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const modals = ['createModal', 'editModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        }
        
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>