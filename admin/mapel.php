<?php
$page_title = 'Mata Pelajaran';
$page_subtitle = 'Manajemen mata pelajaran';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $kode = sanitize($_POST['kode_mapel']);
        $nama = sanitize($_POST['nama_mapel']);
        $kategori = sanitize($_POST['kategori']);
        $jam = intval($_POST['jam_per_minggu']);
        
        $stmt = $conn->prepare("INSERT INTO mata_pelajaran (kode_mapel, nama_mapel, kategori, jam_per_minggu) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $kode, $nama, $kategori, $jam);
        $ok = $stmt->execute();
        $msg = $ok ? 'Mata pelajaran berhasil ditambahkan!' : 'Gagal: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('mapel.php');
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $kode = sanitize($_POST['kode_mapel']);
        $nama = sanitize($_POST['nama_mapel']);
        $kategori = sanitize($_POST['kategori']);
        $jam = intval($_POST['jam_per_minggu']);
        $status = sanitize($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE mata_pelajaran SET kode_mapel=?, nama_mapel=?, kategori=?, jam_per_minggu=?, status=? WHERE id=?");
        $stmt->bind_param("sssisi", $kode, $nama, $kategori, $jam, $status, $id);
        $ok = $stmt->execute();
        $msg = $ok ? 'Mata pelajaran berhasil diupdate!' : 'Gagal mengupdate: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('mapel.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM mata_pelajaran WHERE id = $id");
        $msg = 'Mata pelajaran berhasil dihapus!';
        flash('success', $msg);
        if (isAjax()) jsonOut(['success' => true, 'message' => $msg]);
        redirect('mapel.php');
    }
}

$search = sanitize($_GET['search'] ?? '');
$where_mapel = $search ? "WHERE (kode_mapel LIKE '%$search%' OR nama_mapel LIKE '%$search%')" : '';
$mapel_list = $conn->query("SELECT * FROM mata_pelajaran $where_mapel ORDER BY kode_mapel ASC")->fetch_all(MYSQLI_ASSOC);
?>

<div id="ajax-area">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-3 align-items-center">
        <span class="text-muted">Total: <strong><?= count($mapel_list) ?></strong> mata pelajaran</span>
        <form class="d-flex gap-2 ajax-filter" method="GET">
            <input type="text" class="form-control" name="search" placeholder="Cari kode / nama mapel..." value="<?= htmlspecialchars($search) ?>" style="width:240px;">
        </form>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>Tambah Mapel
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>Kategori</th>
                        <th>Jam/Minggu</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mapel_list as $i => $m): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($m['kode_mapel']) ?></span></td>
                        <td><strong><?= htmlspecialchars($m['nama_mapel']) ?></strong></td>
                        <td>
                            <?php
                            $katClass = match($m['kategori']) {
                                'jurusan' => 'badge-secondary',
                                'ekstrakurikuler' => 'badge-warning',
                                default => 'badge-info'
                            };
                            ?>
                            <span class="badge <?= $katClass ?>"><?= ucfirst($m['kategori']) ?></span>
                        </td>
                        <td><?= $m['jam_per_minggu'] ?> jam</td>
                        <td>
                            <span class="badge <?= $m['status'] === 'aktif' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst($m['status']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $m['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" class="d-inline ajax-form" data-confirm="Hapus mata pelajaran ini?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>        </div>
    </div>
</div>

<?php foreach ($mapel_list as $m): ?>
<!-- Edit Modal -->
<div class="modal fade" id="editModal<?= $m['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Mapel</label>
                        <input type="text" class="form-control" name="kode_mapel" value="<?= htmlspecialchars($m['kode_mapel']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Mapel</label>
                        <input type="text" class="form-control" name="nama_mapel" value="<?= htmlspecialchars($m['nama_mapel']) ?>" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori</label>
                            <select class="form-select" name="kategori">
                                <option value="umum" <?= $m['kategori'] === 'umum' ? 'selected' : '' ?>>Umum</option>
                                <option value="jurusan" <?= $m['kategori'] === 'jurusan' ? 'selected' : '' ?>>Jurusan</option>
                                <option value="ekstrakurikuler" <?= $m['kategori'] === 'ekstrakurikuler' ? 'selected' : '' ?>>Ekstrakurikuler</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam/Minggu</label>
                            <input type="number" class="form-control" name="jam_per_minggu" value="<?= $m['jam_per_minggu'] ?>" min="1" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="aktif" <?= $m['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="nonaktif" <?= $m['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
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
                    <h5 class="modal-title">Tambah Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Mapel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_mapel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Mapel <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mapel" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori</label>
                            <select class="form-select" name="kategori">
                                <option value="umum">Umum</option>
                                <option value="jurusan">Jurusan</option>
                                <option value="ekstrakurikuler">Ekstrakurikuler</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam/Minggu</label>
                            <input type="number" class="form-control" name="jam_per_minggu" value="1" min="1" required>
                        </div>
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
