<?php
$page_title = 'Kelola Kelas';
$page_subtitle = 'Manajemen data kelas';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nama = sanitize($_POST['nama_kelas']);
        $tingkat = intval($_POST['tingkat']);
        $jurusan = sanitize($_POST['jurusan']);
        $kapasitas = intval($_POST['kapasitas']);
        $wali = intval($_POST['wali_kelas_id']) ?: null;
        
        $stmt = $conn->prepare("INSERT INTO kelas (nama_kelas, tingkat, jurusan, kapasitas, wali_kelas_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisii", $nama, $tingkat, $jurusan, $kapasitas, $wali);
        $ok = $stmt->execute();
        $msg = $ok ? 'Kelas berhasil ditambahkan!' : 'Gagal: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('kelas.php');
    }
    
    if ($action === 'edit') {
        $id = intval($_POST['id']);
        $nama = sanitize($_POST['nama_kelas']);
        $tingkat = intval($_POST['tingkat']);
        $jurusan = sanitize($_POST['jurusan']);
        $kapasitas = intval($_POST['kapasitas']);
        $wali = intval($_POST['wali_kelas_id']) ?: null;
        $status = sanitize($_POST['status']);
        
        // s: nama, i: tingkat, s: jurusan, i: kapasitas, i: wali, s: status, i: id
        $stmt = $conn->prepare("UPDATE kelas SET nama_kelas=?, tingkat=?, jurusan=?, kapasitas=?, wali_kelas_id=?, status=? WHERE id=?");
        $stmt->bind_param("sisiisi", $nama, $tingkat, $jurusan, $kapasitas, $wali, $status, $id);
        $ok = $stmt->execute();
        $msg = $ok ? 'Kelas berhasil diupdate!' : 'Gagal mengupdate: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('kelas.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM kelas WHERE id = $id");
        $msg = 'Kelas berhasil dihapus!';
        flash('success', $msg);
        if (isAjax()) jsonOut(['success' => true, 'message' => $msg]);
        redirect('kelas.php');
    }
}

$search = sanitize($_GET['search'] ?? '');
$where_kelas = $search ? "WHERE (k.nama_kelas LIKE '%$search%' OR COALESCE(k.jurusan, '') LIKE '%$search%')" : '';
$kelas_list = $conn->query("
    SELECT k.*, g.nama_guru AS wali_kelas_nama,
    (SELECT COUNT(*) FROM jadwal_mengajar jm WHERE jm.kelas_id = k.id) AS total_jadwal
    FROM kelas k 
    LEFT JOIN guru g ON k.wali_kelas_id = g.id
    $where_kelas
    ORDER BY k.tingkat, k.nama_kelas
")->fetch_all(MYSQLI_ASSOC);

$guru_list = $conn->query("SELECT id, nama_guru FROM guru WHERE status='aktif' ORDER BY nama_guru")->fetch_all(MYSQLI_ASSOC);

// Detail pengajar per kelas (dari jadwal mengajar)
$jadwal_all = $conn->query("
    SELECT jm.kelas_id, jm.hari, jm.jam_mulai, jm.jam_selesai, jm.ruangan,
           g.nama_guru, g.no_hp, mp.nama_mapel, mp.kode_mapel
    FROM jadwal_mengajar jm
    JOIN guru g ON jm.guru_id = g.id
    JOIN mata_pelajaran mp ON jm.mapel_id = mp.id
    WHERE jm.status = 'aktif'
    ORDER BY jm.kelas_id,
             FIELD(jm.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'),
             jm.jam_mulai
")->fetch_all(MYSQLI_ASSOC);

$jadwal_grouped = [];
foreach ($jadwal_all as $j) {
    $jadwal_grouped[$j['kelas_id']][] = $j;
}
?>

<div id="ajax-area">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex gap-3 align-items-center">
        <span class="text-muted">Total: <strong><?= count($kelas_list) ?></strong> kelas</span>
        <form class="d-flex gap-2 ajax-filter" method="GET">
            <input type="text" class="form-control" name="search" placeholder="Cari kelas / jurusan..." value="<?= htmlspecialchars($search) ?>" style="width:220px;">
        </form>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>Tambah Kelas
    </button>
</div>

<div class="row g-4">
    <?php foreach ($kelas_list as $k): ?>
    <div class="col-xl-4 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($k['nama_kelas']) ?></h5>
                        <span class="text-muted">Tingkat <?= $k['tingkat'] ?></span>
                        <?php if ($k['jurusan']): ?>
                            <span class="badge badge-secondary ms-2"><?= htmlspecialchars($k['jurusan']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editModal<?= $k['id'] ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                            <li>
                                <form method="POST" class="ajax-form" data-confirm="Hapus kelas ini?">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                    <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Hapus</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <div class="wali-line">
                    <i class="bi bi-person-circle"></i>
                    <span class="wali-label">Wali Kelas</span>
                    <span class="wali-nama"><?= htmlspecialchars($k['wali_kelas_nama'] ?? 'Belum ditentukan') ?></span>
                </div>
                
                <?php
                $pengajar = $jadwal_grouped[$k['id']] ?? [];
                // Ringkasan mata pelajaran di kelas ini (unik per kode mapel)
                $mapel_sum = [];
                $hari_urut = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                $hari_unik = [];
                foreach ($pengajar as $pj) {
                    $mk = $pj['kode_mapel'];
                    if (!isset($mapel_sum[$mk])) {
                        $mapel_sum[$mk] = ['kode' => $mk, 'nama' => $pj['nama_mapel'], 'jp' => 0];
                    }
                    $mapel_sum[$mk]['jp']++;
                    if (!in_array($pj['hari'], $hari_unik)) $hari_unik[] = $pj['hari'];
                }
                usort($hari_unik, function ($a, $b) use ($hari_urut) {
                    return array_search($a, $hari_urut) - array_search($b, $hari_urut);
                });
                ?>

                <!-- Mata Pelajaran di Kelas Ini -->
                <div class="matpel-box mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="pengajar-title"><i class="bi bi-journal-bookmark me-1"></i>Mata Pelajaran</span>
                        <?php if ($mapel_sum): ?>
                        <span class="badge badge-info"><?= count($mapel_sum) ?> Mapel</span>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($mapel_sum)): ?>
                    <div class="empty-pengajar">
                        <i class="bi bi-journal-x"></i>
                        <span>Belum ada mata pelajaran</span>
                    </div>
                    <?php else: ?>
                    <div class="matpel-list">
                        <?php foreach ($mapel_sum as $ms): ?>
                        <span class="matpel-tag" title="<?= htmlspecialchars($ms['nama']) ?>">
                            <span class="matpel-tag-kode"><?= htmlspecialchars($ms['kode']) ?></span>
                            <span class="matpel-tag-nama"><?= htmlspecialchars($ms['nama']) ?></span>
                            <span class="matpel-tag-jp"><?= $ms['jp'] ?> JP</span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Detail Pengajar -->
                <div class="pengajar-box mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                        <span class="pengajar-title"><i class="bi bi-person-workspace me-1"></i>Detail Pengajar</span>
                        <?php if ($pengajar): ?>
                            <span class="badge badge-primary pengajar-count"><?= count($pengajar) ?> Jadwal</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($pengajar)): ?>
                    <div class="empty-pengajar">
                        <i class="bi bi-person-x"></i>
                        <span>Belum ada guru mengajar di kelas ini</span>
                    </div>
                    <?php else: ?>
                    <div class="pengajar-filters mb-2">
                        <button type="button" class="pengajar-filter active" data-filter-day="">Semua</button>
                        <?php foreach ($hari_unik as $hd): ?>
                        <button type="button" class="pengajar-filter" data-filter-day="<?= $hd ?>"><?= $hd ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table pengajar-table">
                            <thead>
                                <tr>
                                    <th>Hari</th>
                                    <th>Jam</th>
                                    <th>Mapel</th>
                                    <th>Guru</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pengajar as $pj): ?>
                                <tr class="pengajar-row" data-hari="<?= $pj['hari'] ?>">
                                    <td class="pengajar-hari"><?= $pj['hari'] ?></td>
                                    <td class="jam-mengajar"><?= formatJam($pj['jam_mulai']) ?>-<?= formatJam($pj['jam_selesai']) ?></td>
                                    <td class="mapel-text"><span class="mapel-kode"><?= htmlspecialchars($pj['kode_mapel']) ?></span><?= htmlspecialchars($pj['nama_mapel']) ?></td>
                                    <td class="guru-nama"><?= htmlspecialchars($pj['nama_guru']) ?></td>
                                    <td class="wa-cell">
                                        <?php
                                        $tanggalPengingat = date('Y-m-d');
                                        // cari jadwal terdekat di hari ini; fallback ke hari jadwal
                                        $pesan = pesanPengingatMengajar(
                                            $pj['nama_guru'], $pj['hari'], $tanggalPengingat,
                                            formatJam($pj['jam_mulai']), formatJam($pj['jam_selesai']),
                                            $k['nama_kelas'], $pj['kode_mapel'] . ' - ' . $pj['nama_mapel'],
                                            $pj['ruangan'] ?? ''
                                        );
                                        $wa = waLink($pj['no_hp'], $pesan);
                                        ?>
                                        <?php if ($wa): ?>
                                        <a href="<?= $wa ?>" target="_blank" class="btn btn-sm wa-btn btn-icon" title="Kirim pengingat jam mengajar via WhatsApp">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                        <?php else: ?>
                                        <a href="<?= BASE_URL ?>/admin/guru.php?search=<?= urlencode($pj['nama_guru']) ?>" class="btn btn-sm wa-btn wa-btn-missing btn-icon" title="No. HP <?= htmlspecialchars($pj['nama_guru']) ?> belum diisi — klik untuk lengkapi di halaman Guru">
                                            <i class="bi bi-whatsapp"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="empty-pengajar d-none" data-empty-filter>
                            <i class="bi bi-calendar-x"></i>
                            <span>Tidak ada jadwal pada hari ini</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Status Kelas -->
                <div class="d-flex align-items-center justify-content-between pt-2" style="border-top:1px solid var(--border-color);">
                    <?php if ($pengajar): ?>
                        <span class="status-ada"><i class="bi bi-check-circle-fill me-1"></i>Ada Guru Mengajar</span>
                    <?php else: ?>
                        <span class="status-tidak"><i class="bi bi-x-circle-fill me-1"></i>Tidak Ada Guru</span>
                    <?php endif; ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="stat-mini"><i class="bi bi-people me-1"></i><?= $k['kapasitas'] ?> siswa</span>
                        <span class="stat-mini"><i class="bi bi-person-check me-1"></i><?= count($pengajar) ?> guru</span>
                        <span class="badge <?= $k['status'] === 'aktif' ? 'badge-success' : 'badge-danger' ?>"><?= ucfirst($k['status']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal<?= $k['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" class="ajax-form">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Kelas</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama Kelas</label>
                            <input type="text" class="form-control" name="nama_kelas" value="<?= htmlspecialchars($k['nama_kelas']) ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tingkat</label>
                                <select class="form-select" name="tingkat">
                                    <?php for($t=1; $t<=12; $t++): ?>
                                    <option value="<?= $t ?>" <?= $k['tingkat'] == $t ? 'selected' : '' ?>><?= $t ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kapasitas</label>
                                <input type="number" class="form-control" name="kapasitas" value="<?= $k['kapasitas'] ?>" min="1">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jurusan</label>
                            <input type="text" class="form-control" name="jurusan" value="<?= htmlspecialchars($k['jurusan'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Wali Kelas</label>
                            <select class="form-select" name="wali_kelas_id">
                                <option value="">-- Pilih Wali Kelas --</option>
                                <?php foreach ($guru_list as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= $k['wali_kelas_id'] == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="aktif" <?= $k['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                <option value="nonaktif" <?= $k['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
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
</div>
</div><!-- /ajax-area -->

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kelas Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kelas" placeholder="Contoh: VII-A" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tingkat <span class="text-danger">*</span></label>
                            <select class="form-select" name="tingkat">
                                <?php for($t=1; $t<=12; $t++): ?>
                                <option value="<?= $t ?>"><?= $t ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kapasitas</label>
                            <input type="number" class="form-control" name="kapasitas" value="36" min="1">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jurusan</label>
                        <input type="text" class="form-control" name="jurusan" placeholder="Kosongkan jika tidak ada jurusan">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Wali Kelas</label>
                        <select class="form-select" name="wali_kelas_id">
                            <option value="">-- Pilih Wali Kelas --</option>
                            <?php foreach ($guru_list as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                            <?php endforeach; ?>
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
