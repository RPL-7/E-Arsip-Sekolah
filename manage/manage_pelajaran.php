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
    
    if ($action === 'update') {
        try {
            $id_pelajaran = $_POST['id_pelajaran'];
            $nama_pelajaran = trim($_POST['nama_pelajaran']);
            $kelas_terpilih = $_POST['kelas'] ?? [];
            
            // Validasi
            if (empty($nama_pelajaran)) {
                throw new Exception("Nama mata pelajaran wajib diisi!");
            }
            
            // Cek apakah nama pelajaran sudah digunakan pelajaran lain
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pelajaran WHERE nama_pelajaran = ? AND id_pelajaran != ?");
            $stmt->execute([$nama_pelajaran, $id_pelajaran]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Nama mata pelajaran sudah digunakan!");
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update nama pelajaran
            $stmt = $pdo->prepare("UPDATE pelajaran SET nama_pelajaran = ? WHERE id_pelajaran = ?");
            $stmt->execute([$nama_pelajaran, $id_pelajaran]);
            
            // Hapus semua relasi kelas lama
            $stmt = $pdo->prepare("DELETE FROM kelas_pelajaran WHERE id_pelajaran = ?");
            $stmt->execute([$id_pelajaran]);
            
            // Insert relasi kelas baru
            if (!empty($kelas_terpilih)) {
                $stmt = $pdo->prepare("INSERT INTO kelas_pelajaran (id_kelas, id_pelajaran) VALUES (?, ?)");
                foreach ($kelas_terpilih as $id_kelas) {
                    $stmt->execute([$id_kelas, $id_pelajaran]);
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success_message = "Data mata pelajaran berhasil diupdate!";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $id_pelajaran = $_POST['id_pelajaran'];
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Hapus relasi di kelas_pelajaran
            $stmt = $pdo->prepare("DELETE FROM kelas_pelajaran WHERE id_pelajaran = ?");
            $stmt->execute([$id_pelajaran]);
            
            // Hapus pelajaran
            $stmt = $pdo->prepare("DELETE FROM pelajaran WHERE id_pelajaran = ?");
            $stmt->execute([$id_pelajaran]);
            
            // Commit transaction
            $pdo->commit();
            
            $success_message = "Mata pelajaran berhasil dihapus!";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
}

// Ambil semua data pelajaran dengan jumlah kelas
$search = $_GET['search'] ?? '';

$query = "
    SELECT p.*, 
           COUNT(DISTINCT kp.id_kelas) as jumlah_kelas
    FROM pelajaran p
    LEFT JOIN kelas_pelajaran kp ON p.id_pelajaran = kp.id_pelajaran
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $query .= " AND p.nama_pelajaran LIKE ?";
    $params[] = "%$search%";
}

$query .= " GROUP BY p.id_pelajaran ORDER BY p.nama_pelajaran ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_pelajaran = $stmt->fetchAll();

// Ambil daftar kelas untuk dropdown
$stmt = $pdo->query("SELECT id_kelas, nama_kelas, tahun_ajaran FROM kelas ORDER BY tahun_ajaran DESC, nama_kelas ASC");
$all_kelas = $stmt->fetchAll();

// Ambil statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pelajaran");
$total_pelajaran = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(DISTINCT id_pelajaran) as total FROM kelas_pelajaran");
$pelajaran_diajarkan = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas");
$total_kelas = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pelajaran - Admin</title>
    <link rel="stylesheet" href="../css/manage_pelajaran.css">
</head>
<body>
    <div class="navbar">
        <h1>📚 Manajemen Pelajaran</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="dashboard_admin.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <div class="page-header-text">
                <h2>Data Mata Pelajaran</h2>
                <p>Kelola mata pelajaran dan kelas yang mengajarkan</p>
            </div>
            <a href="tambah_pelajaran.php" class="btn btn-success">➕ Tambah Pelajaran Baru</a>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Mata Pelajaran</h3>
                <div class="number"><?php echo $total_pelajaran; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Pelajaran Diajarkan</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $pelajaran_diajarkan; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Total Kelas</h3>
                <div class="number" style="color: #ffa726;"><?php echo $total_kelas; ?></div>
            </div>
        </div>

        <!-- Daftar Pelajaran -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Daftar Mata Pelajaran</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari nama mata pelajaran..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search)): ?>
                    <a href="manage_pelajaran.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (count($all_pelajaran) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Mata Pelajaran</th>
                            <th>Jumlah Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_pelajaran as $pelajaran): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pelajaran['id_pelajaran']); ?></td>
                            <td><strong><?php echo htmlspecialchars($pelajaran['nama_pelajaran']); ?></strong></td>
                            <td>
                                <span class="badge <?php echo $pelajaran['jumlah_kelas'] > 0 ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo $pelajaran['jumlah_kelas']; ?> Kelas
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-info btn-sm" onclick="viewKelas(<?php echo $pelajaran['id_pelajaran']; ?>, '<?php echo htmlspecialchars($pelajaran['nama_pelajaran']); ?>')">
                                        🏫 Lihat Kelas
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="editPelajaran(<?php echo htmlspecialchars(json_encode($pelajaran)); ?>)">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deletePelajaran(<?php echo $pelajaran['id_pelajaran']; ?>, '<?php echo htmlspecialchars($pelajaran['nama_pelajaran']); ?>')">
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
                    <p>Tidak ada data mata pelajaran</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit Mata Pelajaran</h3>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id_pelajaran" id="edit_id_pelajaran">
                    
                    <div class="form-group">
                        <label>Nama Mata Pelajaran <span style="color: red;">*</span></label>
                        <input type="text" name="nama_pelajaran" id="edit_nama_pelajaran" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Pilih Kelas yang Mengajarkan</label>
                        <div class="kelas-selection">
                            <div class="select-all">
                                <input type="checkbox" id="edit_select_all" onclick="toggleEditSelectAll(this)">
                                <label for="edit_select_all">Pilih Semua Kelas</label>
                            </div>
                            
                            <?php foreach ($all_kelas as $kelas): ?>
                            <div class="kelas-item">
                                <input type="checkbox" name="kelas[]" value="<?php echo $kelas['id_kelas']; ?>" id="edit_kelas_<?php echo $kelas['id_kelas']; ?>" class="edit-kelas-checkbox">
                                <label for="edit_kelas_<?php echo $kelas['id_kelas']; ?>">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']) . ' - ' . htmlspecialchars($kelas['tahun_ajaran']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
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

    <!-- Modal Delete -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);">
                <h3>🗑️ Konfirmasi Hapus</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: #2d3748;">
                    Apakah Anda yakin ingin menghapus mata pelajaran <strong id="delete_nama_pelajaran"></strong>?
                </p>
                <p style="color: #e53e3e; font-size: 14px; margin-bottom: 20px;">
                    ⚠️ Data yang sudah dihapus tidak dapat dikembalikan!
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_pelajaran" id="delete_id_pelajaran">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Batal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal View Kelas -->
    <div class="modal" id="viewKelasModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🏫 Kelas yang Mengajarkan - <span id="view_nama_pelajaran"></span></h3>
                <button class="modal-close" onclick="closeViewKelasModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="kelas_list_content">
                    <div style="text-align: center; padding: 20px;">
                        <p>Loading...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editPelajaran(pelajaran) {
            document.getElementById('edit_id_pelajaran').value = pelajaran.id_pelajaran;
            document.getElementById('edit_nama_pelajaran').value = pelajaran.nama_pelajaran;
            
            // Ambil kelas yang sudah dipilih
            fetch('get_kelas_pelajaran.php?id_pelajaran=' + pelajaran.id_pelajaran)
                .then(response => response.json())
                .then(data => {
                    // Uncheck semua checkbox
                    const checkboxes = document.querySelectorAll('.edit-kelas-checkbox');
                    checkboxes.forEach(cb => cb.checked = false);
                    
                    // Check checkbox yang sudah dipilih
                    if (data.success && data.kelas) {
                        data.kelas.forEach(kelas => {
                            const checkbox = document.getElementById('edit_kelas_' + kelas.id_kelas);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                });
            
            document.getElementById('editModal').classList.add('active');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }
        
        function toggleEditSelectAll(checkbox) {
            const kelasCheckboxes = document.querySelectorAll('.edit-kelas-checkbox');
            kelasCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }
        
        function deletePelajaran(id, nama) {
            document.getElementById('delete_id_pelajaran').value = id;
            document.getElementById('delete_nama_pelajaran').textContent = nama;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        function viewKelas(idPelajaran, namaPelajaran) {
            document.getElementById('view_nama_pelajaran').textContent = namaPelajaran;
            document.getElementById('viewKelasModal').classList.add('active');
            
            fetch('get_kelas_pelajaran.php?id_pelajaran=' + idPelajaran)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '';
                        if (data.kelas.length > 0) {
                            html = '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<thead><tr>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">No</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Nama Kelas</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Tahun Ajaran</th>';
                            html += '<th style="background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Wali Kelas</th>';
                            html += '</tr></thead><tbody>';
                            
                            data.kelas.forEach((kelas, index) => {
                                html += '<tr style="border-bottom: 1px solid #e2e8f0;">';
                                html += '<td style="padding: 12px;">' + (index + 1) + '</td>';
                                html += '<td style="padding: 12px;"><strong>' + kelas.nama_kelas + '</strong></td>';
                                html += '<td style="padding: 12px;">' + kelas.tahun_ajaran + '</td>';
                                html += '<td style="padding: 12px;">' + (kelas.nama_wali || '-') + '</td>';
                                html += '</tr>';
                            });
                            
                            html += '</tbody></table>';
                        } else {
                            html = '<div style="text-align: center; padding: 40px; color: #a0aec0;"><p>Belum ada kelas yang mengajarkan mata pelajaran ini</p></div>';
                        }
                        document.getElementById('kelas_list_content').innerHTML = html;
                    } else {
                        document.getElementById('kelas_list_content').innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;"><p>Error: ' + data.message + '</p></div>';
                    }
                })
                .catch(error => {
                    document.getElementById('kelas_list_content').innerHTML = '<div style="text-align: center; padding: 20px; color: #e53e3e;"><p>Terjadi kesalahan saat memuat data</p></div>';
                });
        }
        
        function closeViewKelasModal() {
            document.getElementById('viewKelasModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            const viewKelasModal = document.getElementById('viewKelasModal');
            
            if (event.target === editModal) closeEditModal();
            if (event.target === deleteModal) closeDeleteModal();
            if (event.target === viewKelasModal) closeViewKelasModal();
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