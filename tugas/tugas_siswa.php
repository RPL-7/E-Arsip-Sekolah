<?php
session_start();
require_once '../config.php';

checkUserType(['siswa']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];
$pdo = getDBConnection();

// PROSES UPLOAD TUGAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload') {
        try {
            $id_tugas = $_POST['id_tugas'];

            // Ambil data siswa
            $stmt = $pdo->prepare("SELECT id_kelas, nama_siswa, nis FROM user_siswa WHERE id_siswa = ?");
            $stmt->execute([$user_id]);
            $siswa = $stmt->fetch();

            // Cek tugas
            $stmt = $pdo->prepare("SELECT * FROM tugas WHERE id_tugas = ? AND id_kelas = ?");
            $stmt->execute([$id_tugas, $siswa['id_kelas']]);
            $tugas = $stmt->fetch();

            if (!$tugas) throw new Exception('Tugas tidak ditemukan');

            // Cek apakah sudah upload
            $file_jawaban = json_decode($tugas['file_jawaban'] ?? '[]', true);
            foreach ($file_jawaban as $fw) {
                if ($fw['id_siswa'] == $user_id) {
                    throw new Exception('Anda sudah mengumpulkan tugas ini');
                }
            }

            // Validasi file
            if (!isset($_FILES['file_tugas']) || $_FILES['file_tugas']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File tidak valid');
            }

            $file = $_FILES['file_tugas'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'docx', 'jpeg', 'jpg', 'png'];

            if (!in_array($ext, $allowed)) {
                throw new Exception('Tipe file tidak diizinkan. Hanya PDF, DOCX, JPEG, JPG, PNG');
            }

            if ($file['size'] > 10 * 1024 * 1024) {
                throw new Exception('Ukuran file maksimal 10MB');
            }

            // Upload file
            $upload_dir = "../uploads/tugas_siswa/{$siswa['nis']}_{$siswa['nama_siswa']}";
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

            $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            $file_path = $upload_dir . '/' . $unique_name;

            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                throw new Exception('Gagal mengupload file');
            }

            // Simpan ke database (JSON)
            $is_terlambat = ($tugas['deadline'] && strtotime($tugas['deadline']) < time());
            $file_jawaban[] = [
                'id_siswa' => $user_id,
                'nis' => $siswa['nis'],
                'nama' => $siswa['nama_siswa'],
                'file_path' => $file_path,
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'tanggal_upload' => date('Y-m-d H:i:s'),
                'terlambat' => $is_terlambat
            ];

            $stmt = $pdo->prepare("UPDATE tugas SET file_jawaban = ? WHERE id_tugas = ?");
            $stmt->execute([json_encode($file_jawaban), $id_tugas]);

            $_SESSION['success'] = 'Tugas berhasil dikumpulkan!' . ($is_terlambat ? ' (Terlambat)' : '');

        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: tugas_siswa.php');
        exit();
    }
}

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

    // Ambil semua tugas
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $pelajaran_filter = $_GET['pelajaran'] ?? '';

    $query = "SELECT t.*, p.nama_pelajaran, g.nama_guru FROM tugas t
              JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
              JOIN user_guru g ON t.id_guru = g.id_guru
              WHERE t.id_kelas = ?";
    $params = [$id_kelas];

    if (!empty($search)) {
        $query .= " AND (t.judul_tugas LIKE ? OR p.nama_pelajaran LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($status_filter)) {
        if ($status_filter === 'sudah_kumpul') {
            // Filter di PHP nanti
        } elseif ($status_filter === 'belum_kumpul') {
            // Filter di PHP nanti
        } else {
            $query .= " AND t.status = ?";
            $params[] = $status_filter;
        }
    }

    if (!empty($pelajaran_filter)) {
        $query .= " AND t.id_pelajaran = ?";
        $params[] = $pelajaran_filter;
    }

    $query .= " ORDER BY t.tanggal_dibuat DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_tugas = $stmt->fetchAll();

    // Proses data tugas (tambahkan info pengumpulan)
    foreach ($all_tugas as &$tugas) {
        $file_jawaban = json_decode($tugas['file_jawaban'] ?? '[]', true);
        $nilai_siswa = json_decode($tugas['nilai_siswa'] ?? '[]', true);

        $tugas['sudah_kumpul'] = false;
        $tugas['data_kumpul'] = null;
        $tugas['nilai'] = null;

        foreach ($file_jawaban as $fw) {
            if ($fw['id_siswa'] == $user_id) {
                $tugas['sudah_kumpul'] = true;
                $tugas['data_kumpul'] = $fw;
                break;
            }
        }

        foreach ($nilai_siswa as $ns) {
            if ($ns['id_siswa'] == $user_id) {
                $tugas['nilai'] = $ns;
                break;
            }
        }
    }
    unset($tugas); //Supaya PHP tidak lagi mereferensikan elemen terakhir.

    // Filter berdasarkan status pengumpulan
    if ($status_filter === 'sudah_kumpul') {
        $all_tugas = array_filter($all_tugas, fn($t) => $t['sudah_kumpul']);
    } elseif ($status_filter === 'belum_kumpul') {
        $all_tugas = array_filter($all_tugas, fn($t) => !$t['sudah_kumpul'] && $t['status'] === 'aktif');
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
    $total_aktif = 0;
    $sudah_kumpul = 0;
    $sudah_dinilai = 0;

    foreach ($all_tugas as $t) {
        if ($t['status'] === 'aktif') $total_aktif++;
        if ($t['sudah_kumpul']) $sudah_kumpul++;
        if ($t['nilai']) $sudah_dinilai++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Tugas - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/tugas_siswa.css">
</head>
<body class="light-theme">
    
    <!-- Header -->
    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">DASHBOARD SISWA</div>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
            <div class="position-relative">
                <i class="fas fa-bell" style="font-size: 1.25rem; cursor: pointer;"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem;">3</span>
            </div>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($nama_siswa); ?>&background=10b981&color=fff" alt="Profile" class="profile-img">
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="?page=profil_saya"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu-section">
            <div class="menu-section-title">Main Menu</div>
            <a href="../dashboard/dashboard_siswa.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>DASHBOARD</span>
            </a>
            <a href="#" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Daftar Tugas">
                <i class="fas fa-tasks"></i>
                <span>DAFTAR TUGAS</span>
            </a>
            <a href="../nilai/nilai_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Daftar Nilai">
                <i class="fas fa-chart-line"></i>
                <span>DAFTAR NILAI</span>
            </a>
           <a href="../arsip/arsip_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Arsip Materi">
                <i class="fas fa-folder-open"></i>
                <span>ARSIP</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-section-title">Profil</div>
            <a href="../profil/profil_siswa.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Profil Saya">
                <i class="fas fa-id-card"></i>
                <span>PROFIL SAYA</span>
            </a>
        </div>

        <div class="menu-section">
            <a href="../../logout.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">📝 Daftar Tugas</h2>
                <p class="text-secondary">Kelola semua tugas dan deadline Anda</p>
            </div>
            <div class="d-flex gap-2">
                <form method="GET" class="filter-bar">
                    <select name="status" class="form-select" style="width: auto;">
                        <option value="">Semua Status</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="sudah_kumpul" <?php echo $status_filter === 'sudah_kumpul' ? 'selected' : ''; ?>>Sudah Dikumpulkan</option>
                        <option value="belum_kumpul" <?php echo $status_filter === 'belum_kumpul' ? 'selected' : ''; ?>>Belum Dikumpulkan</option>
                    </select>
                    <select name="pelajaran" class="form-select" style="width: auto;">
                        <option value="">Semua Mata Pelajaran</option>
                        <?php foreach ($pelajaran_list as $pel): ?>
                        <option value="<?php echo $pel['id_pelajaran']; ?>" <?php echo $pelajaran_filter == $pel['id_pelajaran'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pel['nama_pelajaran']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="search" class="form-select" placeholder="Cari judul tugas..." value="<?php echo htmlspecialchars($search); ?>" style="width: auto;">
                    <button type="submit" class="btn btn-primary">🔍 Cari</button>
                    <?php if (!empty($search) || !empty($status_filter) || !empty($pelajaran_filter)): ?>
                    <a href="tugas_siswa.php" class="btn btn-secondary">Reset</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Stats Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_aktif - $sudah_kumpul; ?></div>
                    <div class="stat-label">Belum Dikumpulkan</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card blue">
                    <div class="stat-icon blue">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_aktif; ?></div>
                    <div class="stat-label">Total Tugas Aktif</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-value"><?php echo $sudah_kumpul; ?></div>
                    <div class="stat-label">Sudah Dikumpulkan</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card gree" style="border-left-color: #ef4444;">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-value"><?php echo $sudah_dinilai; ?></div>
                    <div class="stat-label">Sudah Dinilai</div>
                </div>
            </div>
        </div>

        <!-- Task List with Filter Tabs -->
        <div class="dashboard-card">
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

            <?php if ($error_no_class): ?>
            <div class="alert alert-warning">
                <strong>⚠️ Perhatian!</strong><br>
                Anda belum terdaftar di kelas manapun. Silakan hubungi admin.
            </div>
            <?php else: ?>
            
            <ul class="nav nav-tabs mb-4" id="taskTabs">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#allTasks">Semua (<?php echo count($all_tugas); ?>)</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#pendingTasks">Pending (<?php echo $total_aktif - $sudah_kumpul; ?>)</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#completedTasks">Selesai (<?php echo $sudah_kumpul; ?>)</a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="allTasks">
                    <!-- Daftar tugas lengkap seperti di dashboard -->
                    <div class="task-list">
                        <?php if (count($all_tugas) > 0): ?>
                        <?php foreach ($all_tugas as $tugas): ?>
                        <?php
                        $card_class = '';
                        $status_badge = 'badge-pending';
                        $status_text = 'Pending';

                        if ($tugas['nilai']) {
                            $status_badge = 'badge-pending';
                            $status_text = 'Sudah Dinilai';
                        } elseif ($tugas['sudah_kumpul']) {
                            $card_class = 'submitted';
                            $status_badge = 'badge-pending';
                            $status_text = 'Sudah Dikumpulkan';
                            if ($tugas['data_kumpul']['terlambat']) {
                                $status_badge = 'badge-urgent';
                                $status_text = 'Terlambat';
                                $card_class = 'urgent';
                            }
                        } elseif ($tugas['status'] !== 'aktif') {
                            $status_badge = 'badge-pending';
                            $status_text = ucfirst($tugas['status']);
                        }
                        ?>
                        <div class="task-card <?php echo $card_class; ?>">
                            <div class="task-header">
                                <div>
                                    <div class="task-title"><?php echo htmlspecialchars($tugas['judul_tugas']); ?></div>
                                    <div class="task-subject"><?php echo htmlspecialchars($tugas['nama_pelajaran']); ?></div>
                                </div>
                                <span class="task-badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                            </div>

                            <div class="task-meta">
                                <div><i class="fas fa-calendar-alt me-1"></i> Dibuat: <?php echo date('d/m/Y', strtotime($tugas['tanggal_dibuat'])); ?></div>
                                <?php if ($tugas['deadline']): ?>
                                <div><i class="fas fa-clock me-1"></i> Deadline: <?php echo date('d/m/Y', strtotime($tugas['deadline'])); ?></div>
                                <?php endif; ?>
                                <?php if ($tugas['sudah_kumpul']): ?>
                                <div style="color: #10b981; font-weight: 600;">
                                    <i class="fas fa-check-circle me-1"></i> Dikumpulkan: <?php echo date('d/m/Y H:i', strtotime($tugas['data_kumpul']['tanggal_upload'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($tugas['deskripsi']): ?>
                            <div class="task-description">
                                <?php echo nl2br(htmlspecialchars($tugas['deskripsi'])); ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($tugas['nilai']): ?>
                            <div class="nilai-display">
                                🏆 Nilai: <?php echo number_format($tugas['nilai']['nilai'], 2); ?>
                            </div>
                            <?php if ($tugas['nilai']['catatan']): ?>
                            <div style="background: #f7fafc; padding: 10px; border-radius: 8px; margin-top: 10px; font-size: 13px;">
                                💬 <strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($tugas['nilai']['catatan'])); ?>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>

                            <div class="task-footer">
                                <div class="teacher-info">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <span class="teacher-name"> <?php echo htmlspecialchars($tugas['nama_guru']); ?></span>
                                </div>
                                <div class="tugas-actions">
                                    <?php if ($tugas['file_path']): ?>
                                    <a href="<?php echo htmlspecialchars($tugas['file_path']); ?>" target="_blank" class="btn btn-success btn-sm">
                                        📥 Download Materi
                                    </a>
                                    <?php endif; ?>
                                    <?php if (!$tugas['sudah_kumpul'] && $tugas['status'] == 'aktif'): ?>
                                    <button class="task-action" onclick="uploadTugas(<?php echo $tugas['id_tugas']; ?>, '<?php echo htmlspecialchars($tugas['judul_tugas']); ?>')">
                                        Kumpulkan Tugas
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($tugas['sudah_kumpul']): ?>
                                    <a href="<?php echo htmlspecialchars($tugas['data_kumpul']['file_path']); ?>" target="_blank" class="btn btn-info btn-sm">
                                        📄 Lihat File Saya
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center p-5">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <h4 class="text-secondary">Belum Ada Tugas</h4>
                                <p class="text-muted">Belum ada tugas dari guru untuk kelas Anda</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Upload Tugas -->
    <div class="modal fade" id="uploadModal" tabindex="-1" data-bs-backdrop="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">📤 Kumpulkan Tugas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form action="tugas_siswa.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="id_tugas" id="upload_id_tugas">
                        
                        <div class="mb-3">
                            <label class="form-label">Judul Tugas:</label>
                            <div id="upload_judul_tugas" class="fw-bold text-dark"></div>
                        </div>

                        <div class="mb-3">
                            <label for="file_tugas" class="form-label">Upload File Tugas: <span class="text-danger">*</span></label>
                            <input type="file" name="file_tugas" id="file_tugas" class="form-control" required accept=".pdf,.docx,.jpeg,.jpg,.png">
                            <div class="form-text">
                                📌 Format: PDF, DOCX, JPEG, JPG, PNG (Max 10MB)
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success">📤 Kirim Tugas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function uploadTugas(idTugas, judulTugas) {
            document.getElementById('upload_id_tugas').value = idTugas;
            document.getElementById('upload_judul_tugas').textContent = judulTugas;
            document.getElementById('file_tugas').value = '';
            var myModal = new bootstrap.Modal(document.getElementById('uploadModal'));
            myModal.show();
        }

        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');
        let tooltipList = [];

        function isMobile() {
            return window.innerWidth <= 768;
        }

        if (isMobile()) {
            sidebar.classList.add('collapsed');
            mainContent.classList.add('expanded');
        }

        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            updateTooltips();
        });

        function updateTooltips() {
            tooltipList.forEach(tooltip => tooltip.dispose());
            tooltipList = [];
            
            if (sidebar.classList.contains('collapsed')) {
                const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                tooltipList = [...tooltipElements].map(el => {
                    return new bootstrap.Tooltip(el, {
                        trigger: 'hover'
                    });
                });
            }
        }

        updateTooltips();

        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-theme');
            body.classList.toggle('light-theme');
            
            const icon = themeToggle.querySelector('i');
            if (body.classList.contains('dark-theme')) {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                localStorage.setItem('theme', 'dark');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
                localStorage.setItem('theme', 'light');
            }
        });

        // Load saved theme
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.remove('light-theme');
            body.classList.add('dark-theme');
            document.querySelector('#themeToggle i').classList.replace('fa-moon', 'fa-sun');
        }

        window.addEventListener('resize', () => {
            if (isMobile() && !sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                updateTooltips();
            }
        });
    </script>
</body>
</html>