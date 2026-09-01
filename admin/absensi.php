<?php
$page_title = 'Absensi Guru Mengajar';
$page_subtitle = 'Input kehadiran guru saat mengajar di setiap kelas';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $guru_id = intval($_POST['guru_id']);
        $jadwal_id = intval($_POST['jadwal_id']);
        $kelas_id = intval($_POST['kelas_id']);
        $mapel_id = intval($_POST['mapel_id']);
        $tanggal = sanitize($_POST['tanggal']);
        $status = sanitize($_POST['status_kedatangan']);
        $jam_masuk = sanitize($_POST['jam_masuk'] ?? '');
        $jam_mulai = sanitize($_POST['jam_mengajar_mulai'] ?? '');
        $jam_selesai = sanitize($_POST['jam_mengajar_selesai'] ?? '');
        $durasi = intval($_POST['durasi_mengajar'] ?? 0);
        $catatan = sanitize($_POST['catatan']);
        $pelapor = intval($_POST['dilaporkan_oleh']);

        // Cegah insert tanpa jadwal (FK monitoring_kehadiran.jadwal_id NOT NULL)
        if (!$jadwal_id) {
            $msg = 'Pilih jadwal mengajar terlebih dahulu (belum ada jadwal untuk kelas ini?).';
            flash('danger', $msg);
            if (isAjax()) jsonOut(['success' => false, 'message' => $msg]);
            redirect('absensi.php');
        }

        // i guru, i jadwal, i kelas, i mapel, s tanggal, s status,
        // s jam_masuk, s jam_mulai, s jam_selesai, i durasi, s catatan, i pelapor
        $stmt = $conn->prepare("INSERT INTO monitoring_kehadiran (guru_id, jadwal_id, kelas_id, mapel_id, tanggal, status_kedatangan, jam_masuk, jam_mengajar_mulai, jam_mengajar_selesai, durasi_mengajar, catatan, dilaporkan_oleh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiisssssisi", $guru_id, $jadwal_id, $kelas_id, $mapel_id, $tanggal, $status, $jam_masuk, $jam_mulai, $jam_selesai, $durasi, $catatan, $pelapor);
        $ok = $stmt->execute();
        $msg = $ok ? 'Absensi guru mengajar berhasil disimpan!' : 'Gagal: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('absensi.php');
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM monitoring_kehadiran WHERE id = $id");
        $msg = 'Data absensi berhasil dihapus!';
        flash('success', $msg);
        if (isAjax()) jsonOut(['success' => true, 'message' => $msg]);
        redirect('absensi.php');
    }
}

// Filter
$tgl_filter = sanitize($_GET['tanggal'] ?? date('Y-m-d'));
$kelas_filter = $_GET['kelas_id'] ?? '';

$where = "WHERE mk.tanggal = '$tgl_filter'";
if ($kelas_filter) $where .= " AND mk.kelas_id = " . intval($kelas_filter);
$search = sanitize($_GET['search'] ?? '');
if ($search) $where .= " AND (g.nama_guru LIKE '%$search%' OR k.nama_kelas LIKE '%$search%' OR mp.nama_mapel LIKE '%$search%')";

// Data absensi guru mengajar (dari monitoring_kehadiran)
$absensi_list = $conn->query("
    SELECT mk.*, g.nama_guru, g.no_hp, k.nama_kelas, mp.nama_mapel, mp.kode_mapel,
           gp.nama_guru AS nama_pelapor,
           jm.ruangan, jm.hari AS jadwal_hari, jm.jam_mulai AS jadwal_mulai, jm.jam_selesai AS jadwal_selesai
    FROM monitoring_kehadiran mk
    JOIN guru g ON mk.guru_id = g.id
    JOIN kelas k ON mk.kelas_id = k.id
    JOIN mata_pelajaran mp ON mk.mapel_id = mp.id
    LEFT JOIN guru gp ON mk.dilaporkan_oleh = gp.id
    LEFT JOIN jadwal_mengajar jm ON mk.jadwal_id = jm.id
    $where
    ORDER BY mk.jam_mengajar_mulai ASC
")->fetch_all(MYSQLI_ASSOC);

// Statistik
$total = count($absensi_list);
$tepat = count(array_filter($absensi_list, fn($a) => $a['status_kedatangan'] === 'tepat_waktu'));
$terlambat = count(array_filter($absensi_list, fn($a) => $a['status_kedatangan'] === 'terlambat'));
$tidak = count(array_filter($absensi_list, fn($a) => $a['status_kedatangan'] === 'tidak_hadir' || $a['status_kedatangan'] === 'digantikan'));

$guru_list = $conn->query("SELECT id, nama_guru FROM guru WHERE status='aktif' ORDER BY nama_guru")->fetch_all(MYSQLI_ASSOC);
$kelas_list_all = $conn->query("SELECT id, nama_kelas FROM kelas WHERE status='aktif' ORDER BY tingkat, nama_kelas")->fetch_all(MYSQLI_ASSOC);
$jadwal_list = $conn->query("
    SELECT jm.*, g.nama_guru, k.nama_kelas, mp.nama_mapel, mp.kode_mapel
    FROM jadwal_mengajar jm
    JOIN guru g ON jm.guru_id = g.id
    JOIN kelas k ON jm.kelas_id = k.id
    JOIN mata_pelajaran mp ON jm.mapel_id = mp.id
    WHERE jm.status='aktif'
    ORDER BY jm.hari, jm.jam_mulai
")->fetch_all(MYSQLI_ASSOC);

// =============================================
// Blok JP: gabungkan slot berurutan (guru+kelas+mapel sama, jam menyambung)
// menjadi satu blok — guru piket cukup input 1 entri per blok (mis. 3 JP)
// =============================================
function jamDiffMenit($a, $b) {
    return (int) round((strtotime($b) - strtotime($a)) / 60);
}

$blok_groups = [];
foreach ($jadwal_list as $j) {
    $key = $j['hari'] . '|' . $j['guru_id'] . '|' . $j['kelas_id'] . '|' . $j['mapel_id'];
    $blok_groups[$key][] = $j;
}
$jadwal_blocks = [];
foreach ($blok_groups as $rows) {
    $cur = null;
    foreach ($rows as $j) {
        if ($cur && $cur['jam_selesai'] === $j['jam_mulai']) {
            $cur['jam_selesai'] = $j['jam_selesai'];
            $cur['jp']++;
            $cur['ids'][] = $j['id'];
            $cur['durasi'] += jamDiffMenit($j['jam_mulai'], $j['jam_selesai']);
        } else {
            if ($cur) $jadwal_blocks[] = $cur;
            $cur = [
                'id' => $j['id'], 'ids' => [$j['id']],
                'guru_id' => $j['guru_id'], 'kelas_id' => $j['kelas_id'], 'mapel_id' => $j['mapel_id'],
                'hari' => $j['hari'],
                'jam_mulai' => $j['jam_mulai'], 'jam_selesai' => $j['jam_selesai'],
                'jp' => 1,
                'durasi' => jamDiffMenit($j['jam_mulai'], $j['jam_selesai']),
                'nama_guru' => $j['nama_guru'], 'nama_kelas' => $j['nama_kelas'],
                'nama_mapel' => $j['nama_mapel'], 'kode_mapel' => $j['kode_mapel'],
            ];
        }
    }
    if ($cur) $jadwal_blocks[] = $cur;
}
usort($jadwal_blocks, function ($a, $b) {
    $hi = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $da = array_search($a['hari'], $hi);
    $db = array_search($b['hari'], $hi);
    if ($da !== $db) return $da <=> $db;
    return strcmp($a['jam_mulai'], $b['jam_mulai']);
});

// Peta absensi yang sudah tercatat: "tanggal|kelas_id" => [jadwal_id, ...]
// dipakai menandai blok yang sudah dicatat di modal input.
$recorded_map = [];
$rec_rows = $conn->query("SELECT tanggal, kelas_id, jadwal_id FROM monitoring_kehadiran");
if ($rec_rows) foreach ($rec_rows as $rr) $recorded_map[$rr['tanggal'] . '|' . $rr['kelas_id']][] = (int) $rr['jadwal_id'];
?>

<!-- Filter & Aksi -->
<div id="ajax-area">
<div class="card mb-4">
    <div class="filter-bar">
        <form class="d-flex gap-2 align-items-center flex-wrap ajax-filter" method="GET">
            <label class="form-label mb-0 me-1 text-secondary">Tanggal:</label>
            <input type="date" class="form-control" name="tanggal" value="<?= $tgl_filter ?>" style="width:200px;">
            <select class="form-select" name="kelas_id" style="width:180px;">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelas_list_all as $k): ?>
                <option value="<?= $k['id'] ?>" <?= $kelas_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control" name="search" placeholder="Cari guru / kelas / mapel..." value="<?= htmlspecialchars($search) ?>" style="width:200px;">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="?tanggal=<?= date('Y-m-d') ?>" class="btn btn-outline-light">Hari Ini</a>
        </form>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-light" onclick="exportCSV('absensiGuruTable', 'absensi_guru_<?= $tgl_filter ?>')">
                <i class="bi bi-download me-1"></i>CSV
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i>Input Absensi
            </button>
        </div>
    </div>
</div>

<!-- Kartu Statistik -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box blue"><i class="bi bi-person-check"></i></div>
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Total Absensi</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $tepat ?></div>
            <div class="stat-label">Tepat Waktu</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box yellow"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-value"><?= $terlambat ?></div>
            <div class="stat-label">Terlambat</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box red"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-value"><?= $tidak ?></div>
            <div class="stat-label">Tidak Hadir</div>
        </div>
    </div>
</div>

<!-- Tabel Absensi Guru Mengajar -->
<div class="card">
    <div class="card-header">
        <h5>Daftar Absensi Guru Mengajar - <?= formatTanggal($tgl_filter) ?></h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" id="absensiGuruTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Guru</th>
                        <th>Mata Pelajaran</th>
                        <th>Kelas</th>
                        <th>Jam Mengajar</th>
                        <th>Status</th>
                        <th>Jam Masuk</th>
                        <th>Durasi</th>
                        <th>Pelapor</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absensi_list)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h5>Belum Ada Absensi Guru</h5>
                                <p>Belum ada catatan kehadiran guru mengajar pada tanggal ini.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($absensi_list as $i => $a): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($a['nama_guru']) ?></strong></td>
                        <td>
                            <span class="mapel-kode-inline"><?= htmlspecialchars($a['kode_mapel']) ?></span>
                            <?= htmlspecialchars($a['nama_mapel']) ?>
                        </td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($a['nama_kelas']) ?></span></td>
                        <td class="jam-mengajar">
                            <?php if ($a['jam_mengajar_mulai']): ?>
                                <?= formatJam($a['jam_mengajar_mulai']) ?>-<?= formatJam($a['jam_mengajar_selesai']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = match($a['status_kedatangan']) {
                                'tepat_waktu' => 'badge-success',
                                'terlambat'    => 'badge-warning',
                                'tidak_hadir'  => 'badge-danger',
                                'digantikan'   => 'badge-info',
                                default        => 'badge-primary'
                            };
                            $statusLabel = match($a['status_kedatangan']) {
                                'tepat_waktu' => 'Tepat Waktu',
                                'terlambat'    => 'Terlambat',
                                'tidak_hadir'  => 'Tidak Hadir',
                                'digantikan'   => 'Digantikan',
                                default        => ucfirst($a['status_kedatangan'])
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                        </td>
                        <td><?= $a['jam_masuk'] ? formatJam($a['jam_masuk']) : '-' ?></td>
                        <td><?= $a['durasi_mengajar'] ? $a['durasi_mengajar'] . ' mnt' : '-' ?></td>
                        <td class="text-secondary"><?= htmlspecialchars($a['nama_pelapor'] ?? '-') ?></td>
                        <td class="d-flex gap-1">
                            <?php
                            // Tombol pengingat WhatsApp ke guru pengajar
                            $jamA = $a['jam_mengajar_mulai'] ?: ($a['jadwal_mulai'] ?? '');
                            $jamB = $a['jam_mengajar_selesai'] ?: ($a['jadwal_selesai'] ?? '');
                            $wa = null;
                            if ($jamA && $jamB) {
                                $pesan = pesanPengingatMengajar(
                                    $a['nama_guru'], namaHari($a['tanggal']), $a['tanggal'],
                                    formatJam($jamA), formatJam($jamB),
                                    $a['nama_kelas'], $a['kode_mapel'] . ' - ' . $a['nama_mapel'],
                                    $a['ruangan'] ?? ''
                                );
                                $wa = waLink($a['no_hp'], $pesan);
                            }
                            ?>
                            <?php if ($wa): ?>
                            <a href="<?= $wa ?>" target="_blank" class="btn btn-sm wa-btn btn-icon" title="Kirim pengingat jam mengajar via WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <?php else: ?>
                            <a href="<?= BASE_URL ?>/admin/guru.php?search=<?= urlencode($a['nama_guru']) ?>" class="btn btn-sm wa-btn wa-btn-missing btn-icon" title="No. HP <?= htmlspecialchars($a['nama_guru']) ?> belum diisi — klik untuk lengkapi di halaman Guru">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <?php endif; ?>
                            <form method="POST" class="d-inline ajax-form" data-confirm="Hapus data absensi ini?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger btn-icon" type="submit"><i class="bi bi-trash"></i></button>
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

<script>window.RECORDED_DATA = <?= json_encode($recorded_map) ?>;</script>
</div><!-- /ajax-area -->

<!-- Modal Input Absensi Guru Mengajar (class-centric: pilih kelas -> lihat jadwal -> klik blok) -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Input Absensi Guru Mengajar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal" id="tanggalAbsensi" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" id="kelasAbsensi">
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list_all as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= $kelas_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Dilaporkan Oleh (Guru Piket) <span class="text-danger">*</span></label>
                            <select class="form-select" name="dilaporkan_oleh" required>
                                <option value="">-- Pilih Guru Piket --</option>
                                <?php foreach ($guru_list as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <label class="form-label mb-2">Jadwal Kelas <span class="text-secondary">(klik blok untuk memilih — 1 entri per blok JP)</span></label>
                    <div id="absBlockList" class="abs-block-list">
                        <?php foreach ($jadwal_blocks as $b): ?>
                        <button type="button" class="abs-block d-none"
                                data-kelas="<?= $b['kelas_id'] ?>" data-hari="<?= $b['hari'] ?>"
                                data-jadwal-id="<?= $b['id'] ?>" data-ids='<?= json_encode($b['ids']) ?>'
                                data-guru-id="<?= $b['guru_id'] ?>" data-kelas-id="<?= $b['kelas_id'] ?>" data-mapel-id="<?= $b['mapel_id'] ?>"
                                data-mulai="<?= $b['jam_mulai'] ?>" data-selesai="<?= $b['jam_selesai'] ?>" data-durasi="<?= $b['durasi'] ?>"
                                data-nama-guru="<?= htmlspecialchars($b['nama_guru']) ?>" data-nama-mapel="<?= htmlspecialchars($b['nama_mapel']) ?>"
                                data-kode-mapel="<?= htmlspecialchars($b['kode_mapel']) ?>" data-nama-kelas="<?= htmlspecialchars($b['nama_kelas']) ?>">
                            <span class="ab-time"><?= formatJam($b['jam_mulai']) ?>–<?= formatJam($b['jam_selesai']) ?></span>
                            <span class="ab-main">
                                <b><?= htmlspecialchars($b['kode_mapel']) ?> · <?= htmlspecialchars($b['nama_mapel']) ?></b>
                                <span class="ab-guru"><i class="bi bi-person"></i> <?= htmlspecialchars($b['nama_guru']) ?></span>
                            </span>
                            <span class="ab-badge"><?= $b['jp'] ?> JP</span>
                            <span class="ab-status"><i class="bi bi-check-circle-fill me-1"></i>Dicatat</span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div id="absBlockEmpty" class="empty-state py-4 d-none">
                        <i class="bi bi-calendar-x"></i>
                        <p class="mb-0">Tidak ada jadwal untuk kelas ini pada hari itu.</p>
                    </div>
                    <div id="absSelectedInfo" class="preview-block mt-2 d-none">
                        <i class="bi bi-check-circle me-2"></i><span id="absSelectedText"></span>
                    </div>

                    <input type="hidden" name="jadwal_id" id="absJadwalId">
                    <input type="hidden" name="guru_id" id="absGuruId">
                    <input type="hidden" name="kelas_id" id="absKelasId">
                    <input type="hidden" name="mapel_id" id="absMapelId">
                    <input type="hidden" name="jam_mengajar_mulai" id="absJamMulai">
                    <input type="hidden" name="jam_mengajar_selesai" id="absJamSelesai">

                    <hr style="border-color:var(--border-color);">
                    <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Data Kehadiran</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status Kedatangan <span class="text-danger">*</span></label>
                            <select class="form-select" name="status_kedatangan" required>
                                <option value="tepat_waktu">Tepat Waktu</option>
                                <option value="terlambat">Terlambat</option>
                                <option value="tidak_hadir">Tidak Hadir</option>
                                <option value="digantikan">Digantikan</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jam Masuk Kelas</label>
                            <input type="time" class="form-control" name="jam_masuk">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Durasi <small class="text-secondary">(otomatis dari jadwal)</small></label>
                            <input type="text" class="form-control" id="absDurasiView" readonly placeholder="pilih blok jadwal" style="background:var(--bg-glass);">
                            <input type="hidden" name="durasi_mengajar" id="absDurasi">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" name="catatan" rows="2" placeholder="Contoh: terlambat 15 menit karena rapat dinas"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" disabled><i class="bi bi-check-lg me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('addModal');
    var blocks = Array.prototype.slice.call(document.querySelectorAll('#absBlockList .abs-block'));
    var selTanggal = document.getElementById('tanggalAbsensi');
    var selKelas = document.getElementById('kelasAbsensi');
    var listEl = document.getElementById('absBlockList');
    var emptyEl = document.getElementById('absBlockEmpty');
    var infoEl = document.getElementById('absSelectedInfo');
    var infoText = document.getElementById('absSelectedText');
    var saveBtn = modal.querySelector('button[type="submit"]');

    function namaHari(tgl) {
        return ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][new Date(tgl + 'T00:00:00').getDay()];
    }

    function clearSelection() {
        blocks.forEach(function (b) { b.classList.remove('selected'); });
        ['absJadwalId', 'absGuruId', 'absKelasId', 'absMapelId', 'absJamMulai', 'absJamSelesai', 'absDurasi'].forEach(function (id) {
            var el = document.getElementById(id); if (el) el.value = '';
        });
        var dv = document.getElementById('absDurasiView'); if (dv) dv.value = '';
        infoEl.classList.add('d-none');
        saveBtn.disabled = true;
    }

    function selectBlock(b) {
        clearSelection();
        b.classList.add('selected');
        document.getElementById('absJadwalId').value = b.dataset.jadwalId;
        document.getElementById('absGuruId').value = b.dataset.guruId;
        document.getElementById('absKelasId').value = b.dataset.kelasId;
        document.getElementById('absMapelId').value = b.dataset.mapelId;
        document.getElementById('absJamMulai').value = b.dataset.mulai;
        document.getElementById('absJamSelesai').value = b.dataset.selesai;
        document.getElementById('absDurasi').value = b.dataset.durasi;
        document.getElementById('absDurasiView').value = b.dataset.durasi + ' menit';
        infoText.innerHTML = '<b>' + b.dataset.namaGuru + '</b> · ' + b.dataset.kodeMapel + ' - ' + b.dataset.namaMapel +
            ' · ' + b.dataset.namaKelas + ' · ' + (b.dataset.mulai ? b.dataset.mulai.slice(0, 5) : '') + '–' +
            (b.dataset.selesai ? b.dataset.selesai.slice(0, 5) : '') + ' (' + JSON.parse(b.dataset.ids).length + ' JP)';
        infoEl.classList.remove('d-none');
        saveBtn.disabled = false;
    }

    var lastKelas = '';

    function render() {
        clearSelection();
        if (!selKelas.value) {
            var first = selKelas.querySelector('option[value]:not([value=""])');
            if (first) selKelas.value = first.value;
        }
        lastKelas = selKelas.value;
        var day = namaHari(selTanggal.value);
        var kid = selKelas.value;
        var visible = 0;
        var recorded = (window.RECORDED_DATA || {});
        blocks.forEach(function (b) {
            var show = kid && b.dataset.hari === day && String(b.dataset.kelas) === String(kid);
            b.classList.toggle('d-none', !show);
            if (show) {
                var rec = recorded[selTanggal.value + '|' + kid] || [];
                var ids = JSON.parse(b.dataset.ids || '[]').map(function (x) { return parseInt(x, 10); });
                b.classList.toggle('done', ids.some(function (id) { return rec.indexOf(id) !== -1; }));
                visible++;
            }
        });
        emptyEl.classList.toggle('d-none', visible > 0);
        var firstFree = null, firstAny = null;
        blocks.forEach(function (b) {
            if (b.classList.contains('d-none')) return;
            if (!firstAny) firstAny = b;
            if (!b.classList.contains('done') && !firstFree) firstFree = b;
        });
        if (firstFree) selectBlock(firstFree);
        else if (firstAny) selectBlock(firstAny);
    }

    listEl.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.abs-block');
        if (b && !b.classList.contains('d-none')) selectBlock(b);
    });
    selTanggal.addEventListener('change', render);
    selKelas.addEventListener('change', function () { lastKelas = selKelas.value; render(); });
    modal.addEventListener('show.bs.modal', function () {
        if (lastKelas) selKelas.value = lastKelas;
        render();
    });
    render();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>