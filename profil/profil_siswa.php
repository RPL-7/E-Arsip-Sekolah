<?php
session_start();
require_once '../config.php';

checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Ambil data siswa lengkap dengan kelas dan wali kelas
$stmt = $pdo->prepare("
    SELECT s.*, 
           k.nama_kelas, k.tahun_ajaran,
           g.nama_guru as nama_wali_kelas, g.email as email_wali_kelas, g.no_hp as no_hp_wali_kelas
    FROM user_siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN user_guru g ON k.id_guru_wali = g.id_guru
    WHERE s.id_siswa = ?
");
$stmt->execute([$user_id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    header('Location: ../dashboard/dashboard_siswa.php');
    exit();
}

// Format data
$jenis_kelamin_text = $siswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan';
$tanggal_lahir_formatted = $siswa['tanggal_lahir'] ? date('d F Y', strtotime($siswa['tanggal_lahir'])) : '-';
$umur = $siswa['tanggal_lahir'] ? date_diff(date_create($siswa['tanggal_lahir']), date_create('today'))->y : 0;

$status_badge = 'badge-success';
$status_text = 'Aktif';
if ($siswa['status'] === 'nonaktif') {
    $status_badge = 'badge-secondary';
    $status_text = 'Nonaktif';
} elseif ($siswa['status'] === 'lulus') {
    $status_badge = 'badge-info';
    $status_text = 'Lulus';
} elseif ($siswa['status'] === 'pindah') {
    $status_badge = 'badge-warning';
    $status_text = 'Pindah';
}

// Hitung statistik siswa
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM tugas WHERE id_kelas = ?) as total_tugas,
        (SELECT COUNT(*) FROM tugas t WHERE t.id_kelas = ? AND JSON_SEARCH(t.file_jawaban, 'one', ?, null, '$[*].id_siswa') IS NOT NULL) as tugas_dikumpulkan
");
$stmt->execute([$siswa['id_kelas'], $siswa['id_kelas'], $user_id]);
$stats = $stmt->fetch();

$total_tugas = $stats['total_tugas'] ?? 0;
$tugas_dikumpulkan = $stats['tugas_dikumpulkan'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - <?php echo htmlspecialchars($siswa['nama_siswa']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-back { background: rgba(255,255,255,0.2); color: white; border: 2px solid white; }
        .btn-back:hover { background: white; color: #667eea; }
        .btn-primary { background: #667eea; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .profile-header { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 40px; margin-bottom: 30px; text-align: center; }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 48px; color: white; margin: 0 auto 20px; font-weight: bold; }
        .profile-name { font-size: 28px; color: #2d3748; margin-bottom: 5px; font-weight: 700; }
        .profile-nis { font-size: 16px; color: #718096; margin-bottom: 15px; }
        .badge { padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; display: inline-block; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-secondary { background: #e2e8f0; color: #555; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea; }
        .stat-card h3 { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #667eea; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; }
        .card-header h3 { font-size: 20px; }
        .card-body { padding: 30px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; }
        .info-item { padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea; }
        .info-label { font-size: 13px; color: #718096; margin-bottom: 5px; font-weight: 600; }
        .info-value { font-size: 16px; color: #2d3748; font-weight: 600; }
        .icon { font-size: 20px; margin-right: 8px; }
        .no-class-alert { background: #fff3cd; border: 1px solid #ffa726; padding: 20px; border-radius: 8px; text-align: center; color: #856404; }
        @media (max-width: 768px) {
            .info-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>👤 Profil Saya</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($siswa['nama_siswa']); ?></strong></span>
            <a href="../dashboard/dashboard_siswa.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($siswa['nama_siswa'], 0, 1)); ?>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($siswa['nama_siswa']); ?></div>
            <div class="profile-nis">NIS: <?php echo htmlspecialchars($siswa['nis']); ?></div>
            <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Tugas</h3>
                <div class="number"><?php echo $total_tugas; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Tugas Dikumpulkan</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $tugas_dikumpulkan; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Umur</h3>
                <div class="number" style="color: #ffa726;"><?php echo $umur; ?> <span style="font-size: 16px;">tahun</span></div>
            </div>
        </div>

        <!-- Data Pribadi -->
        <div class="card">
            <div class="card-header">
                <h3>📋 Data Pribadi</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><span class="icon">🆔</span>NIS</div>
                        <div class="info-value"><?php echo htmlspecialchars($siswa['nis']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><span class="icon">👤</span>Nama Lengkap</div>
                        <div class="info-value"><?php echo htmlspecialchars($siswa['nama_siswa']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><span class="icon">⚧️</span>Jenis Kelamin</div>
                        <div class="info-value"><?php echo $jenis_kelamin_text; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><span class="icon">🎂</span>Tanggal Lahir</div>
                        <div class="info-value"><?php echo $tanggal_lahir_formatted; ?> (<?php echo $umur; ?> tahun)</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><span class="icon">📱</span>No. Handphone</div>
                        <div class="info-value"><?php echo $siswa['no_hp'] ? htmlspecialchars($siswa['no_hp']) : '-'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><span class="icon">📊</span>Status</div>
                        <div class="info-value">
                            <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($siswa['alamat']): ?>
                <div style="margin-top: 25px;">
                    <div class="info-item">
                        <div class="info-label"><span class="icon">🏠</span>Alamat Lengkap</div>
                        <div class="info-value" style="line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($siswa['alamat']); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Data Kelas & Akademik -->
        <div class="card">
            <div class="card-header">
                <h3>🏫 Data Kelas & Akademik</h3>
            </div>
            <div class="card-body">
                <?php if ($siswa['id_kelas']): ?>
                <div class="info-grid">
                    <div class="info-item" style="border-left-color: #38ef7d;">
                        <div class="info-label"><span class="icon">🏫</span>Kelas</div>
                        <div class="info-value"><?php echo htmlspecialchars($siswa['nama_kelas']); ?></div>
                    </div>
                    <div class="info-item" style="border-left-color: #38ef7d;">
                        <div class="info-label"><span class="icon">📅</span>Tahun Ajaran</div>
                        <div class="info-value"><?php echo htmlspecialchars($siswa['tahun_ajaran']); ?></div>
                    </div>
                    <?php if ($siswa['nama_wali_kelas']): ?>
                    <div class="info-item" style="border-left-color: #ffa726;">
                        <div class="info-label"><span class="icon">👨‍🏫</span>Wali Kelas</div>
                        <div class="info-value"><?php echo htmlspecialchars($siswa['nama_wali_kelas']); ?></div>
                    </div>
                    <?php if ($siswa['email_wali_kelas']): ?>
                    <div class="info-item" style="border-left-color: #ffa726;">
                        <div class="info-label"><span class="icon">📧</span>Email Wali Kelas</div>
                        <div class="info-value"><?php echo htmlspecialchars($siswa['email_wali_kelas']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($siswa['no_hp_wali_kelas']): ?>
                    <div class="info-item" style="border-left-color: #ffa726;">
                        <div class="info-label"><span class="icon">📱</span>No. HP Wali Kelas</div>
                        <div class="info-value"><?php echo htmlspecialchars($siswa['no_hp_wali_kelas']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="no-class-alert">
                    <h3 style="margin-bottom: 10px;">⚠️ Belum Terdaftar di Kelas</h3>
                    <p>Anda belum terdaftar di kelas manapun. Silakan hubungi admin untuk mendaftarkan Anda ke kelas.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3>🔗 Menu Cepat</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <a href="../tugas/tugas_siswa.php" class="btn btn-primary" style="text-align: center;">
                        📝 Lihat Tugas
                    </a>
                    <a href="../tugas/nilai_siswa.php" class="btn btn-primary" style="text-align: center; background: #38ef7d;">
                        📊 Lihat Nilai
                    </a>
                    <a href="../arsip/arsip_siswa.php" class="btn btn-primary" style="text-align: center; background: #ffa726;">
                        📁 Lihat Arsip
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>