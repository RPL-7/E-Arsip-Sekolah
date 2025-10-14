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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h1 {
            font-size: 24px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-success {
            background: #38ef7d;
            color: white;
        }
        
        .btn-success:hover {
            background: #2dd36f;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
        }
        
        .btn-back:hover {
            background: white;
            color: #667eea;
        }
        
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #718096;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .card-header h3 {
            font-size: 20px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .info-box ul {
            color: #424242;
            font-size: 14px;
            line-height: 1.8;
            margin-left: 20px;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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