<?php
session_start();
require_once '../config.php';

checkUserType(['guru']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Ambil data guru
$stmt = $pdo->prepare("SELECT nama_guru FROM user_guru WHERE id_guru = ?");
$stmt->execute([$user_id]);
$nama_guru = $stmt->fetch()['nama_guru'];

// Filter
$search = $_GET['search'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';
$pelajaran_filter = $_GET['pelajaran'] ?? '';

// Ambil semua tugas dengan statistik nilai
$query = "
    SELECT t.*, 
           p.nama_pelajaran,
           k.nama_kelas, k.tahun_ajaran,
           (SELECT COUNT(*) FROM user_siswa WHERE id_kelas = t.id_kelas AND status = 'aktif') as total_siswa,
           JSON_LENGTH(t.file_jawaban) as jumlah_dikumpulkan,
           JSON_LENGTH(t.nilai_siswa) as jumlah_dinilai
    FROM tugas t
    JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
    JOIN kelas k ON t.id_kelas = k.id_kelas
    WHERE t.id_guru = ?
";
$params = [$user_id];

if (!empty($search)) {
    $query .= " AND (t.judul_tugas LIKE ? OR p.nama_pelajaran LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($kelas_filter)) {
    $query .= " AND t.id_kelas = ?";
    $params[] = $kelas_filter;
}

if (!empty($pelajaran_filter)) {
    $query .= " AND t.id_pelajaran = ?";
    $params[] = $pelajaran_filter;
}

$query .= " ORDER BY t.tanggal_dibuat DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_tugas = $stmt->fetchAll();

// Ambil kelas dan pelajaran yang diajar guru
$stmt = $pdo->prepare("
    SELECT DISTINCT k.id_kelas, k.nama_kelas, k.tahun_ajaran, p.id_pelajaran, p.nama_pelajaran
    FROM kelas_pelajaran kp
    JOIN kelas k ON kp.id_kelas = k.id_kelas
    JOIN pelajaran p ON kp.id_pelajaran = p.id_pelajaran
    WHERE kp.id_guru = ?
    ORDER BY k.nama_kelas, p.nama_pelajaran
");
$stmt->execute([$user_id]);
$kelas_pelajaran = $stmt->fetchAll();

// Group by kelas dan pelajaran
$kelas_list = [];
$pelajaran_list = [];
foreach ($kelas_pelajaran as $kp) {
    $kelas_list[$kp['id_kelas']] = $kp['nama_kelas'] . ' - ' . $kp['tahun_ajaran'];
    $pelajaran_list[$kp['id_pelajaran']] = $kp['nama_pelajaran'];
}
$kelas_list = array_unique($kelas_list);
$pelajaran_list = array_unique($pelajaran_list);

// Statistik
$total_tugas = count($all_tugas);
$total_dinilai = 0;
$total_belum_dinilai = 0;

foreach ($all_tugas as $t) {
    $dikumpulkan = $t['jumlah_dikumpulkan'] ?? 0;
    $dinilai = $t['jumlah_dinilai'] ?? 0;
    $total_dinilai += $dinilai;
    $total_belum_dinilai += ($dikumpulkan - $dinilai);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Penilaian - Guru</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-back { background: rgba(255,255,255,0.2); color: white; border: 2px solid white; }
        .btn-back:hover { background: white; color: #11998e; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-info { background: #26c6da; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .page-header { margin-bottom: 30px; }
        .page-header h2 { color: #2d3748; font-size: 28px; margin-bottom: 5px; }
        .page-header p { color: #718096; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #11998e; }
        .stat-card h3 { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #11998e; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .card-body { padding: 25px; }
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-bar input, .filter-bar select { padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
        .filter-bar input { flex: 1; min-width: 250px; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; font-weight: 600; color: #2d3748; font-size: 14px; }
        tr:hover { background: #f7fafc; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .progress-bar { width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #38ef7d; transition: width 0.3s ease; }
        .empty-state { text-align: center; padding: 60px 20px; color: #a0aec0; }
        @media (max-width: 768px) {
            .table-responsive { font-size: 13px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📊 Rekap Penilaian</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($nama_guru); ?></strong></span>
            <a href="../dashboard/dashboard_guru.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Rekap Nilai Per Tugas</h2>
            <p>Lihat statistik penilaian semua tugas Anda</p>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Tugas</h3>
                <div class="number"><?php echo $total_tugas; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Sudah Dinilai</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $total_dinilai; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Belum Dinilai</h3>
                <div class="number" style="color: #ffa726;"><?php echo $total_belum_dinilai; ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card">
            <div class="card-body" style="padding: 20px;">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari judul tugas..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="kelas">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelas_list as $id => $nama): ?>
                        <option value="<?php echo $id; ?>" <?php echo $kelas_filter == $id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nama); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="pelajaran">
                        <option value="">Semua Pelajaran</option>
                        <?php foreach ($pelajaran_list as $id => $nama): ?>
                        <option value="<?php echo $id; ?>" <?php echo $pelajaran_filter == $id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($nama); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($kelas_filter) || !empty($pelajaran_filter)): ?>
                    <a href="penilaian.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Tabel Rekap -->
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom: 20px;">Daftar Tugas & Statistik Penilaian</h3>
                
                <?php if (count($all_tugas) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Judul Tugas</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Total Siswa</th>
                                <th>Dikumpulkan</th>
                                <th>Dinilai</th>
                                <th>Progress</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_tugas as $idx => $tugas): ?>
                            <?php
                            $total_siswa = $tugas['total_siswa'];
                            $dikumpulkan = $tugas['jumlah_dikumpulkan'] ?? 0;
                            $dinilai = $tugas['jumlah_dinilai'] ?? 0;
                            $progress = $dikumpulkan > 0 ? ($dinilai / $dikumpulkan * 100) : 0;
                            
                            $status_badge = 'badge-success';
                            $status_text = 'Selesai';
                            if ($dikumpulkan == 0) {
                                $status_badge = 'badge-danger';
                                $status_text = 'Belum Ada';
                            } elseif ($dinilai < $dikumpulkan) {
                                $status_badge = 'badge-warning';
                                $status_text = 'Proses';
                            }
                            ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($tugas['judul_tugas']); ?></strong><br>
                                    <small style="color: #718096;">
                                        <?php echo date('d/m/Y', strtotime($tugas['tanggal_dibuat'])); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></td>
                                <td><?php echo htmlspecialchars($tugas['nama_kelas']); ?></td>
                                <td><strong><?php echo $total_siswa; ?></strong></td>
                                <td>
                                    <span class="badge badge-info"><?php echo $dikumpulkan; ?></span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status_badge; ?>"><?php echo $dinilai; ?></span>
                                </td>
                                <td style="min-width: 120px;">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%;"></div>
                                    </div>
                                    <small style="color: #718096; font-size: 11px;">
                                        <?php echo number_format($progress, 0); ?>% dinilai
                                    </small>
                                </td>
                                <td>
                                    <a href="../tugas/nilai_tugas.php?id=<?php echo $tugas['id_tugas']; ?>" class="btn btn-info btn-sm">
                                        👁️ Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 64px; margin-bottom: 20px;">📊</div>
                    <h3 style="margin-bottom: 10px; color: #718096;">Belum Ada Tugas</h3>
                    <p>Buat tugas terlebih dahulu untuk melihat rekap penilaian</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>