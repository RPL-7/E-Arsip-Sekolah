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
        $nis = trim($_POST['nis']);
        $nama_siswa = trim($_POST['nama_siswa']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $jenis_kelamin = $_POST['jenis_kelamin'];
        $tanggal_lahir = $_POST['tanggal_lahir'];
        $alamat = trim($_POST['alamat']);
        $no_hp = trim($_POST['no_hp']);
        $id_kelas = $_POST['id_kelas'] ?? null;
        
        // Validasi
        if (empty($nis) || empty($nama_siswa) || empty($password)) {
            throw new Exception("NIS, Nama, dan Password wajib diisi!");
        }
        
        if ($password !== $confirm_password) {
            throw new Exception("Password dan konfirmasi password tidak sama!");
        }
        
        if (strlen($password) < 6) {
            throw new Exception("Password minimal 6 karakter!");
        }
        
        // Cek apakah NIS sudah ada
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_siswa WHERE nis = ?");
        $stmt->execute([$nis]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("NIS sudah terdaftar!");
        }
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert ke database
        $stmt = $pdo->prepare("
            INSERT INTO user_siswa (nis, nama_siswa, password_login, jenis_kelamin, tanggal_lahir, alamat, no_hp, id_kelas, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktif')
        ");
        
        $stmt->execute([
            $nis,
            $nama_siswa,
            $password_hash,
            $jenis_kelamin,
            $tanggal_lahir,
            $alamat,
            $no_hp,
            $id_kelas ? $id_kelas : null
        ]);
        
        $success_message = "Akun siswa berhasil dibuat! ID Siswa: " . $pdo->lastInsertId() . ". Siswa dapat login dengan NIS: " . $nis;
        
        // Reset form
        $_POST = array();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Ambil daftar kelas untuk dropdown
$stmt = $pdo->query("SELECT id_kelas, nama_kelas, tahun_ajaran FROM kelas ORDER BY nama_kelas");
$all_kelas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Siswa Baru - Admin</title>
    <link rel="stylesheet" href="../css/tambah_siswa.css">
</head>
<body>
    <div class="navbar">
        <h1>➕ Tambah Siswa Baru</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="../dashboard/dashboard_admin.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Form Pendaftaran Siswa Baru</h2>
            <p>Isi semua data siswa dengan lengkap dan benar</p>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
            <div style="margin-top: 10px;">
                <a href="tambah_siswa.php" class="btn btn-success" style="font-size: 12px; padding: 6px 12px;">Tambah Siswa Lagi</a>
                <a href="manage_siswa.php" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">Lihat Daftar Siswa</a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>📝 Data Siswa</h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <h4>ℹ️ Informasi Penting</h4>
                    <ul>
                        <li><strong>NIS</strong> akan digunakan siswa untuk login ke sistem</li>
                        <li><strong>Password</strong> harus minimal 6 karakter dan akan dienkripsi</li>
                        <li>Siswa dapat <strong>mengubah password</strong> sendiri setelah login</li>
                        <li><strong>Kelas</strong> dapat diisi sekarang atau nanti (opsional)</li>
                        <li>Status siswa otomatis diset sebagai <strong>Aktif</strong></li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>NIS <span style="color: red;">*</span></label>
                            <input type="text" name="nis" required placeholder="Masukkan NIS" value="<?php echo $_POST['nis'] ?? ''; ?>">
                            <small style="color: #718096; font-size: 12px;">NIS akan digunakan untuk login</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Lengkap <span style="color: red;">*</span></label>
                            <input type="text" name="nama_siswa" required placeholder="Masukkan nama lengkap" value="<?php echo $_POST['nama_siswa'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Jenis Kelamin <span style="color: red;">*</span></label>
                            <select name="jenis_kelamin" required>
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="L" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] === 'L') ? 'selected' : ''; ?>>Laki-laki</option>
                                <option value="P" <?php echo (isset($_POST['jenis_kelamin']) && $_POST['jenis_kelamin'] === 'P') ? 'selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Tanggal Lahir <span style="color: red;">*</span></label>
                            <input type="date" name="tanggal_lahir" required value="<?php echo $_POST['tanggal_lahir'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Nomor HP / WhatsApp</label>
                            <input type="text" name="no_hp" placeholder="08xxxxxxxxxx" value="<?php echo $_POST['no_hp'] ?? ''; ?>">
                            <small style="color: #718096; font-size: 12px;">Nomor yang dapat dihubungi</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Kelas</label>
                            <select name="id_kelas">
                                <option value="">Belum Ada Kelas (Opsional)</option>
                                <?php foreach ($all_kelas as $kelas): ?>
                                <option value="<?php echo $kelas['id_kelas']; ?>" <?php echo (isset($_POST['id_kelas']) && $_POST['id_kelas'] == $kelas['id_kelas']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kelas['nama_kelas']) . ' - ' . htmlspecialchars($kelas['tahun_ajaran']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: #718096; font-size: 12px;">Dapat diisi nanti jika belum ada kelas</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Password <span style="color: red;">*</span></label>
                            <input type="password" name="password" required placeholder="Minimal 6 karakter">
                            <small style="color: #718096; font-size: 12px;">Password untuk login siswa</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Konfirmasi Password <span style="color: red;">*</span></label>
                            <input type="password" name="confirm_password" required placeholder="Ketik ulang password">
                            <small style="color: #718096; font-size: 12px;">Harus sama dengan password</small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Alamat Lengkap</label>
                            <textarea name="alamat" placeholder="Masukkan alamat lengkap siswa"><?php echo $_POST['alamat'] ?? ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">✓ Simpan Data Siswa</button>
                        <button type="reset" class="btn btn-secondary">🔄 Reset Form</button>
                        <a href="manage_siswa.php" class="btn btn-secondary">← Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Auto close alert after 10 seconds (lebih lama untuk success message)
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