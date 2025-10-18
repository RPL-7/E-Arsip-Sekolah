<?php
session_start();
require_once '../config.php';
checkUserType(['guru']);

$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// PROSES PENILAIAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'nilai') {
    try {
        $id_tugas = $_POST['id_tugas'];
        $id_siswa = $_POST['id_siswa'];
        $nilai = floatval($_POST['nilai']);
        $catatan = $_POST['catatan'] ?? '';
        
        if ($nilai < 0 || $nilai > 100) throw new Exception('Nilai harus 0-100');
        
        // Ambil data tugas
        $stmt = $pdo->prepare("SELECT * FROM tugas WHERE id_tugas = ? AND id_guru = ?");
        $stmt->execute([$id_tugas, $user_id]);
        $tugas = $stmt->fetch();
        
        if (!$tugas) throw new Exception('Tugas tidak ditemukan');
        
        // Update atau tambah nilai
        $nilai_siswa = json_decode($tugas['nilai_siswa'] ?? '[]', true);
        $found = false;
        
        foreach ($nilai_siswa as &$ns) {
            if ($ns['id_siswa'] == $id_siswa) {
                $ns['nilai'] = $nilai;
                $ns['catatan'] = $catatan;
                $ns['tanggal_nilai'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $nilai_siswa[] = [
                'id_siswa' => $id_siswa,
                'nilai' => $nilai,
                'catatan' => $catatan,
                'tanggal_nilai' => date('Y-m-d H:i:s')
            ];
        }
        
        $stmt = $pdo->prepare("UPDATE tugas SET nilai_siswa = ? WHERE id_tugas = ?");
        $stmt->execute([json_encode($nilai_siswa), $id_tugas]);
        
        $_SESSION['success'] = 'Nilai berhasil disimpan!';
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: nilai_tugas.php?id=' . $id_tugas);
    exit();
}

// Ambil ID tugas
$id_tugas = $_GET['id'] ?? null;
if (!$id_tugas) {
    header('Location: tugas_guru.php');
    exit();
}

// Ambil data tugas
$stmt = $pdo->prepare("
    SELECT t.*, p.nama_pelajaran, k.nama_kelas
    FROM tugas t
    JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
    JOIN kelas k ON t.id_kelas = k.id_kelas
    WHERE t.id_tugas = ? AND t.id_guru = ?
");
$stmt->execute([$id_tugas, $user_id]);
$tugas = $stmt->fetch();

if (!$tugas) {
    $_SESSION['error'] = 'Tugas tidak ditemukan';
    header('Location: tugas_guru.php');
    exit();
}

// Ambil siswa di kelas ini
$stmt = $pdo->prepare("SELECT id_siswa, nis, nama_siswa FROM user_siswa WHERE id_kelas = ? ORDER BY nama_siswa");
$stmt->execute([$tugas['id_kelas']]);
$all_siswa = $stmt->fetchAll();

// Parse JSON
$file_jawaban = json_decode($tugas['file_jawaban'] ?? '[]', true);
$nilai_siswa = json_decode($tugas['nilai_siswa'] ?? '[]', true);

// Gabungkan data
$pengumpulan_list = [];
foreach ($file_jawaban as $fw) {
    $data = $fw;
    $data['nilai'] = null;
    
    foreach ($nilai_siswa as $ns) {
        if ($ns['id_siswa'] == $fw['id_siswa']) {
            $data['nilai'] = $ns;
            break;
        }
    }
    
    $pengumpulan_list[] = $data;
}

// Statistik
$total_siswa = count($all_siswa);
$sudah_kumpul = count($pengumpulan_list);
$belum_kumpul = $total_siswa - $sudah_kumpul;
$sudah_dinilai = 0;

foreach ($pengumpulan_list as $p) {
    if ($p['nilai']) $sudah_dinilai++;
}

$stmt = $pdo->prepare("SELECT nama_guru FROM user_guru WHERE id_guru = ?");
$stmt->execute([$user_id]);
$nama_guru = $stmt->fetch()['nama_guru'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penilaian Tugas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .navbar { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .navbar h1 { font-size: 24px; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-back { background: rgba(255,255,255,0.2); color: white; border: 2px solid white; }
        .btn-back:hover { background: white; color: #11998e; }
        .btn-warning { background: #ffa726; color: white; }
        .btn-success { background: #38ef7d; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .tugas-info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 12px; margin-bottom: 30px; }
        .tugas-info h3 { font-size: 24px; margin-bottom: 15px; }
        .tugas-info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .tugas-info-item { background: rgba(255,255,255,0.2); padding: 12px; border-radius: 8px; }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid #11998e; }
        .stat-card h3 { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-card .number { font-size: 28px; font-weight: bold; color: #11998e; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px; overflow: hidden; }
        .card-body { padding: 25px; }
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; font-weight: 600; color: #2d3748; }
        tr:hover { background: #f7fafc; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e2e8f0; color: #555; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 12px; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; margin: 20px; }
        .modal-header { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0; }
        .modal-header h3 { font-size: 20px; }
        .modal-close { background: none; border: none; color: white; font-size: 24px; cursor: pointer; }
        .modal-body { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2d3748; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .empty-state { text-align: center; padding: 60px 20px; color: #a0aec0; }
        .empty-state-icon { font-size: 64px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📊 Penilaian Tugas</h1>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($nama_guru); ?></strong></span>
            <a href="tugas_guru.php" class="btn btn-back">← Kembali</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <strong>✓ Berhasil!</strong> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <strong>✗ Error!</strong> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>

        <!-- Info Tugas -->
        <div class="tugas-info">
            <h3><?php echo htmlspecialchars($tugas['judul_tugas']); ?></h3>
            <div class="tugas-info-grid">
                <div class="tugas-info-item">
                    <strong>📚 Mata Pelajaran:</strong><br>
                    <?php echo htmlspecialchars($tugas['nama_pelajaran']); ?>
                </div>
                <div class="tugas-info-item">
                    <strong>🏫 Kelas:</strong><br>
                    <?php echo htmlspecialchars($tugas['nama_kelas']); ?>
                </div>
                <div class="tugas-info-item">
                    <strong>📅 Dibuat:</strong><br>
                    <?php echo date('d/m/Y H:i', strtotime($tugas['tanggal_dibuat'])); ?>
                </div>
                <?php if ($tugas['deadline']): ?>
                <div class="tugas-info-item">
                    <strong>⏰ Deadline:</strong><br>
                    <?php echo date('d/m/Y H:i', strtotime($tugas['deadline'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h3>Total Siswa</h3>
                <div class="number"><?php echo $total_siswa; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #38ef7d;">
                <h3>Sudah Mengumpulkan</h3>
                <div class="number" style="color: #38ef7d;"><?php echo $sudah_kumpul; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #ffa726;">
                <h3>Sudah Dinilai</h3>
                <div class="number" style="color: #ffa726;"><?php echo $sudah_dinilai; ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #f5576c;">
                <h3>Belum Dinilai</h3>
                <div class="number" style="color: #f5576c;"><?php echo $sudah_kumpul - $sudah_dinilai; ?></div>
            </div>
        </div>

        <!-- Tabel Pengumpulan -->
        <div class="card">
            <div class="card-body">
                <h3 style="margin-bottom: 20px;">Daftar Pengumpulan Tugas</h3>
                
                <?php if (count($pengumpulan_list) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Tanggal Upload</th>
                                <th>Status</th>
                                <th>Nilai</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pengumpulan_list as $idx => $p): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td><?php echo htmlspecialchars($p['nis']); ?></td>
                                <td><?php echo htmlspecialchars($p['nama']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($p['tanggal_upload'])); ?></td>
                                <td>
                                    <?php if ($p['nilai']): ?>
                                        <span class="badge badge-success">Sudah Dinilai</span>
                                    <?php elseif ($p['terlambat']): ?>
                                        <span class="badge badge-danger">Terlambat</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Belum Dinilai</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($p['nilai']): ?>
                                        <strong style="color: #ffa726;"><?php echo number_format($p['nilai']['nilai'], 2); ?></strong>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-warning btn-sm" onclick='beriNilai(<?php echo json_encode($p); ?>)'>
                                        <?php echo $p['nilai'] ? '✏️ Edit' : '📝 Nilai'; ?>
                                    </button>
                                    <a href="<?php echo htmlspecialchars($p['file_path']); ?>" target="_blank" class="btn btn-success btn-sm">
                                        📥 Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3 style="margin-bottom: 10px; color: #718096;">Belum Ada Pengumpulan</h3>
                    <p>Belum ada siswa yang mengumpulkan tugas ini</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Beri Nilai -->
    <div class="modal" id="nilaiModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📝 Beri Nilai</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form action="nilai_tugas.php?id=<?php echo $id_tugas; ?>" method="POST">
                    <input type="hidden" name="action" value="nilai">
                    <input type="hidden" name="id_tugas" value="<?php echo $id_tugas; ?>">
                    <input type="hidden" name="id_siswa" id="nilai_id_siswa">
                    
                    <div class="form-group">
                        <label>Nama Siswa:</label>
                        <div id="nilai_nama" style="font-weight: 600; color: #2d3748; padding: 10px; background: #f7fafc; border-radius: 8px;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>NIS:</label>
                        <div id="nilai_nis" style="font-weight: 600; color: #2d3748; padding: 10px; background: #f7fafc; border-radius: 8px;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal Upload:</label>
                        <div id="nilai_tanggal" style="font-weight: 600; color: #2d3748; padding: 10px; background: #f7fafc; border-radius: 8px;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>File Tugas:</label>
                        <a id="nilai_file" href="#" target="_blank" class="btn btn-success btn-sm">📥 Download File</a>
                    </div>
                    
                    <div class="form-group">
                        <label for="nilai">Nilai: <span style="color: red;">*</span></label>
                        <input type="number" name="nilai" id="nilai" step="0.01" min="0" max="100" required placeholder="0-100">
                    </div>
                    
                    <div class="form-group">
                        <label for="catatan">Catatan/Feedback:</label>
                        <textarea name="catatan" id="catatan" placeholder="Berikan catatan untuk siswa (opsional)"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                        <button type="submit" class="btn btn-success">💾 Simpan Nilai</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function beriNilai(data) {
            document.getElementById('nilai_id_siswa').value = data.id_siswa;
            document.getElementById('nilai_nama').textContent = data.nama;
            document.getElementById('nilai_nis').textContent = data.nis;
            document.getElementById('nilai_tanggal').textContent = new Date(data.tanggal_upload).toLocaleString('id-ID');
            document.getElementById('nilai_file').href = data.file_path;
            
            if (data.nilai) {
                document.getElementById('nilai').value = data.nilai.nilai;
                document.getElementById('catatan').value = data.nilai.catatan || '';
            } else {
                document.getElementById('nilai').value = '';
                document.getElementById('catatan').value = '';
            }
            
            document.getElementById('nilaiModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('nilaiModal').classList.remove('active');
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('nilaiModal');
            if (event.target === modal) closeModal();
        }
    </script>
</body>
</html>