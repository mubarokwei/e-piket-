<?php
$page_title = 'Kelola Guru';
$page_subtitle = 'Manajemen data guru';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

// Handle CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nip = sanitize($_POST['nip']);
        $nama = sanitize($_POST['nama_guru']);
        $jk = sanitize($_POST['jenis_kelamin']);
        $hp = sanitize($_POST['no_hp']);
        $email = sanitize($_POST['email']);
        $mapel = sanitize($_POST['mata_pelajaran']);
        $alamat = sanitize($_POST['alamat']);
        
        $stmt = $conn->prepare("INSERT INTO guru (nip, nama_guru, jenis_kelamin, no_hp, email, mata_pelajaran, alamat) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $nip, $nama, $jk, $hp, $email, $mapel, $alamat);
        
        $ok = $stmt->execute();
        $msg = $ok ? 'Guru berhasil ditambahkan!' : 'Gagal menambahkan guru: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('guru.php');
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $nip = sanitize($_POST['nip']);
        $nama = sanitize($_POST['nama_guru']);
        $jk = sanitize($_POST['jenis_kelamin']);
        $hp = sanitize($_POST['no_hp']);
        $email = sanitize($_POST['email']);
        $mapel = sanitize($_POST['mata_pelajaran']);
        $alamat = sanitize($_POST['alamat']);
        $status = sanitize($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE guru SET nip=?, nama_guru=?, jenis_kelamin=?, no_hp=?, email=?, mata_pelajaran=?, alamat=?, status=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $nip, $nama, $jk, $hp, $email, $mapel, $alamat, $status, $id);
        
        $ok = $stmt->execute();
        $msg = $ok ? 'Data guru berhasil diupdate!' : 'Gagal mengupdate guru: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('guru.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM guru WHERE id = $id");
        $msg = 'Guru berhasil dihapus!';
        flash('success', $msg);
        if (isAjax()) jsonOut(['success' => true, 'message' => $msg]);
        redirect('guru.php');
    }
}

// Ambil data guru
$search = sanitize($_GET['search'] ?? '');
$where = $search ? "WHERE (g.nama_guru LIKE '%$search%' OR g.nip LIKE '%$search%' OR g.mata_pelajaran LIKE '%$search%')" : "WHERE g.status = 'aktif'";

$guru_list = $conn->query("
    SELECT g.* 
    FROM guru g 
    $where
    ORDER BY g.nama_guru ASC
")->fetch_all(MYSQLI_ASSOC);
?>

<div id="ajax-area">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <form class="d-flex gap-2 ajax-filter" method="GET">
            <input type="text" class="form-control" name="search" placeholder="Cari guru..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Cari</button>
        </form>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>Tambah Guru
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIP</th>
                        <th>Nama Guru</th>
                        <th>Jenis Kelamin</th>
                        <th>Mata Pelajaran</th>
                        <th>No. HP</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($guru_list)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Tidak ada data guru</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($guru_list as $i => $g): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td class="text-muted"><?= htmlspecialchars($g['nip']) ?></td>
                        <td><strong><?= htmlspecialchars($g['nama_guru']) ?></strong></td>
                        <td>
                            <span class="badge <?= $g['jenis_kelamin'] === 'L' ? 'badge-info' : 'badge-secondary' ?>">
                                <?= $g['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?>
                            </span>
                        </td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($g['mata_pelajaran'] ?? '-') ?></span></td>
                        <td class="text-muted"><?= htmlspecialchars($g['no_hp'] ?? '-') ?></td>
                        <td class="text-muted"><?= htmlspecialchars($g['email'] ?? '-') ?></td>
                        <td>
                            <span class="badge <?= $g['status'] === 'aktif' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst($g['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $g['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline ajax-form" data-confirm="Yakin ingin menghapus guru ini?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($guru_list as $g): ?>
<!-- Edit Modal -->
<div class="modal fade" id="editModal<?= $g['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $g['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Guru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">NIP</label>
                        <input type="text" class="form-control" name="nip" value="<?= htmlspecialchars($g['nip']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Guru</label>
                        <input type="text" class="form-control" name="nama_guru" value="<?= htmlspecialchars($g['nama_guru']) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Kelamin</label>
                            <select class="form-select" name="jenis_kelamin" required>
                                <option value="L" <?= $g['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="P" <?= $g['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="aktif" <?= $g['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $g['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No. HP</label>
                            <input type="text" class="form-control" name="no_hp" value="<?= htmlspecialchars($g['no_hp'] ?? '') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($g['email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mata Pelajaran</label>
                        <input type="text" class="form-control" name="mata_pelajaran" value="<?= htmlspecialchars($g['mata_pelajaran'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" rows="2"><?= htmlspecialchars($g['alamat'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div><!-- /ajax-area -->

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Guru Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">NIP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nip" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Guru <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_guru" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select class="form-select" name="jenis_kelamin" required>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">No. HP</label>
                            <input type="text" class="form-control" name="no_hp">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="mata_pelajaran" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alamat</label>
                        <textarea class="form-control" name="alamat" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
