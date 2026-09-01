<?php
$page_title = 'Pengguna';
$page_subtitle = 'Manajemen akun pengguna';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $username = sanitize($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $nama = sanitize($_POST['nama_lengkap']);
        $role = sanitize($_POST['role']);
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $password, $nama, $role);
        $ok = $stmt->execute();
        $msg = $ok ? 'Pengguna berhasil ditambahkan!' : 'Gagal: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('users.php');
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $nama = sanitize($_POST['nama_lengkap']);
        $role = sanitize($_POST['role']);
        $status = sanitize($_POST['status']);
        
        $sql = "UPDATE users SET nama_lengkap=?, role=?, status=?";
        $params = [$nama, $role, $status];
        $types = "sss";
        
        if (!empty($_POST['password'])) {
            $sql .= ", password=?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $types .= "s";
        }
        
        $sql .= " WHERE id=?";
        $params[] = $id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $ok = $stmt->execute();
        $msg = $ok ? 'Pengguna berhasil diupdate!' : 'Gagal mengupdate: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('users.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        if ($id != $_SESSION['user_id']) {
            $conn->query("DELETE FROM users WHERE id = $id");
            $msg = 'Pengguna berhasil dihapus!';
            flash('success', $msg);
        } else {
            $ok = false;
            $msg = 'Tidak bisa menghapus akun sendiri!';
            flash('danger', $msg);
        }
        if (isAjax()) jsonOut(['success' => $ok ?? true, 'message' => $msg]);
        redirect('users.php');
    }
}

$search = sanitize($_GET['search'] ?? '');
$where_users = $search ? "WHERE username LIKE '%$search%' OR nama_lengkap LIKE '%$search%'" : '';
$users = $conn->query("SELECT * FROM users $where_users ORDER BY role, nama_lengkap")->fetch_all(MYSQLI_ASSOC);
?>

<div id="ajax-area">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-3 align-items-center">
        <span class="text-muted">Total: <strong><?= count($users) ?></strong> pengguna</span>
        <form class="d-flex gap-2 ajax-filter" method="GET">
            <input type="text" class="form-control" name="search" placeholder="Cari username / nama..." value="<?= htmlspecialchars($search) ?>" style="width:220px;">
        </form>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>Tambah Pengguna
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Username</th>
                        <th>Nama Lengkap</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                        <td>
                            <?php
                            $roleClass = match($u['role']) {
                                'admin' => 'badge-danger',
                                'guru_piket' => 'badge-warning',
                                default => 'badge-info'
                            };
                            ?>
                            <span class="badge <?= $roleClass ?>"><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></span>
                        </td>
                        <td>
                            <span class="badge <?= $u['status'] === 'aktif' ? 'badge-success' : 'badge-danger' ?>">
                                <?= ucfirst($u['status']) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= formatTanggal($u['created_at']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" class="d-inline ajax-form" data-confirm="Hapus pengguna ini?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php foreach ($users as $u): ?>
<!-- Edit Modal -->
<div class="modal fade" id="editModal<?= $u['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="nama_lengkap" value="<?= htmlspecialchars($u['nama_lengkap']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password Baru (kosongkan jika tidak diubah)</label>
                        <input type="password" class="form-control" name="password">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role">
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="guru_piket" <?= $u['role'] === 'guru_piket' ? 'selected' : '' ?>>Guru Piket</option>
                                <option value="guru" <?= $u['role'] === 'guru' ? 'selected' : '' ?>>Guru</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="aktif" <?= $u['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $u['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                        </div>
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
                    <h5 class="modal-title">Tambah Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role">
                            <option value="guru">Guru</option>
                            <option value="guru_piket">Guru Piket</option>
                            <option value="admin">Admin</option>
                        </select>
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
