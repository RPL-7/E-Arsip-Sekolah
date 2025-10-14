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
        
        $id_guru_baru = $pdo->lastInsertId();
        $success_message = "Akun guru berhasil dibuat! ID Guru: " . $id_guru_baru . ". Guru dapat login dengan ID Guru: " . $id_guru_baru;
        
        // Reset form
        $_POST = array();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Guru Baru - Admin</title>
    <link rel="stylesheet" href="../css/tambah_guru.css">
</head>
<body>
    <div class="navbar">
        <h1>➕ Tambah Guru Baru</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            <a href="../dashboard//dashboard_admin.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Form Pendaftaran Guru Baru</h2>
            <p>Isi semua data guru dengan lengkap dan benar</p>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
            <div style="margin-top: 10px;">
                <a href="tambah_guru.php" class="btn btn-success" style="font-size: 12px; padding: 6px 12px;">Tambah Guru Lagi</a>
                <a href="manage_guru.php" class="btn btn-secondary" style="font-size: 12px; padding: 6px 12px;">Lihat Daftar Guru</a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>📝 Data Guru</h3>
            </div>
            <div class="card-body">
                <div class="info-box">
                    <h4>ℹ️ Informasi Penting</h4>
                    <ul>
                        <li><strong>ID Guru</strong> akan digenerate otomatis dan digunakan untuk login</li>
                        <li><strong>NIP</strong> digunakan untuk identifikasi, pastikan benar dan unique</li>
                        <li><strong>Email</strong> harus valid dan belum terdaftar di sistem</li>
                        <li><strong>Password</strong> harus minimal 6 karakter dan akan dienkripsi</li>
                        <li>Guru dapat <strong>mengubah password</strong> sendiri setelah login</li>
                        <li>Status guru otomatis diset sebagai <strong>Aktif</strong></li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>NIP <span style="color: red;">*</span></label>
                            <input type="text" name="nip" required placeholder="Masukkan NIP" value="<?php echo $_POST['nip'] ?? ''; ?>">
                            <small style="color: #718096; font-size: 12px;">Nomor Induk Pegawai</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Nama Lengkap <span style="color: red;">*</span></label>
                            <input type="text" name="nama_guru" required placeholder="Masukkan nama lengkap" value="<?php echo $_POST['nama_guru'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Email <span style="color: red;">*</span></label>
                            <input type="email" name="email" required placeholder="contoh@email.com" value="<?php echo $_POST['email'] ?? ''; ?>">
                            <small style="color: #718096; font-size: 12px;">Email untuk komunikasi</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Nomor HP / WhatsApp</label>
                            <input type="text" name="no_hp" placeholder="08xxxxxxxxxx" value="<?php echo $_POST['no_hp'] ?? ''; ?>">
                            <small style="color: #718096; font-size: 12px;">Nomor yang dapat dihubungi</small>
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
                            <label>Password <span style="color: red;">*</span></label>
                            <input type="password" name="password" required placeholder="Minimal 6 karakter">
                            <small style="color: #718096; font-size: 12px;">Password untuk login guru</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Konfirmasi Password <span style="color: red;">*</span></label>
                            <input type="password" name="confirm_password" required placeholder="Ketik ulang password">
                            <small style="color: #718096; font-size: 12px;">Harus sama dengan password</small>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>Alamat Lengkap</label>
                            <textarea name="alamat" placeholder="Masukkan alamat lengkap guru"><?php echo $_POST['alamat'] ?? ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">✓ Simpan Data Guru</button>
                        <button type="reset" class="btn btn-secondary">🔄 Reset Form</button>
                        <a href="manage_guru.php" class="btn btn-secondary">← Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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