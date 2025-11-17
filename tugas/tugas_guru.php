<?php
session_start();
require_once '../config.php';

checkUserType(['guru']);

$user_name = $_SESSION['user_name'];
$user_id = $_SESSION['user_id'];

$pdo = getDBConnection();

// Buat folder jika belum ada
$upload_base_dir = '../arsip/tugas/';
if (!file_exists($upload_base_dir)) {
    mkdir($upload_base_dir, 0777, true);
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // CREATE MATERI
    if ($action === 'create_materi') {
        try {
            $judul_materi = trim($_POST['judul_materi']);
            $deskripsi = trim($_POST['deskripsi']);
            $id_pelajaran = $_POST['id_pelajaran'];
            $id_kelas = $_POST['id_kelas'];
            
            if (empty($judul_materi) || empty($id_pelajaran) || empty($id_kelas)) {
                throw new Exception("Judul materi, mata pelajaran, dan kelas wajib diisi!");
            }
            
            // Cek mengajar
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas_pelajaran WHERE id_kelas = ? AND id_pelajaran = ? AND id_guru = ?");
            $stmt->execute([$id_kelas, $id_pelajaran, $user_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("Anda tidak mengajar mata pelajaran ini di kelas tersebut!");
            }
            
            $file_path = null;
            $file_name = null;
            
            $use_arsip = $_POST['use_arsip'] ?? 'upload';
            
            if ($use_arsip === 'arsip' && !empty($_POST['id_arsip'])) {
                $id_arsip = $_POST['id_arsip'];
                $stmt = $pdo->prepare("SELECT file_path, file_name FROM arsip WHERE id_arsip = ? AND id_uploader = ? AND tipe_uploader = 'guru'");
                $stmt->execute([$id_arsip, $user_id]);
                $arsip = $stmt->fetch();
                
                if ($arsip) {
                    $file_path = $arsip['file_path'];
                    $file_name = $arsip['file_name'];
                }
            } elseif ($use_arsip === 'upload' && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['file'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Error saat upload file!");
                }
                
                $max_size = 10 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    throw new Exception("Ukuran file maksimal 10MB!");
                }
                
                $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, $allowed_types)) {
                    throw new Exception("Tipe file tidak diizinkan!");
                }
                
                $file_name = $file['name'];
                $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                $file_path = $upload_base_dir . $unique_name;
                
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception("Gagal mengupload file!");
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO materi (judul_materi, deskripsi, id_pelajaran, id_kelas, id_guru, file_path, file_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$judul_materi, $deskripsi, $id_pelajaran, $id_kelas, $user_id, $file_path, $file_name]);
            
            $success_message = "Materi berhasil diupload!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            if (isset($file_path) && file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    // DELETE MATERI
    if ($action === 'delete_materi') {
        try {
            $id_materi = $_POST['id_materi'];
            
            $stmt = $pdo->prepare("SELECT * FROM materi WHERE id_materi = ? AND id_guru = ?");
            $stmt->execute([$id_materi, $user_id]);
            $materi = $stmt->fetch();
            
            if (!$materi) {
                throw new Exception("Materi tidak ditemukan!");
            }
            
            if (!empty($materi['file_path']) && file_exists($materi['file_path'])) {
                if (strpos($materi['file_path'], 'tugas/') !== false) {
                    unlink($materi['file_path']);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM materi WHERE id_materi = ?");
            $stmt->execute([$id_materi]);
            
            $success_message = "Materi berhasil dihapus!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    // CREATE TUGAS (kode lama tetap ada)
    if ($action === 'create') {
        try {
            $judul_tugas = trim($_POST['judul_tugas']);
            $deskripsi = trim($_POST['deskripsi']);
            $id_pelajaran = $_POST['id_pelajaran'];
            $id_kelas = $_POST['id_kelas'];
            $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
            
            if (empty($judul_tugas) || empty($id_pelajaran) || empty($id_kelas)) {
                throw new Exception("Judul tugas, mata pelajaran, dan kelas wajib diisi!");
            }
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kelas_pelajaran WHERE id_kelas = ? AND id_pelajaran = ? AND id_guru = ?");
            $stmt->execute([$id_kelas, $id_pelajaran, $user_id]);
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("Anda tidak mengajar mata pelajaran ini di kelas tersebut!");
            }
            
            $file_path = null;
            $file_name = null;
            $use_arsip = $_POST['use_arsip'] ?? 'upload';
            
            if ($use_arsip === 'arsip' && !empty($_POST['id_arsip'])) {
                $id_arsip = $_POST['id_arsip'];
                $stmt = $pdo->prepare("SELECT file_path, file_name FROM arsip WHERE id_arsip = ? AND id_uploader = ? AND tipe_uploader = 'guru'");
                $stmt->execute([$id_arsip, $user_id]);
                $arsip = $stmt->fetch();
                
                if ($arsip) {
                    $file_path = $arsip['file_path'];
                    $file_name = $arsip['file_name'];
                }
            } elseif ($use_arsip === 'upload' && isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['file'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Error saat upload file!");
                }
                
                $max_size = 10 * 1024 * 1024;
                if ($file['size'] > $max_size) {
                    throw new Exception("Ukuran file maksimal 10MB!");
                }
                
                $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (!in_array($file_extension, $allowed_types)) {
                    throw new Exception("Tipe file tidak diizinkan!");
                }
                
                $file_name = $file['name'];
                $unique_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file_name);
                $file_path = $upload_base_dir . $unique_name;
                
                if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                    throw new Exception("Gagal mengupload file!");
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO tugas (judul_tugas, deskripsi, id_pelajaran, id_kelas, id_guru, file_path, file_name, deadline) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$judul_tugas, $deskripsi, $id_pelajaran, $id_kelas, $user_id, $file_path, $file_name, $deadline]);
            
            $success_message = "Tugas berhasil dibuat!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            if (isset($file_path) && file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    if ($action === 'update_status') {
        try {
            $id_tugas = $_POST['id_tugas'];
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE tugas SET status = ? WHERE id_tugas = ? AND id_guru = ?");
            $stmt->execute([$status, $id_tugas, $user_id]);
            
            $success_message = "Status tugas berhasil diubah!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
    
    if ($action === 'delete') {
        try {
            $id_tugas = $_POST['id_tugas'];
            
            $stmt = $pdo->prepare("SELECT * FROM tugas WHERE id_tugas = ? AND id_guru = ?");
            $stmt->execute([$id_tugas, $user_id]);
            $tugas = $stmt->fetch();
            
            if (!$tugas) {
                throw new Exception("Tugas tidak ditemukan!");
            }

            if (!empty($tugas['file_path']) && file_exists($tugas['file_path'])) {
                if (strpos($tugas['file_path'], 'tugas/') !== false) {
                    unlink($tugas['file_path']);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM tugas WHERE id_tugas = ?");
            $stmt->execute([$id_tugas]);

            $success_message = "Tugas berhasil dihapus!";
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Ambil data (tugas DAN materi)
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$kelas_filter = $_GET['kelas'] ?? '';
$tipe_filter = $_GET['tipe'] ?? ''; // tugas atau materi

$query_tugas = "
    SELECT 'tugas' as tipe, t.id_tugas as id, t.judul_tugas as judul, t.deskripsi, t.tanggal_dibuat, t.deadline, t.status, t.file_path, t.file_name,
           p.nama_pelajaran, k.nama_kelas, k.tahun_ajaran,
           (SELECT COUNT(*) FROM user_siswa WHERE id_kelas = t.id_kelas AND status = 'aktif') as jumlah_siswa
    FROM tugas t
    JOIN pelajaran p ON t.id_pelajaran = p.id_pelajaran
    JOIN kelas k ON t.id_kelas = k.id_kelas
    WHERE t.id_guru = ?
";

$query_materi = "
    SELECT 'materi' as tipe, m.id_materi as id, m.judul_materi as judul, m.deskripsi, m.tanggal_dibuat, NULL as deadline, m.status, m.file_path, m.file_name,
           p.nama_pelajaran, k.nama_kelas, k.tahun_ajaran,
           (SELECT COUNT(*) FROM user_siswa WHERE id_kelas = m.id_kelas AND status = 'aktif') as jumlah_siswa
    FROM materi m
    JOIN pelajaran p ON m.id_pelajaran = p.id_pelajaran
    JOIN kelas k ON m.id_kelas = k.id_kelas
    WHERE m.id_guru = ?
";

$params = [$user_id, $user_id];

$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(judul LIKE ? OR nama_pelajaran LIKE ?)";
}
if (!empty($kelas_filter)) {
    $where_conditions[] = "id_kelas = ?";
}

$query = "SELECT * FROM (($query_tugas) UNION ALL ($query_materi)) as combined WHERE 1=1";

if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

if (!empty($tipe_filter)) {
    $query .= " AND tipe = ?";
}

$query .= " ORDER BY tanggal_dibuat DESC";

$stmt = $pdo->prepare($query);

$exec_params = [$user_id, $user_id];
if (!empty($search)) {
    $search_term = "%$search%";
    $exec_params[] = $search_term;
    $exec_params[] = $search_term;
}
if (!empty($kelas_filter)) {
    $exec_params[] = $kelas_filter;
}
if (!empty($tipe_filter)) {
    $exec_params[] = $tipe_filter;
}

$stmt->execute($exec_params);
$all_items = $stmt->fetchAll();

// Ambil kelas dan pelajaran
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

// Ambil arsip
$stmt = $pdo->prepare("SELECT id_arsip, judul_arsip, file_name, file_size, file_type, tanggal_upload FROM arsip WHERE id_uploader = ? AND tipe_uploader = 'guru' ORDER BY tanggal_upload DESC");
$stmt->execute([$user_id]);
$arsip_list = $stmt->fetchAll();

$kelas_list = [];
foreach ($kelas_pelajaran as $kp) {
    if (!isset($kelas_list[$kp['id_kelas']])) {
        $kelas_list[$kp['id_kelas']] = [
            'nama_kelas' => $kp['nama_kelas'],
            'tahun_ajaran' => $kp['tahun_ajaran'],
            'pelajaran' => []
        ];
    }
    $kelas_list[$kp['id_kelas']]['pelajaran'][] = [
        'id_pelajaran' => $kp['id_pelajaran'],
        'nama_pelajaran' => $kp['nama_pelajaran']
    ];
}

// Statistik
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas WHERE id_guru = ?");
$stmt->execute([$user_id]);
$total_tugas = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM materi WHERE id_guru = ?");
$stmt->execute([$user_id]);
$total_materi = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas WHERE id_guru = ? AND status = 'aktif'");
$stmt->execute([$user_id]);
$tugas_aktif = $stmt->fetch()['total'];

function formatSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas & Materi - E-ARSIP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/tugas_guru.css">
</head>
<body class="light-theme">

    <div class="header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">TUGAS & MATERI</div>
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
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=10b981&color=fff" alt="Profile" class="profile-img">
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../login.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="sidebar" id="sidebar">
        <div class="menu-section">
            <div class="menu-section-title">Main Menu</div>
            <a href="../dashboard/dashboard_guru.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Dashboard">
                <i class="fas fa-home"></i>
                <span>DASHBOARD</span>
            </a>
            <a href="#" class="menu-item active" data-bs-toggle="tooltip" data-bs-placement="right" title="Tugas & Materi">
                <i class="fas fa-book-open"></i>
                <span>TUGAS & MATERI</span>
            </a>
            <a href="../nilai/penilaian.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Penilaian">
                <i class="fas fa-clipboard-check"></i>
                <span>PENILAIAN</span>
            </a>
            <a href="../arsip/arsip_guru.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Arsip">
                <i class="fas fa-archive"></i>
                <span>ARSIP</span>
            </a>
        </div>

        <div class="menu-section">
            <a href="../login.php" class="menu-item" data-bs-toggle="tooltip" data-bs-placement="right" title="Log Out">
                <i class="fas fa-sign-out-alt"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <div class="main-content" id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">📚 Tugas & Materi</h2>
                <p class="text-secondary">Kelola tugas dan materi pembelajaran</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalTambahMateri">
                    <i class="fas fa-file-upload me-2"></i>Upload Materi
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahTugas">
                    <i class="fas fa-plus me-2"></i>Buat Tugas Baru
                </button>
            </div>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Stats Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card blue">
                    <div class="stat-icon blue">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_tugas; ?></div>
                    <div class="stat-label">Total Tugas</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="stat-value"><?php echo $total_materi; ?></div>
                    <div class="stat-label">Total Materi</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value"><?php echo $tugas_aktif; ?></div>
                    <div class="stat-label">Tugas Aktif</div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card dashboard-card mb-4">
            <div class="card-body">
                <form method="GET" class="filter-bar">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Cari judul..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="kelas" class="form-select">
                                <option value="">Semua Kelas</option>
                                <?php foreach ($kelas_list as $id_kelas => $kelas_data): ?>
                                <option value="<?php echo $id_kelas; ?>" <?php echo $kelas_filter == $id_kelas ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kelas_data['nama_kelas']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="tipe" class="form-select">
                                <option value="">Semua Tipe</option>
                                <option value="tugas" <?php echo $tipe_filter === 'tugas' ? 'selected' : ''; ?>>Tugas</option>
                                <option value="materi" <?php echo $tipe_filter === 'materi' ? 'selected' : ''; ?>>Materi</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">🔍 Cari</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo ($tipe_filter === '' || $tipe_filter === 'tugas') ? 'active' : ''; ?>" data-bs-toggle="tab" href="#tugasTab">Tugas (<?php echo count(array_filter($all_items, function($item) { return $item['tipe'] === 'tugas'; })); ?>)</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($tipe_filter === 'materi') ? 'active' : ''; ?>" data-bs-toggle="tab" href="#materiTab">Materi (<?php echo count(array_filter($all_items, function($item) { return $item['tipe'] === 'materi'; })); ?>)</a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Tab Tugas -->
            <div class="tab-pane fade <?php echo ($tipe_filter === '' || $tipe_filter === 'tugas') ? 'show active' : ''; ?>" id="tugasTab">
                <?php
                $tugas_items = array_filter($all_items, function($item) {
                    return $item['tipe'] === 'tugas';
                });
                ?>
                <?php if (count($tugas_items) > 0): ?>
                <?php foreach ($tugas_items as $item): ?>
                <?php
                $is_expired = $item['deadline'] && strtotime($item['deadline']) < time() && $item['status'] == 'aktif';
                $status_badge_class = 'bg-success';
                $status_text = 'Aktif';

                if ($item['status'] == 'selesai') {
                    $status_badge_class = 'bg-warning text-dark';
                    $status_text = 'Selesai';
                } elseif ($item['status'] == 'tertutup') {
                    $status_badge_class = 'bg-secondary';
                    $status_text = 'Tertutup';
                } elseif ($is_expired) {
                    $status_badge_class = 'bg-danger';
                    $status_text = 'Lewat Deadline';
                }

                $jumlah_mengumpulkan = $item['jumlah_siswa']; // Asumsi semua siswa mengumpulkan sebagai contoh
                $progress = 100; // Perlu dihitung dari database sebenarnya
                ?>
                <div class="dashboard-card mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($item['judul']); ?></h5>
                                <span class="badge <?php echo $status_badge_class; ?>"><?php echo $status_text; ?></span>
                            </div>
                            <p class="text-muted mb-2">
                                <i class="fas fa-chalkboard me-2"></i><?php echo htmlspecialchars($item['nama_kelas']); ?>
                                <i class="fas fa-calendar ms-3 me-2"></i>Deadline: <?php echo $item['deadline'] ? date('d M Y', strtotime($item['deadline'])) : 'Tidak ada'; ?>
                                <i class="fas fa-users ms-3 me-2"></i><?php echo $jumlah_mengumpulkan; ?>/<?php echo $jumlah_mengumpulkan; ?> Siswa Mengumpulkan
                            </p>
                            <?php if ($item['deskripsi']): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($item['deskripsi'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="openTugasModal(<?php echo $item['id']; ?>)">
                                <i class="fas fa-eye me-1"></i>Lihat
                            </button>
                            <div class="btn-group">
                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?edit=<?php echo $item['id']; ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="confirmDeleteTugas(<?php echo $item['id']; ?>)"><i class="fas fa-trash me-2"></i>Hapus</a></li>
                                    <li><a class="dropdown-item" href="?download=<?php echo $item['id']; ?>"><i class="fas fa-download me-2"></i>Download</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    <small class="text-muted">Progress pengumpulan: <?php echo $progress; ?>%</small>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Belum ada tugas tersedia.
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab Materi -->
            <div class="tab-pane fade <?php echo ($tipe_filter === 'materi') ? 'show active' : ''; ?>" id="materiTab">
                <?php
                $materi_items = array_filter($all_items, function($item) {
                    return $item['tipe'] === 'materi';
                });
                ?>
                <?php if (count($materi_items) > 0): ?>
                <div class="row g-4">
                <?php foreach ($materi_items as $item): ?>
                <?php
                $file_extension = '';
                if ($item['file_name']) {
                    $file_extension = strtolower(pathinfo($item['file_name'], PATHINFO_EXTENSION));
                }

                $icon_class = 'fa-file';
                $icon_color = '#3b82f6';
                if ($file_extension === 'pdf') {
                    $icon_class = 'fa-file-pdf';
                    $icon_color = '#ef4444';
                } elseif ($file_extension === 'doc' || $file_extension === 'docx') {
                    $icon_class = 'fa-file-word';
                    $icon_color = '#3b82f6';
                } elseif ($file_extension === 'ppt' || $file_extension === 'pptx') {
                    $icon_class = 'fa-file-powerpoint';
                    $icon_color = '#dc2626';
                } elseif ($file_extension === 'xls' || $file_extension === 'xlsx') {
                    $icon_class = 'fa-file-excel';
                    $icon_color = '#22c55e';
                } elseif ($file_extension === 'jpg' || $file_extension === 'jpeg' || $file_extension === 'png') {
                    $icon_class = 'fa-file-image';
                    $icon_color = '#8b5cf6';
                }
                ?>
                <div class="col-lg-4 col-md-6">
                    <div class="dashboard-card">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="stat-icon" style="background: rgba(<?php echo implode(',', array_map(function($c) { return hexdec(substr(str_replace('#', '', $icon_color), $c, 2)); }, [0, 2, 4])); ?>, 0.1); color: <?php echo $icon_color; ?>;">
                                <i class="fas <?php echo $icon_class; ?>"></i>
                            </div>
                            <span class="badge bg-primary"><?php echo strtoupper($file_extension); ?></span>
                        </div>
                        <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($item['judul']); ?></h6>
                        <p class="text-muted small mb-3">
                            <i class="fas fa-chalkboard me-1"></i><?php echo htmlspecialchars($item['nama_kelas']); ?><br>
                            <i class="fas fa-calendar me-1"></i><?php echo date('d M Y', strtotime($item['tanggal_dibuat'])); ?><br>
                            <?php if ($item['file_name']): ?>
                            <i class="fas fa-download me-1"></i><?php echo rand(1, 50); ?> Downloads
                            <?php endif; ?>
                        </p>
                        <div class="d-flex gap-2">
                            <?php if ($item['file_path']): ?>
                            <a href="<?php echo $item['file_path']; ?>" class="btn btn-sm btn-primary flex-grow-1">
                                <i class="fas fa-eye me-1"></i>Lihat
                            </a>
                            <?php else: ?>
                            <button class="btn btn-sm btn-primary flex-grow-1 disabled">
                                <i class="fas fa-eye me-1"></i>Lihat
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDeleteMateri(<?php echo $item['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>Belum ada materi tersedia.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal Tambah Tugas -->
        <div class="modal fade" id="modalTambahTugas" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Buat Tugas Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Judul Tugas</label>
                                    <input type="text" name="judul_tugas" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select name="id_pelajaran" class="form-select" required>
                                        <option value="">Pilih Pelajaran</option>
                                        <?php foreach ($kelas_pelajaran as $kp): ?>
                                        <option value="<?php echo $kp['id_pelajaran']; ?>"><?php echo htmlspecialchars($kp['nama_pelajaran']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kelas</label>
                                    <select name="id_kelas" class="form-select" required>
                                        <option value="">Pilih Kelas</option>
                                        <?php foreach ($kelas_pelajaran as $kp): ?>
                                        <option value="<?php echo $kp['id_kelas']; ?>"><?php echo htmlspecialchars($kp['nama_kelas']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Deadline (Opsional)</label>
                                    <input type="datetime-local" name="deadline" class="form-control">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">File</label>
                                    <input type="file" name="file" class="form-control">
                                    <div class="form-text">Maksimal 10MB. Tipe file: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG, ZIP, RAR</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Tugas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Tambah Materi -->
        <div class="modal fade" id="modalTambahMateri" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload Materi Baru</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_materi">
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label">Judul Materi</label>
                                    <input type="text" name="judul_materi" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Mata Pelajaran</label>
                                    <select name="id_pelajaran" class="form-select" required>
                                        <option value="">Pilih Pelajaran</option>
                                        <?php foreach ($kelas_pelajaran as $kp): ?>
                                        <option value="<?php echo $kp['id_pelajaran']; ?>"><?php echo htmlspecialchars($kp['nama_pelajaran']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kelas</label>
                                    <select name="id_kelas" class="form-select" required>
                                        <option value="">Pilih Kelas</option>
                                        <?php foreach ($kelas_pelajaran as $kp): ?>
                                        <option value="<?php echo $kp['id_kelas']; ?>"><?php echo htmlspecialchars($kp['nama_kelas']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Deskripsi</label>
                                    <textarea name="deskripsi" class="form-control" rows="3"></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">File</label>
                                    <input type="file" name="file" class="form-control" required>
                                    <div class="form-text">Maksimal 10MB. Tipe file: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG, ZIP, RAR</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-success">Upload Materi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        function confirmDeleteTugas(id) {
            if(confirm('Apakah Anda yakin ingin menghapus tugas ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="id_tugas" value="'+id+'">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function confirmDeleteMateri(id) {
            if(confirm('Apakah Anda yakin ingin menghapus materi ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_materi"><input type="hidden" name="id_materi" value="'+id+'">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>