<?php
session_start();
require_once '../config.php';

checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// Ambil data siswa
$stmt = $pdo->prepare("SELECT id_kelas, nama_siswa, nis FROM user_siswa WHERE id_siswa = ?");
$stmt->execute([$user_id]);
$siswa_data = $stmt->fetch();
$id_kelas = $siswa_data['id_kelas'];
$nama_siswa = $siswa_data['nama_siswa'];
$nis = $siswa_data['nis'];

if (!$id_kelas) {
    $error_no_class = true;
} else {
    $error_no_class = false;
    
    // Filter
    $search = $_GET['search'] ?? '';
    $pelajaran_filter = $_GET['pelajaran'] ?? '';
    
    // Ambil semua tugas dengan nilai
    $query = "
        SELECT t.*, 
               p.nama_pelajaran,
               g.nama_guru
        FROM tugas t
        JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
        JOIN user_guru g ON t.id_guru = g.id_guru
        WHERE t.id_kelas = ?
    ";
    $params = [$id_kelas];
    
    if (!empty($search)) {
        $query .= " AND (t.judul_tugas LIKE ? OR p.nama_pelajaran LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($pelajaran_filter)) {
        $query .= " AND t.id_pelajaran = ?";
        $params[] = $pelajaran_filter;
    }
    
    $query .= " ORDER BY t.tanggal_dibuat DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_tugas = $stmt->fetchAll();
    
    // Proses setiap tugas untuk cek nilai
    $nilai_list = [];
    foreach ($all_tugas as &$tugas) {
        $nilai_siswa = json_decode($tugas['nilai_siswa'] ?? '[]', true);
        $file_jawaban = json_decode($tugas['file_jawaban'] ?? '[]', true);
        
        $tugas['sudah_kumpul'] = false;
        $tugas['tanggal_kumpul'] = null;
        $tugas['nilai'] = null;
        
        foreach ($file_jawaban as $fw) {
            if ($fw['id_siswa'] == $user_id) {
                $tugas['sudah_kumpul'] = true;
                $tugas['tanggal_kumpul'] = $fw['tanggal_upload'];
                break;
            }
        }
        
        foreach ($nilai_siswa as $ns) {
            if ($ns['id_siswa'] == $user_id) {
                $tugas['nilai'] = $ns;
                $nilai_list[] = [
                    'tugas' => $tugas['judul_tugas'],
                    'pelajaran' => $tugas['nama_pelajaran'],
                    'nilai' => $ns['nilai'],
                    'tanggal' => $ns['tanggal_nilai']
                ];
                break;
            }
        }
    }
    
    // Ambil pelajaran untuk filter
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.id_pelajaran, p.nama_pelajaran
        FROM tugas t
        JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
        WHERE t.id_kelas = ?
        ORDER BY p.nama_pelajaran
    ");
    $stmt->execute([$id_kelas]);
    $pelajaran_list = $stmt->fetchAll();
    
    // Statistik
    $total_tugas = count($all_tugas);
    $total_dikumpulkan = 0;
    $total_dinilai = 0;
    $total_nilai = 0;
    
    foreach ($all_tugas as $t) {
        if ($t['sudah_kumpul']) $total_dikumpulkan++;
        if ($t['nilai']) {
            $total_dinilai++;
            $total_nilai += $t['nilai']['nilai'];
        }
    }
    
    $rata_rata = $total_dinilai > 0 ? $total_nilai / $total_dinilai : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nilai Saya - Siswa</title>
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
        .btn-secondary { background: #6c757d; color: white; }
        .btn-info { background: #26c6da; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .page-header { margin-bottom: 30px; }
        .page-header h2 { color: #2d3748; font-size: 28px; margin-bottom: 5px; }
        .page-header p { color: #718096; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #667eea; }
        .stat-card h3 { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #667eea; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .card-body { padding: 25px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffa726; }
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
        .badge-secondary { background: #e2e8f0; color: #555; }
        .nilai-display { font-size: 24px; font-weight: bold; color: #ffa726; }
        .nilai-excellent { color: #38ef7d; }
        .nilai-good { color: #ffa726; }
        .nilai-poor { color: #f5576c; }
        .empty-state { text-align: center; padding: 60px 20px; color: #a0aec0; }
        @media (max-width: 768px) {
            .table-responsive { font-size: 13px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📊 Nilai Saya</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($nama_siswa); ?></strong> (<?php echo htmlspecialchars($nis); ?>)</span>
            <a href="../dashboard/dashboard_siswa.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <?php if ($error_no_class): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Perhatian!</strong><br>
            Anda belum terdaftar di kelas manapun. Silakan hubungi admin.
        </div>
        <?php else: ?>
        
        <div class="page-header">
            <h2>Nilai & Penilaian Tugas</h2>
            <p>Lihat semua nilai tugas Anda di semua mata pelajaran</p>
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
                <h3>Rata-rata Nilai</h3>
                <div class="number" style="color: #ffa726;"><?php echo number_format($rata_rata, 2); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #667eea;">
                <h3>Dikumpulkan</h3>
                <div class="number" style="color: #667eea;"><?php echo $total_dikumpulkan; ?></div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card">
            <div class="card-body" style="padding: 20px;">
                <form method="GET" class="filter-bar">
                    <input type="text" name="search" placeholder="Cari judul tugas..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="pelajaran">
                        <option value="">Semua Pelajaran</option>
                        <?php foreach ($pelajaran_list as $pel): ?>
                        <option value="<?php echo $pel['id_pelajaran']; ?>" <?php echo $pelajaran_filter == $pel['id_pelajaran'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pel['nama_pelajaran']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($pelajaran_filter)): ?>
                    <a href="nilai_siswa.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Tabel Nilai -->
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom: 20px;">Daftar Nilai Tugas</h3>
                
                <?php if (count($all_tugas) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Judul Tugas</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Tanggal Tugas</th>
                                <th>Status</th>
                                <th>Nilai</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_tugas as $idx => $tugas): ?>
                            <?php
                            $status_badge = 'badge-secondary';
                            $status_text = 'Belum Dikumpulkan';
                            
                            if ($tugas['nilai']) {
                                $status_badge = 'badge-success';
                                $status_text = 'Sudah Dinilai';
                            } elseif ($tugas['sudah_kumpul']) {
                                $status_badge = 'badge-warning';
                                $status_text = 'Menunggu Nilai';
                            }
                            
                            $nilai_class = '';
                            if ($tugas['nilai']) {
                                $nilai = $tugas['nilai']['nilai'];
                                if ($nilai >= 80) $nilai_class = 'nilai-excellent';
                                elseif ($nilai >= 70) $nilai_class = 'nilai-good';
                                else $nilai_class = 'nilai-poor';
                            }
                            ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($tugas['judul_tugas']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></td>
                                <td><?php echo htmlspecialchars($tugas['nama_guru']); ?></td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($tugas['tanggal_dibuat'])); ?>
                                    <?php if ($tugas['deadline']): ?>
                                    <br><small style="color: #718096;">Deadline: <?php echo date('d/m/Y', strtotime($tugas['deadline'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                                    <?php if ($tugas['sudah_kumpul']): ?>
                                    <br><small style="color: #718096; font-size: 11px;">
                                        Dikumpulkan: <?php echo date('d/m/Y H:i', strtotime($tugas['tanggal_kumpul'])); ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tugas['nilai']): ?>
                                        <div class="nilai-display <?php echo $nilai_class; ?>">
                                            <?php echo number_format($tugas['nilai']['nilai'], 2); ?>
                                        </div>
                                        <small style="color: #718096; font-size: 11px;">
                                            Dinilai: <?php echo date('d/m/Y', strtotime($tugas['nilai']['tanggal_nilai'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tugas['nilai'] && !empty($tugas['nilai']['catatan'])): ?>
                                        <button class="btn btn-info btn-sm" onclick="showCatatan('<?php echo htmlspecialchars($tugas['judul_tugas']); ?>', '<?php echo htmlspecialchars($tugas['nilai']['catatan']); ?>')">
                                            💬 Lihat
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">-</span>
                                    <?php endif; ?>
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
                    <p>Belum ada tugas dari guru untuk kelas Anda</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rekap Per Mata Pelajaran -->
        <?php if ($total_dinilai > 0): ?>
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom: 20px;">Rekap Nilai Per Mata Pelajaran</h3>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Mata Pelajaran</th>
                                <th>Jumlah Tugas Dinilai</th>
                                <th>Rata-rata Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rekap_pelajaran = [];
                            foreach ($all_tugas as $t) {
                                if ($t['nilai']) {
                                    $pelajaran = $t['nama_pelajaran'];
                                    if (!isset($rekap_pelajaran[$pelajaran])) {
                                        $rekap_pelajaran[$pelajaran] = [
                                            'total' => 0,
                                            'sum' => 0
                                        ];
                                    }
                                    $rekap_pelajaran[$pelajaran]['total']++;
                                    $rekap_pelajaran[$pelajaran]['sum'] += $t['nilai']['nilai'];
                                }
                            }
                            
                            foreach ($rekap_pelajaran as $pelajaran => $data):
                                $avg = $data['sum'] / $data['total'];
                                $avg_class = '';
                                if ($avg >= 80) $avg_class = 'nilai-excellent';
                                elseif ($avg >= 70) $avg_class = 'nilai-good';
                                else $avg_class = 'nilai-poor';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($pelajaran); ?></strong></td>
                                <td><?php echo $data['total']; ?> tugas</td>
                                <td>
                                    <div class="nilai-display <?php echo $avg_class; ?>">
                                        <?php echo number_format($avg, 2); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
    </div>

    <!-- Modal Catatan -->
    <div class="modal" id="catatanModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div class="modal-content" style="background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; margin: 20px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
                <h3 style="font-size: 20px;">💬 Catatan dari Guru</h3>
                <button class="modal-close" onclick="closeCatatanModal()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <h4 id="catatan_judul" style="color: #2d3748; margin-bottom: 15px;"></h4>
                <div id="catatan_isi" style="background: #f8f9fa; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6;"></div>
            </div>
        </div>
    </div>

    <script>
        function showCatatan(judul, catatan) {
            document.getElementById('catatan_judul').textContent = judul;
            document.getElementById('catatan_isi').textContent = catatan;
            document.getElementById('catatanModal').style.display = 'flex';
        }
        
        function closeCatatanModal() {
            document.getElementById('catatanModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('catatanModal');
            if (event.target === modal) {
                closeCatatanModal();
            }
        }
    </script>
</body>
</html>