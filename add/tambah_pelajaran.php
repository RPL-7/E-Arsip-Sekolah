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
    try {
        $nama_pelajaran = trim($_POST['nama_pelajaran']);
        $kelas_guru = $_POST['kelas_guru'] ?? []; // Array: [id_kelas => id_guru]
        
        // Validasi
        if (empty($nama_pelajaran)) {
            throw new Exception("Nama mata pelajaran wajib diisi!");
        }
        
        // Cek apakah nama pelajaran sudah ada
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pelajaran WHERE nama_pelajaran = ?");
        $stmt->execute([$nama_pelajaran]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Mata pelajaran dengan nama ini sudah terdaftar!");
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert mata pelajaran
        $stmt = $pdo->prepare("INSERT INTO pelajaran (nama_pelajaran) VALUES (?)");
        $stmt->execute([$nama_pelajaran]);
        $id_pelajaran_baru = $pdo->lastInsertId();
        
        // Insert relasi ke kelas_pelajaran dengan guru
        if (!empty($kelas_guru)) {
            $stmt = $pdo->prepare("INSERT INTO kelas_pelajaran (id_kelas, id_pelajaran, id_guru) VALUES (?, ?, ?)");
            foreach ($kelas_guru as $id_kelas => $id_guru) {
                // Hanya insert jika id_guru tidak kosong
                $guru_id = !empty($id_guru) ? $id_guru : null;
                $stmt->execute([$id_kelas, $id_pelajaran_baru, $guru_id]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = "Mata pelajaran berhasil ditambahkan! ID Pelajaran: " . $id_pelajaran_baru;
        if (!empty($kelas_guru)) {
            $success_message .= " dan berhasil ditambahkan ke " . count($kelas_guru) . " kelas.";
        }
        
        // Reset form
        $_POST = array();
        
    } catch (Exception $e) {
        // Rollback jika ada error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error_message = $e->getMessage();
    }
}

// Ambil daftar kelas untuk checkbox
$stmt = $pdo->query("SELECT id_kelas, nama_kelas, tahun_ajaran FROM kelas ORDER BY tahun_ajaran DESC, nama_kelas ASC");
$all_kelas = $stmt->fetchAll();

// Ambil daftar guru aktif
$stmt = $pdo->query("SELECT id_guru, nama_guru FROM user_guru WHERE status = 'aktif' ORDER BY nama_guru ASC");
$all_guru = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Mata Pelajaran - Admin</title>
    <link rel="stylesheet" href="../css/tambah_pelajaran.css">
</head>
<body>
    <div class="navbar">
        <h1>➕ Tambah Mata Pelajaran</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="../dashboard/dashboard_admin.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Form Mata Pelajaran Baru</h2>
            <p>Tambahkan mata pelajaran, tentukan kelas dan guru pengajar</p>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
            <div style="margin-top: 10px;">
                <a href="tambah_pelajaran.php" class="btn btn-success" style="font-size: 12px; padding: 6px 12px;">Tambah Pelajaran Lagi</a>
                <a href="manage_pelajaran.php" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">Lihat Daftar Pelajaran</a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>📚 Data Mata Pelajaran</h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <h4>ℹ️ Informasi Penting</h4>
                    <ul>
                        <li>Nama mata pelajaran harus <strong>unik</strong> dan belum terdaftar</li>
                        <li>Pilih kelas dan <strong>tetapkan guru pengajar</strong> untuk setiap kelas</li>
                        <li>Jika checkbox kelas dicentang, <strong>wajib pilih guru</strong> untuk kelas tersebut</li>
                        <li>Satu mata pelajaran bisa diajarkan oleh <strong>guru berbeda</strong> di kelas yang berbeda</li>
                    </ul>
                </div>

                <form method="POST" action="" id="pelajaranForm">
                    <div class="form-group">
                        <label>Nama Mata Pelajaran <span style="color: red;">*</span></label>
                        <input type="text" name="nama_pelajaran" required placeholder="Contoh: Matematika, Bahasa Indonesia, IPA" value="<?php echo $_POST['nama_pelajaran'] ?? ''; ?>">
                        <small style="color: #718096; font-size: 12px; display: block; margin-top: 5px;">Masukkan nama lengkap mata pelajaran</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Pilih Kelas dan Guru Pengajar (Opsional)</label>
                        
                        <?php if (count($all_kelas) > 0): ?>
                        <div class="kelas-selection">
                            <div class="select-all">
                                <input type="checkbox" id="select_all" onclick="toggleSelectAll(this)">
                                <label for="select_all">Pilih Semua Kelas</label>
                            </div>
                            
                            <div class="kelas-header">
                                <div>Pilih</div>
                                <div>Kelas</div>
                                <div>Guru Pengajar</div>
                            </div>
                            
                            <?php foreach ($all_kelas as $kelas): ?>
                            <div class="kelas-item">
                                <input type="checkbox" 
                                       class="kelas-checkbox" 
                                       id="kelas_<?php echo $kelas['id_kelas']; ?>" 
                                       onchange="toggleGuruSelect(<?php echo $kelas['id_kelas']; ?>)">
                                
                                <label for="kelas_<?php echo $kelas['id_kelas']; ?>" class="kelas-label">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']) . ' - ' . htmlspecialchars($kelas['tahun_ajaran']); ?>
                                </label>
                                
                                <select name="kelas_guru[<?php echo $kelas['id_kelas']; ?>]" 
                                        id="guru_<?php echo $kelas['id_kelas']; ?>" 
                                        disabled>
                                    <option value="">-- Pilih Guru Pengajar --</option>
                                    <?php foreach ($all_guru as $guru): ?>
                                    <option value="<?php echo $guru['id_guru']; ?>">
                                        <?php echo htmlspecialchars($guru['nama_guru']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: #718096; font-size: 12px; display: block; margin-top: 8px;">
                            ✓ Centang kelas yang akan mengajarkan mata pelajaran ini<br>
                            ✓ Pilih guru yang akan mengajar di kelas tersebut
                        </small>
                        <?php else: ?>
                        <div class="empty-state">
                            <p>Belum ada kelas yang tersedia. Silakan tambah kelas terlebih dahulu.</p>
                            <a href="tambah_kelas.php" class="btn btn-success" style="margin-top: 10px; font-size: 12px; padding: 8px 16px;">Tambah Kelas</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">✓ Simpan Mata Pelajaran</button>
                        <button type="reset" class="btn btn-secondary" onclick="resetForm()">🔄 Reset Form</button>
                        <a href="manage_pelajaran.php" class="btn btn-secondary">← Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSelectAll(checkbox) {
            const kelasCheckboxes = document.querySelectorAll('.kelas-checkbox');
            kelasCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                const idKelas = cb.id.replace('kelas_', '');
                toggleGuruSelect(idKelas);
            });
        }
        
        function toggleGuruSelect(idKelas) {
            const checkbox = document.getElementById('kelas_' + idKelas);
            const select = document.getElementById('guru_' + idKelas);
            
            if (checkbox.checked) {
                select.disabled = false;
                select.style.borderColor = '#667eea';
                select.style.background = 'white';
            } else {
                select.disabled = true;
                select.value = '';
                select.style.borderColor = '#e2e8f0';
                select.style.background = '#f8f9fa';
            }
            
            updateSelectAllCheckbox();
        }
        
        function updateSelectAllCheckbox() {
            const kelasCheckboxes = document.querySelectorAll('.kelas-checkbox');
            const selectAllCheckbox = document.getElementById('select_all');
            
            if (selectAllCheckbox) {
                const allChecked = Array.from(kelasCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
        }
        
        function resetForm() {
            document.getElementById('select_all').checked = false;
            const kelasCheckboxes = document.querySelectorAll('.kelas-checkbox');
            kelasCheckboxes.forEach(cb => {
                cb.checked = false;
                const idKelas = cb.id.replace('kelas_', '');
                toggleGuruSelect(idKelas);
            });
        }
        
        // Validasi form sebelum submit
        document.getElementById('pelajaranForm').addEventListener('submit', function(e) {
            const kelasCheckboxes = document.querySelectorAll('.kelas-checkbox');
            let hasError = false;
            let errorMessage = '';
            
            kelasCheckboxes.forEach(cb => {
                if (cb.checked) {
                    const idKelas = cb.id.replace('kelas_', '');
                    const select = document.getElementById('guru_' + idKelas);
                    
                    if (!select.value) {
                        hasError = true;
                        select.style.borderColor = '#f5576c';
                        const kelasLabel = document.querySelector(`label[for="${cb.id}"]`).textContent;
                        errorMessage += `• ${kelasLabel} belum dipilih guru pengajar\n`;
                    }
                }
            });
            
            if (hasError) {
                e.preventDefault();
                alert('⚠️ Perhatian!\n\nKelas yang dipilih harus memiliki guru pengajar:\n\n' + errorMessage);
            }
        });
        
        // Reset border color saat memilih guru
        document.querySelectorAll('select[id^="guru_"]').forEach(select => {
            select.addEventListener('change', function() {
                if (this.value) {
                    this.style.borderColor = '#38ef7d';
                } else {
                    this.style.borderColor = '#e2e8f0';
                }
            });
        });
        
        // Auto close alert after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-danger');
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