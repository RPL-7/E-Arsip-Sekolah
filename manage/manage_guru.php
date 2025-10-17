<?php
session_start();
require_once '../config.php';

// Cek apakah user sudah login dan tipe user adalah admin
checkUserType(['admin']);

$user_name = $_SESSION['user_name'];
$user_username = $_SESSION['user_username'];

// Koneksi database
$pdo = getDBConnection();

// Proses form
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        try {
            $nip = trim($_POST['nip']);
            $nama_guru = trim($_POST['nama_guru']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $jenis_kelamin = $_POST['jenis_kelamin'];
            $tanggal_lahir = $_POST['tanggal_lahir'];
            $alamat = trim($_POST['alamat']);
            $no_hp = trim($_POST['no_hp']);
            
            // Validasi
            if (empty($nip) || empty($nama_guru) || empty($email) || empty($password)) {
                throw new Exception("Semua field wajib diisi!");
            }
            
            if ($password !== $confirm_password) {
                throw new Exception("Password dan konfirmasi password tidak sama!");
            }
            
            if (strlen($password) < 6) {
                throw new Exception("Password minimal 6 karakter!");
            }
            
            // Cek apakah NIP sudah ada
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_guru WHERE nip = ?");
            $stmt->execute([$nip]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("NIP sudah terdaftar!");
            }
            
            // Cek apakah email sudah ada
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_guru WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Email sudah terdaftar!");
            }
            
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert ke database
            $stmt = $pdo->prepare("
                INSERT INTO user_guru (nip, nama_guru, email, password_login, jenis_kelamin, tanggal_lahir, alamat, no_hp, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif')
            ");
            
            $stmt->execute([
                $nip,
                $nama_guru,
                $email,
                $password_hash,
                $jenis_kelamin,
                $tanggal_lahir,
                $alamat,
                $no_hp
            ]);
            
            $success_message = "Akun guru berhasil dibuat! ID Guru: " . $pdo->lastInsertId();
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'update') {
        try {
            $id_guru = $_POST['id_guru'];
            $nip = trim($_POST['nip']);
            $nama_guru = trim($_POST['nama_guru']);
            $email = trim($_POST['email']);
            $jenis_kelamin = $_POST['jenis_kelamin'];
            $tanggal_lahir = $_POST['tanggal_lahir'];
            $alamat = trim($_POST['alamat']);
            $no_hp = trim($_POST['no_hp']);
            $status = $_POST['status'];
            
            // Validasi
            if (empty($nip) || empty($nama_guru) || empty($email)) {
                throw new Exception("Field NIP, Nama, dan Email wajib diisi!");
            }
            
            // Cek apakah NIP sudah digunakan guru lain
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_guru WHERE nip = ? AND id_guru != ?");
            $stmt->execute([$nip, $id_guru]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("NIP sudah digunakan oleh guru lain!");
            }
            
            // Cek apakah email sudah digunakan guru lain
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_guru WHERE email = ? AND id_guru != ?");
            $stmt->execute([$email, $id_guru]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Email sudah digunakan oleh guru lain!");
            }
            
            // Update data
            $stmt = $pdo->prepare("
                UPDATE user_guru 
                SET nip = ?, nama_guru = ?, email = ?, jenis_kelamin = ?, 
                    tanggal_lahir = ?, alamat = ?, no_hp = ?, status = ?
                WHERE id_guru = ?
            ");
            
            $stmt->execute([
                $nip,
                $nama_guru,
                $email,
                $jenis_kelamin,
                $tanggal_lahir,
                $alamat,
                $no_hp,
                $status,
                $id_guru
            ]);
            
            // Update password jika diisi
            if (!empty($_POST['password'])) {
                $password = $_POST['password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($password !== $confirm_password) {
                    throw new Exception("Password dan konfirmasi password tidak sama!");
                }
                
                if (strlen($password) < 6) {
                    throw new Exception("Password minimal 6 karakter!");
                }
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE user_guru SET password_login = ? WHERE id_guru = ?");
                $stmt->execute([$password_hash, $id_guru]);
            }
            
            $success_message = "Data guru berhasil diupdate!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $id_guru = $_POST['id_guru'];
            
            // Cek apakah guru masih menjadi wali kelas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas WHERE id_guru_wali = ?");
            $stmt->execute([$id_guru]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Guru masih menjadi wali kelas! Hapus atau ganti wali kelas terlebih dahulu.");
            }
            
            // Hapus guru
            $stmt = $pdo->prepare("DELETE FROM user_guru WHERE id_guru = ?");
            $stmt->execute([$id_guru]);
            
            $success_message = "Data guru berhasil dihapus!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Ambil semua data guru
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM user_guru WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nama_guru LIKE ? OR nip LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY id_guru DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_guru = $stmt->fetchAll();

// Ambil statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_guru WHERE status = 'aktif'");
$total_guru_aktif = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM user_guru WHERE status = 'nonaktif'");
$total_guru_nonaktif = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Guru - Admin</title>
    <link rel="stylesheet" href="../css/manage_guru.css">
</head>
<body>
    <div class="navbar">
        <h1>👨‍🏫 Manajemen Guru</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="/dashboard/dashboard_admin.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Kelola Data Guru</h2>
            <p>Tambah, edit, dan hapus data guru</p>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Guru</h3>
                <div class="number"><?php echo count($all_guru); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Guru Aktif</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $total_guru_aktif; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f5576c;">
                <h3>Guru Nonaktif</h3>
                <div class="number" style="color: #f5576c;"><?php echo $total_guru_nonaktif; ?></div>
            </div>
        </div>


        <!-- Daftar Guru -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Daftar Guru</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari nama, NIP, atau email..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="status">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="nonaktif" <?php echo $status_filter === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                    <a href="manage_guru.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (count($all_guru) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>NIP</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No. HP</th>
                            <th>Jenis Kelamin</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_guru as $guru): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($guru['id_guru']); ?></td>
                            <td><?php echo htmlspecialchars($guru['nip']); ?></td>
                            <td><?php echo htmlspecialchars($guru['nama_guru']); ?></td>
                            <td><?php echo htmlspecialchars($guru['email']); ?></td>
                            <td><?php echo htmlspecialchars($guru['no_hp'] ?? '-'); ?></td>
                            <td><?php echo $guru['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan'; ?></td>
                            <td>
                                <span class="badge <?php echo $guru['status'] === 'aktif' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($guru['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-warning btn-sm" onclick="editGuru(<?php echo htmlspecialchars(json_encode($guru)); ?>)">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteGuru(<?php echo $guru['id_guru']; ?>, '<?php echo htmlspecialchars($guru['nama_guru']); ?>')">
                                        🗑️ Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #a0aec0;">
                    <p>Tidak ada data guru</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Data Guru</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_guru" id="edit_id_guru">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>NIP <span style="color: red;">*</span></label>
                            <input type="text" name="nip" id="edit_nip" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Lengkap <span style="color: red;">*</span></label>
                            <input type="text" name="nama_guru" id="edit_nama_guru" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email <span style="color: red;">*</span></label>
                            <input type="email" name="email" id="edit_email" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Nomor HP</label>
                            <input type="text" name="no_hp" id="edit_no_hp">
                        </div>
                        
                        <div class="form-group">
                            <label>Jenis Kelamin <span style="color: red;">*</span></label>
                            <select name="jenis_kelamin" id="edit_jenis_kelamin" required>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tanggal Lahir <span style="color: red;">*</span></label>
                            <input type="date" name="tanggal_lahir" id="edit_tanggal_lahir" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Status <span style="color: red;">*</span></label>
                            <select name="status" id="edit_status" required>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Alamat</label>
                            <textarea name="alamat" id="edit_alamat"></textarea>
                        </div>
                        
                        <div class="form-group full-width">
                            <hr style="margin: 20px 0;">
                            <p style="color: #718096; font-size: 14px; margin-bottom: 15px;">
                                <strong>Ganti Password</strong> (Kosongkan jika tidak ingin mengubah password)
                            </p>
                        </div>
                        
                        <div class="form-group">
                            <label>Password Baru</label>
                            <input type="password" name="password" id="edit_password" placeholder="Minimal 6 karakter">
                        </div>
                        
                        <div class="form-group">
                            <label>Konfirmasi Password Baru</label>
                            <input type="password" name="confirm_password" id="edit_confirm_password" placeholder="Ketik ulang password">
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn btn-success">✓ Update Data</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Delete Confirmation -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);">
                <h3>🗑️ Konfirmasi Hapus</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #2d3748;">
                    Apakah Anda yakin ingin menghapus guru <strong id="delete_nama_guru"></strong>?
                </p>
                <p style="color: #e53e3e; font-size: 14px; margin-bottom: 20px;">
                    ⚠️ Data yang sudah dihapus tidak dapat dikembalikan!
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_guru" id="delete_id_guru">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Edit Modal Functions
        function editGuru(guru) {
            document.getElementById('edit_id_guru').value = guru.id_guru;
            document.getElementById('edit_nip').value = guru.nip;
            document.getElementById('edit_nama_guru').value = guru.nama_guru;
            document.getElementById('edit_email').value = guru.email;
            document.getElementById('edit_no_hp').value = guru.no_hp || '';
            document.getElementById('edit_jenis_kelamin').value = guru.jenis_kelamin;
            document.getElementById('edit_tanggal_lahir').value = guru.tanggal_lahir;
            document.getElementById('edit_alamat').value = guru.alamat || '';
            document.getElementById('edit_status').value = guru.status;
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_confirm_password').value = '';
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        // Delete Modal Functions
        function deleteGuru(id, nama) {
            document.getElementById('delete_id_guru').value = id;
            document.getElementById('delete_nama_guru').textContent = nama;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
        
        // Auto close alert after 5 seconds
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