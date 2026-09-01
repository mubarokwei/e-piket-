<?php
$page_title = 'Monitoring Kehadiran Guru';
$page_subtitle = 'Monitoring kehadiran guru dalam kelas per mata pelajaran';
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
            redirect('monitoring.php');
        }
        
        $stmt = $conn->prepare("INSERT INTO monitoring_kehadiran (guru_id, jadwal_id, kelas_id, mapel_id, tanggal, status_kedatangan, jam_masuk, jam_mengajar_mulai, jam_mengajar_selesai, durasi_mengajar, catatan, dilaporkan_oleh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        // Urutan tipe: i guru_id, i jadwal_id, i kelas_id, i mapel_id,
        // s tanggal, s status, s jam_masuk, s jam_mulai, s jam_selesai,
        // i durasi, s catatan, i pelapor
        $stmt->bind_param("iiiisssssisi", $guru_id, $jadwal_id, $kelas_id, $mapel_id, $tanggal, $status, $jam_masuk, $jam_mulai, $jam_selesai, $durasi, $catatan, $pelapor);
        $ok = $stmt->execute();
        $msg = $ok ? 'Monitoring berhasil ditambahkan!' : 'Gagal: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('monitoring.php');
    }
    
    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM monitoring_kehadiran WHERE id = $id");
        $msg = 'Data monitoring berhasil dihapus!';
        flash('success', $msg);
        if (isAjax()) jsonOut(['success' => true, 'message' => $msg]);
        redirect('monitoring.php');
    }
}

// Filter
$tgl_mulai = sanitize($_GET['tgl_mulai'] ?? date('Y-m-d'));
$tgl_selesai = sanitize($_GET['tgl_selesai'] ?? date('Y-m-d'));
$mapel_filter = $_GET['mapel_id'] ?? '';
$guru_filter = $_GET['guru_id'] ?? '';
$kelas_filter = $_GET['kelas_id'] ?? '';

$search = sanitize($_GET['search'] ?? '');

$where = "WHERE mk.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
if ($mapel_filter) $where .= " AND mk.mapel_id = " . intval($mapel_filter);
if ($guru_filter) $where .= " AND mk.guru_id = " . intval($guru_filter);
if ($kelas_filter) $where .= " AND mk.kelas_id = " . intval($kelas_filter);
if ($search) $where .= " AND (g.nama_guru LIKE '%$search%' OR k.nama_kelas LIKE '%$search%' OR mp.nama_mapel LIKE '%$search%')";

$monitoring_data = $conn->query("
    SELECT mk.*, g.nama_guru, g.nip, g.no_hp, k.nama_kelas, mp.nama_mapel, mp.kode_mapel,
           gp.nama_guru AS nama_pelapor,
           jm.ruangan, jm.hari AS jadwal_hari, jm.jam_mulai AS jadwal_mulai, jm.jam_selesai AS jadwal_selesai
    FROM monitoring_kehadiran mk
    JOIN guru g ON mk.guru_id = g.id
    JOIN kelas k ON mk.kelas_id = k.id
    JOIN mata_pelajaran mp ON mk.mapel_id = mp.id
    LEFT JOIN guru gp ON mk.dilaporkan_oleh = gp.id
    LEFT JOIN jadwal_mengajar jm ON mk.jadwal_id = jm.id
    $where
    ORDER BY mk.tanggal DESC, mk.jam_mengajar_mulai ASC
")->fetch_all(MYSQLI_ASSOC);

// Stats
$total_monitoring = count($monitoring_data);
$tepat_waktu = count(array_filter($monitoring_data, fn($m) => $m['status_kedatangan'] === 'tepat_waktu'));
$terlambat = count(array_filter($monitoring_data, fn($m) => $m['status_kedatangan'] === 'terlambat'));
$tidak_hadir = count(array_filter($monitoring_data, fn($m) => $m['status_kedatangan'] === 'tidak_hadir'));
$digantikan = count(array_filter($monitoring_data, fn($m) => $m['status_kedatangan'] === 'digantikan'));
$persen_disiplin = $total_monitoring > 0 ? round(($tepat_waktu / $total_monitoring) * 100, 1) : 0;

// Per Mata Pelajaran
$per_mapel = [];
foreach ($monitoring_data as $m) {
    $key = $m['nama_mapel'];
    if (!isset($per_mapel[$key])) {
        $per_mapel[$key] = ['kode' => $m['kode_mapel'], 'tepat_waktu' => 0, 'terlambat' => 0, 'tidak_hadir' => 0, 'digantikan' => 0, 'total' => 0, 'guru_list' => []];
    }
    $per_mapel[$key][$m['status_kedatangan']]++;
    $per_mapel[$key]['total']++;
    $per_mapel[$key]['guru_list'][$m['nama_guru']] = ($per_mapel[$key]['guru_list'][$m['nama_guru']] ?? 0) + 1;
}

// Per Guru
$per_guru = [];
foreach ($monitoring_data as $m) {
    $key = $m['nama_guru'];
    if (!isset($per_guru[$key])) {
        $per_guru[$key] = ['nip' => $m['nip'], 'tepat_waktu' => 0, 'terlambat' => 0, 'tidak_hadir' => 0, 'digantikan' => 0, 'total' => 0, 'mapel_list' => []];
    }
    $per_guru[$key][$m['status_kedatangan']]++;
    $per_guru[$key]['total']++;
    $per_guru[$key]['mapel_list'][] = $m['nama_mapel'];
}

// Data for forms
$guru_list = $conn->query("SELECT id, nama_guru FROM guru WHERE status='aktif' ORDER BY nama_guru")->fetch_all(MYSQLI_ASSOC);
$jadwal_list = $conn->query("SELECT jm.*, g.nama_guru, k.nama_kelas, k.id AS kelas_id_full, mp.nama_mapel, mp.id AS mapel_id_full FROM jadwal_mengajar jm JOIN guru g ON jm.guru_id=g.id JOIN kelas k ON jm.kelas_id=k.id JOIN mata_pelajaran mp ON jm.mapel_id=mp.id WHERE jm.status='aktif' ORDER BY jm.hari, jm.jam_mulai")->fetch_all(MYSQLI_ASSOC);
$mapel_list = $conn->query("SELECT id, kode_mapel, nama_mapel FROM mata_pelajaran WHERE status='aktif' ORDER BY kode_mapel")->fetch_all(MYSQLI_ASSOC);
$kelas_list_all = $conn->query("SELECT id, nama_kelas FROM kelas WHERE status='aktif' ORDER BY tingkat, nama_kelas")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Report Header (print) -->
<div class="report-header">
    <h2>LAPORAN MONITORING KEHADIRAN GURU</h2>
    <p>SMK PK TGI JAKARTA | Periode: <?= formatTanggal($tgl_mulai) ?> s/d <?= formatTanggal($tgl_selesai) ?></p>
    <p>Dicetak: <?= formatTanggal(date('Y-m-d')) ?> Jam <?= date('H:i') ?></p>
</div>

<!-- Filter -->
<div id="ajax-area">
<div class="card mb-4" id="filterSection">
    <div class="filter-bar">
        <form class="d-flex gap-2 flex-wrap ajax-filter" method="GET">
            <input type="date" class="form-control" name="tgl_mulai" value="<?= $tgl_mulai ?>" style="width:170px;">
            <span class="text-muted align-self-center">s/d</span>
            <input type="date" class="form-control" name="tgl_selesai" value="<?= $tgl_selesai ?>" style="width:170px;">
            <select class="form-select" name="mapel_id" style="width:180px;">
                <option value="">Semua Mapel</option>
                <?php foreach ($mapel_list as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $mapel_filter == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['kode_mapel'] . ' - ' . $m['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="guru_id" style="width:180px;">
                <option value="">Semua Guru</option>
                <?php foreach ($guru_list as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $guru_filter == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="kelas_id" style="width:150px;">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelas_list_all as $k): ?>
                <option value="<?= $k['id'] ?>" <?= $kelas_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control" name="search" placeholder="Cari guru / kelas / mapel..." value="<?= htmlspecialchars($search) ?>" style="width:190px;">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="monitoring.php" class="btn btn-outline-light">Reset</a>
        </form>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-light" onclick="exportCSV('monitoringTable', 'monitoring_kehadiran')">
                <i class="bi bi-download me-1"></i>CSV
            </button>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="bi bi-printer me-1"></i>Cetak
            </button>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i>Input Monitoring
            </button>
        </div>
    </div>
</div>

<!-- Statistik -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box blue"><i class="bi bi-eye"></i></div>
            <div class="stat-value"><?= $total_monitoring ?></div>
            <div class="stat-label">Total Monitoring</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $tepat_waktu ?></div>
            <div class="stat-label">Tepat Waktu</div>
            <div class="stat-change up"><?= $persen_disiplin ?>% disiplin</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box yellow"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-value"><?= $terlambat ?></div>
            <div class="stat-label">Terlambat</div>
            <div class="stat-change"><?= $total_monitoring > 0 ? round(($terlambat / $total_monitoring) * 100, 1) : 0 ?>%</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box red"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-value"><?= $tidak_hadir + $digantikan ?></div>
            <div class="stat-label">Tidak Hadir / Digantikan</div>
            <div class="stat-change down"><?= $total_monitoring > 0 ? round((($tidak_hadir + $digantikan) / $total_monitoring) * 100, 1) : 0 ?>%</div>
        </div>
    </div>
</div>

<!-- Monitoring per Mata Pelajaran -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-book me-2"></i>Monitoring per Mata Pelajaran</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Mata Pelajaran</th>
                        <th>Total</th>
                        <th>Tepat Waktu</th>
                        <th>Terlambat</th>
                        <th>Tidak Hadir</th>
                        <th>Digantikan</th>
                        <th>% Disiplin</th>
                        <th>Guru yang Mengajar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($per_mapel as $nama => $data): 
                        $disiplin = $data['total'] > 0 ? round(($data['tepat_waktu'] / $data['total']) * 100) : 0;
                        $barColor = $disiplin >= 90 ? 'bg-success' : ($disiplin >= 70 ? 'bg-warning' : 'bg-danger');
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <span class="badge badge-secondary"><?= htmlspecialchars($data['kode']) ?></span>
                            <strong><?= htmlspecialchars($nama) ?></strong>
                        </td>
                        <td><?= $data['total'] ?></td>
                        <td class="text-success fw-bold"><?= $data['tepat_waktu'] ?></td>
                        <td class="text-warning"><?= $data['terlambat'] ?></td>
                        <td class="text-danger"><?= $data['tidak_hadir'] ?></td>
                        <td><?= $data['digantikan'] ?></td>
                        <td style="min-width:150px;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar <?= $barColor ?>" style="width:<?= $disiplin ?>%"></div>
                                </div>
                                <small class="fw-bold"><?= $disiplin ?>%</small>
                            </div>
                        </td>
                        <td style="font-size:12px;">
                            <?php foreach (array_unique($data['guru_list']) as $gn => $cnt): ?>
                                <span class="badge badge-info mb-1"><?= htmlspecialchars($gn) ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Monitoring per Guru -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-person me-2"></i>Rekapitulasi Kehadiran per Guru</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Guru</th>
                        <th>NIP</th>
                        <th>Total</th>
                        <th>Tepat Waktu</th>
                        <th>Terlambat</th>
                        <th>Tidak Hadir</th>
                        <th>Digantikan</th>
                        <th>% Disiplin</th>
                        <th>Mapel</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($per_guru as $nama => $data): 
                        $disiplin = $data['total'] > 0 ? round(($data['tepat_waktu'] / $data['total']) * 100) : 0;
                        $barColor = $disiplin >= 90 ? 'bg-success' : ($disiplin >= 70 ? 'bg-warning' : 'bg-danger');
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($nama) ?></strong></td>
                        <td class="text-muted" style="font-size:12px;"><?= htmlspecialchars($data['nip']) ?></td>
                        <td><?= $data['total'] ?></td>
                        <td class="text-success fw-bold"><?= $data['tepat_waktu'] ?></td>
                        <td class="text-warning"><?= $data['terlambat'] ?></td>
                        <td class="text-danger"><?= $data['tidak_hadir'] ?></td>
                        <td><?= $data['digantikan'] ?></td>
                        <td style="min-width:150px;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px;">
                                    <div class="progress-bar <?= $barColor ?>" style="width:<?= $disiplin ?>%"></div>
                                </div>
                                <small class="fw-bold"><?= $disiplin ?>%</small>
                            </div>
                        </td>
                        <td style="font-size:12px;">
                            <?php foreach (array_unique($data['mapel_list']) as $ml): ?>
                                <span class="badge badge-primary mb-1"><?= htmlspecialchars($ml) ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detail Monitoring Table -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-list-check me-2"></i>Detail Monitoring Kehadiran</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" id="monitoringTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Guru</th>
                        <th>Mapel</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Jam Masuk</th>
                        <th>Jam Mengajar</th>
                        <th>Durasi</th>
                        <th>Pelapor</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monitoring_data)): ?>
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">Tidak ada data monitoring</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($monitoring_data as $i => $m): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= formatTanggal($m['tanggal']) ?></td>
                        <td><strong><?= htmlspecialchars($m['nama_guru']) ?></strong></td>
                        <td>
                            <span class="badge badge-secondary"><?= htmlspecialchars($m['kode_mapel']) ?></span>
                            <?= htmlspecialchars($m['nama_mapel']) ?>
                        </td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($m['nama_kelas']) ?></span></td>
                        <td>
                            <?php
                            $statusClass = match($m['status_kedatangan']) {
                                'tepat_waktu' => 'badge-success',
                                'terlambat' => 'badge-warning',
                                'tidak_hadir' => 'badge-danger',
                                'digantikan' => 'badge-info',
                                default => 'badge-primary'
                            };
                            $statusLabel = match($m['status_kedatangan']) {
                                'tepat_waktu' => 'Tepat Waktu',
                                'terlambat' => 'Terlambat',
                                'tidak_hadir' => 'Tidak Hadir',
                                'digantikan' => 'Digantikan',
                                default => ucfirst($m['status_kedatangan'])
                            };
                            ?>
                            <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                        </td>
                        <td><?= $m['jam_masuk'] ? formatJam($m['jam_masuk']) : '-' ?></td>
                        <td class="jam-mengajar"><?= $m['jam_mengajar_mulai'] ? formatJam($m['jam_mengajar_mulai']) . ' - ' . formatJam($m['jam_mengajar_selesai']) : '-' ?></td>
                        <td><?= $m['durasi_mengajar'] ? $m['durasi_mengajar'] . ' mnt' : '-' ?></td>
                        <td class="text-muted"><?= htmlspecialchars($m['nama_pelapor'] ?? '-') ?></td>
                        <td class="d-flex gap-1">
                            <?php
                            $jamA = $m['jam_mengajar_mulai'] ?: ($m['jadwal_mulai'] ?? '');
                            $jamB = $m['jam_mengajar_selesai'] ?: ($m['jadwal_selesai'] ?? '');
                            $wa = null;
                            if ($jamA && $jamB) {
                                $pesan = pesanPengingatMengajar(
                                    $m['nama_guru'], namaHari($m['tanggal']), $m['tanggal'],
                                    formatJam($jamA), formatJam($jamB),
                                    $m['nama_kelas'], $m['kode_mapel'] . ' - ' . $m['nama_mapel'],
                                    $m['ruangan'] ?? ''
                                );
                                $wa = waLink($m['no_hp'], $pesan);
                            }
                            ?>
                            <?php if ($wa): ?>
                            <a href="<?= $wa ?>" target="_blank" class="btn btn-sm wa-btn btn-icon" title="Kirim pengingat jam mengajar via WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <?php else: ?>
                            <a href="<?= BASE_URL ?>/admin/guru.php?search=<?= urlencode($m['nama_guru']) ?>" class="btn btn-sm wa-btn wa-btn-missing btn-icon" title="No. HP <?= htmlspecialchars($m['nama_guru']) ?> belum diisi — klik untuk lengkapi di halaman Guru">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <?php endif; ?>
                            <form method="POST" class="d-inline ajax-form" data-confirm="Hapus data ini?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $m['id'] ?>">
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
</div><!-- /ajax-area -->

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Input Monitoring Kehadiran Guru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal" id="tanggalMonitoring" value="<?= date('Y-m-d') ?>" required onchange="filterJadwalByDay('tanggalMonitoring','jadwalSelect')">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dilaporkan Oleh <span class="text-danger">*</span></label>
                            <select class="form-select" name="dilaporkan_oleh" required>
                                <option value="">-- Pilih Guru Piket --</option>
                                <?php foreach ($guru_list as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jadwal Mengajar <span class="text-danger">*</span></label>
                        <select class="form-select" name="jadwal_id" required id="jadwalSelect" onchange="autoFillMonitoring(this)">
                            <option value="">-- Pilih Jadwal --</option>
                            <?php foreach ($jadwal_list as $j): ?>
                            <option value="<?= $j['id'] ?>" 
                                    data-guru="<?= $j['guru_id'] ?>" 
                                    data-kelas="<?= $j['kelas_id'] ?>" 
                                    data-mapel="<?= $j['mapel_id'] ?>"
                                    data-nama-guru="<?= htmlspecialchars($j['nama_guru']) ?>"
                                    data-hari="<?= $j['hari'] ?>"
                                    data-jam-mulai="<?= formatJam($j['jam_mulai']) ?>"
                                    data-jam-selesai="<?= formatJam($j['jam_selesai']) ?>"
                                    title="<?= $j['hari'] ?> <?= formatJam($j['jam_mulai']) ?>-<?= formatJam($j['jam_selesai']) ?>">
                                <?= htmlspecialchars($j['nama_guru']) ?> - <?= htmlspecialchars($j['nama_mapel']) ?> - <?= htmlspecialchars($j['nama_kelas']) ?> · <?= formatJam($j['jam_mulai']) ?>-<?= formatJam($j['jam_selesai']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Guru Mengajar</label>
                            <input type="text" class="form-control" id="guruInfo" readonly style="background:var(--bg-glass);">
                            <input type="hidden" name="guru_id" id="guru_id_input">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kelas</label>
                            <select class="form-select" name="kelas_id" id="kelas_id_input">
                                <?php foreach ($kelas_list_all as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Mata Pelajaran</label>
                            <select class="form-select" name="mapel_id" id="mapel_id_input">
                                <?php foreach ($mapel_list as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <hr style="border-color:var(--border-color);">
                    <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Data Kedatangan</h6>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status Kedatangan <span class="text-danger">*</span></label>
                            <select class="form-select" name="status_kedatangan" required id="statusKedatangan">
                                <option value="tepat_waktu">Tepat Waktu</option>
                                <option value="terlambat">Terlambat</option>
                                <option value="tidak_hadir">Tidak Hadir</option>
                                <option value="digantikan">Digantikan</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam Masuk</label>
                            <input type="time" class="form-control" name="jam_masuk">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam Mengajar Selesai <small class="text-secondary">(otomatis dari jadwal)</small></label>
                            <input type="time" class="form-control" name="jam_mengajar_selesai" id="jam_mengajar_selesai">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Durasi (menit)</label>
                            <input type="number" class="form-control" name="durasi_mengajar" value="90">
                        </div>
                    </div>
                    <input type="hidden" name="jam_mengajar_mulai" id="jam_mengajar_mulai">
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" name="catatan" rows="2"></textarea>
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

<script>
function autoFillMonitoring(select) {
    const opt = select.options[select.selectedIndex];
    if (opt.value) {
        document.getElementById('guruInfo').value = opt.dataset.namaGuru;
        document.getElementById('guru_id_input').value = opt.dataset.guru;
        document.getElementById('kelas_id_input').value = opt.dataset.kelas;
        document.getElementById('mapel_id_input').value = opt.dataset.mapel;
        document.getElementById('jam_mengajar_mulai').value = opt.dataset.jamMulai || '';
        document.getElementById('jam_mengajar_selesai').value = opt.dataset.jamSelesai || '';
    }
}

function filterJadwalByDay(dateId, selectId) {
    const tgl = document.getElementById(dateId);
    const sel = document.getElementById(selectId);
    if (!tgl || !sel) return;
    const namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][new Date(tgl.value + 'T00:00:00').getDay()];
    sel.selectedIndex = 0;
    [...sel.options].forEach(o => {
        if (!o.value) return; // opsi placeholder
        o.hidden = o.dataset.hari !== namaHari;
    });
}

document.addEventListener('DOMContentLoaded', () => filterJadwalByDay('tanggalMonitoring', 'jadwalSelect'));
document.getElementById('addModal').addEventListener('show.bs.modal', () => filterJadwalByDay('tanggalMonitoring', 'jadwalSelect'));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
