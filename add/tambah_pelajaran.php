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
        $kelas_terpilih = $_POST['kelas'] ?? [];
        
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
        
        // Insert relasi ke kelas_pelajaran jika ada kelas yang dipilih
        if (!empty($kelas_terpilih)) {
            $stmt = $pdo->prepare("INSERT INTO kelas_pelajaran (id_kelas, id_pelajaran) VALUES (?, ?)");
            foreach ($kelas_terpilih as $id_kelas) {
                $stmt->execute([$id_kelas, $id_pelajaran_baru]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = "Mata pelajaran berhasil ditambahkan! ID Pelajaran: " . $id_pelajaran_baru;
        if (!empty($kelas_terpilih)) {
            $success_message .= " dan berhasil ditambahkan ke " . count($kelas_terpilih) . " kelas.";
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
            <p>Tambahkan mata pelajaran dan tentukan kelas yang mengajarkan</p>
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
                        <li>Anda dapat menambahkan pelajaran ke <strong>beberapa kelas sekaligus</strong></li>
                        <li>Kelas dapat ditambahkan sekarang atau nanti (opsional)</li>
                        <li>Mata pelajaran dapat diubah atau dihapus dari menu Manajemen Pelajaran</li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label>Nama Mata Pelajaran <span style="color: red;">*</span></label>
                        <input type="text" name="nama_pelajaran" required placeholder="Contoh: Matematika, Bahasa Indonesia, IPA" value="<?php echo $_POST['nama_pelajaran'] ?? ''; ?>">
                        <small style="color: #718096; font-size: 12px; display: block; margin-top: 5px;">Masukkan nama lengkap mata pelajaran</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Pilih Kelas yang Mengajarkan (Opsional)</label>
                        
                        <?php if (count($all_kelas) > 0): ?>
                        <div class="kelas-selection">
                            <div class="select-all">
                                <input type="checkbox" id="select_all" onclick="toggleSelectAll(this)">
                                <label for="select_all">Pilih Semua Kelas</label>
                            </div>
                            
                            <?php foreach ($all_kelas as $kelas): ?>
                            <div class="kelas-item">
                                <input type="checkbox" name="kelas[]" value="<?php echo $kelas['id_kelas']; ?>" id="kelas_<?php echo $kelas['id_kelas']; ?>" class="kelas-checkbox">
                                <label for="kelas_<?php echo $kelas['id_kelas']; ?>">
                                    <?php echo htmlspecialchars($kelas['nama_kelas']) . ' - ' . htmlspecialchars($kelas['tahun_ajaran']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <small style="color: #718096; font-size: 12px; display: block; margin-top: 8px;">
                            Pilih kelas yang akan mengajarkan mata pelajaran ini. Anda dapat memilih lebih dari satu kelas.
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
                        <button type="reset" class="btn btn-secondary" onclick="uncheckAll()">🔄 Reset Form</button>
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
            });
        }
        
        function uncheckAll() {
            document.getElementById('select_all').checked = false;
            const kelasCheckboxes = document.querySelectorAll('.kelas-checkbox');
            kelasCheckboxes.forEach(cb => {
                cb.checked = false;
            });
        }
        
        // Update select all checkbox based on individual checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const kelasCheckboxes = document.querySelectorAll('.kelas-checkbox');
            const selectAllCheckbox = document.getElementById('select_all');
            
            kelasCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = Array.from(kelasCheckboxes).every(checkbox => checkbox.checked);
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = allChecked;
                    }
                });
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