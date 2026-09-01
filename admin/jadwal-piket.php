<?php
$page_title = 'Jadwal Piket';
$page_subtitle = 'Jadwal piket guru';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $guru = intval($_POST['guru_id']);
        $tanggal = sanitize($_POST['tanggal']);
        $pagi = isset($_POST['shift_pagi']) ? 1 : 0;
        $siang = isset($_POST['shift_siang']) ? 1 : 0;
        $ket = sanitize($_POST['keterangan']);
        
        $stmt = $conn->prepare("INSERT INTO jadwal_piket (guru_id, tanggal, shift_pagi, shift_siang, keterangan) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiis", $guru, $tanggal, $pagi, $siang, $ket);
        $ok = $stmt->execute();
        $msg = $ok ? 'Jadwal piket berhasil ditambahkan!' : 'Gagal: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('jadwal-piket.php');
    }
    
    if ($action === 'update_status') {
        $id = intval($_POST['id']);
        $status = sanitize($_POST['status_hadir']);
        $pengganti = intval($_POST['guru_pengganti_id']) ?: null;
        
        $stmt = $conn->prepare("UPDATE jadwal_piket SET status_hadir=?, guru_pengganti_id=? WHERE id=?");
        $stmt->bind_param("sii", $status, $pengganti, $id);
        $ok = $stmt->execute();
        $msg = $ok ? 'Status berhasil diupdate!' : 'Gagal mengupdate: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('jadwal-piket.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM jadwal_piket WHERE id = $id");
        $msg = 'Jadwal piket berhasil dihapus!';
        flash('success', $msg);
        if (isAjax()) jsonOut(['success' => true, 'message' => $msg]);
        redirect('jadwal-piket.php');
    }
}

$tgl_filter = sanitize($_GET['tanggal'] ?? date('Y-m-d'));
$search = sanitize($_GET['search'] ?? '');
$jadwal_piket = $conn->query("
    SELECT jp.*, g.nama_guru,
    gp.nama_guru AS nama_pengganti
    FROM jadwal_piket jp
    JOIN guru g ON jp.guru_id = g.id
    LEFT JOIN guru gp ON jp.guru_pengganti_id = gp.id
    WHERE jp.tanggal = '$tgl_filter'" . ($search ? " AND g.nama_guru LIKE '%$search%'" : '') . "
    ORDER BY jp.shift_pagi DESC, g.nama_guru
")->fetch_all(MYSQLI_ASSOC);

// Data untuk minggu ini
$minggu_ini = $conn->query("
    SELECT jp.*, g.nama_guru
    FROM jadwal_piket jp
    JOIN guru g ON jp.guru_id = g.id
    WHERE jp.tanggal BETWEEN '" . date('Y-m-d', strtotime('monday this week')) . "' AND '" . date('Y-m-d', strtotime('sunday this week')) . "'
    ORDER BY jp.tanggal, jp.shift_pagi DESC
")->fetch_all(MYSQLI_ASSOC);

$guru_list = $conn->query("SELECT id, nama_guru FROM guru WHERE status='aktif' ORDER BY nama_guru")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Filter -->
<div id="ajax-area">
<div class="card mb-4">
    <div class="filter-bar">
        <form class="d-flex gap-2 align-items-center ajax-filter" method="GET">
            <label class="form-label mb-0 me-2 text-muted">Tanggal:</label>
            <input type="date" class="form-control" name="tanggal" value="<?= $tgl_filter ?>" style="width:180px;">
            <input type="text" class="form-control" name="search" placeholder="Cari guru piket..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Lihat</button>
            <a href="?tanggal=<?= date('Y-m-d') ?>" class="btn btn-outline-light">Hari Ini</a>
        </form>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i>Tambah Jadwal Piket
        </button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <!-- Jadwal Hari Ini -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-person-badge-fill me-2"></i>Jadwal Piket - <?= formatTanggal($tgl_filter) ?></h5>
                <span class="badge badge-primary"><?= count($jadwal_piket) ?> Guru</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Guru Piket</th>
                                <th>Shift</th>
                                <th>Status</th>
                                <th>Pengganti</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($jadwal_piket)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Tidak ada jadwal piket untuk tanggal ini</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($jadwal_piket as $i => $jp): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><strong><?= htmlspecialchars($jp['nama_guru']) ?></strong></td>
                                <td>
                                    <?php if ($jp['shift_pagi']): ?>
                                        <span class="badge badge-info"><i class="bi bi-sun me-1"></i>Pagi</span>
                                    <?php endif; ?>
                                    <?php if ($jp['shift_siang']): ?>
                                        <span class="badge badge-warning"><i class="bi bi-moon me-1"></i>Siang</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = match($jp['status_hadir']) {
                                        'hadir' => 'badge-success',
                                        'tidak_hadir' => 'badge-danger',
                                        'ganti' => 'badge-warning',
                                        default => 'badge-info'
                                    };
                                    ?>
                                    <span class="badge <?= $statusBadge ?>"><?= ucfirst(str_replace('_', ' ', $jp['status_hadir'])) ?></span>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($jp['nama_pengganti'] ?? '-') ?></td>
                                <td class="text-muted" style="max-width:150px;"><?= htmlspecialchars($jp['keterangan'] ?? '-') ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $jp['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline ajax-form" data-confirm="Hapus jadwal piket ini?">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
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
    </div>
    
<?php foreach ($jadwal_piket as $jp): ?>
<!-- Edit Status Modal -->
<div class="modal fade" id="editModal<?= $jp['id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?= $jp['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Update Status - <?= htmlspecialchars($jp['nama_guru']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Status Kehadiran</label>
                        <select class="form-select" name="status_hadir">
                            <option value="hadir" <?= $jp['status_hadir'] === 'hadir' ? 'selected' : '' ?>>Hadir</option>
                            <option value="tidak_hadir" <?= $jp['status_hadir'] === 'tidak_hadir' ? 'selected' : '' ?>>Tidak Hadir</option>
                            <option value="ganti" <?= $jp['status_hadir'] === 'ganti' ? 'selected' : '' ?>>Digantikan</option>
                        </select>
                    </div>
                    <div class="mb-3" id="penggantiField<?= $jp['id'] ?>">
                        <label class="form-label">Guru Pengganti</label>
                        <select class="form-select" name="guru_pengganti_id">
                            <option value="">-- Tidak Ada --</option>
                            <?php foreach ($guru_list as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= $jp['guru_pengganti_id'] == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                            <?php endforeach; ?>
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
    
    <div class="col-xl-4">
        <!-- Jadwal Minggu Ini -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-calendar-week me-2"></i>Minggu Ini</h5>
            </div>
            <div class="card-body">
                <?php
                $jadwal_by_date = [];
                foreach ($minggu_ini as $mi) {
                    $jadwal_by_date[$mi['tanggal']][] = $mi;
                }
                ?>
                <?php if (empty($jadwal_by_date)): ?>
                <p class="text-muted text-center">Belum ada jadwal</p>
                <?php else: ?>
                <?php foreach ($jadwal_by_date as $tgl => $jds): ?>
                <div class="mb-3">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div style="width:8px;height:8px;border-radius:50;background:var(--primary);"></div>
                        <strong style="font-size:13px;"><?= formatTanggal($tgl) ?></strong>
                    </div>
                    <?php foreach ($jds as $jd): ?>
                    <div class="d-flex align-items-center justify-content-between p-2 mb-1" style="background:var(--bg-glass);border-radius:var(--radius-sm);font-size:13px;">
                        <span><?= htmlspecialchars($jd['nama_guru']) ?></span>
                        <?php if ($jd['shift_pagi']): ?>
                            <span class="badge badge-info" style="font-size:10px;">Pagi</span>
                        <?php endif; ?>
                        <?php if ($jd['shift_siang']): ?>
                            <span class="badge badge-warning" style="font-size:10px;">Siang</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div><!-- /ajax-area -->

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jadwal Piket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Guru Piket <span class="text-danger">*</span></label>
                        <select class="form-select" name="guru_id" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php foreach ($guru_list as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Shift <span class="text-danger">*</span></label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="shift_pagi" value="1" id="shiftPagi" checked>
                                <label class="form-check-label" for="shiftPagi"><i class="bi bi-sun me-1"></i>Pagi</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="shift_siang" value="1" id="shiftSiang">
                                <label class="form-check-label" for="shiftSiang"><i class="bi bi-moon me-1"></i>Siang</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="2"></textarea>
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
