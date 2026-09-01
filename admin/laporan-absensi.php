<?php
$page_title = 'Laporan Absensi Guru';
$page_subtitle = 'Laporan kehadiran guru mengajar per kelas dan mata pelajaran';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

// Filter
$tgl_mulai = sanitize($_GET['tgl_mulai'] ?? date('Y-m-d', strtotime('-7 days')));
$tgl_selesai = sanitize($_GET['tgl_selesai'] ?? date('Y-m-d'));
$kelas_filter = $_GET['kelas_id'] ?? '';
$guru_filter = $_GET['guru_id'] ?? '';
$mapel_filter = $_GET['mapel_id'] ?? '';
$search = sanitize($_GET['search'] ?? '');

$where = "WHERE mk.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
if ($kelas_filter) $where .= " AND mk.kelas_id = " . intval($kelas_filter);
if ($guru_filter) $where .= " AND mk.guru_id = " . intval($guru_filter);
if ($mapel_filter) $where .= " AND mk.mapel_id = " . intval($mapel_filter);
if ($search) $where .= " AND (g.nama_guru LIKE '%$search%' OR k.nama_kelas LIKE '%$search%' OR mp.nama_mapel LIKE '%$search%')";

// Data absensi guru mengajar
$absensi_data = $conn->query("
    SELECT mk.*, g.nama_guru, g.nip, k.nama_kelas, k.tingkat,
           mp.nama_mapel, mp.kode_mapel
    FROM monitoring_kehadiran mk
    JOIN guru g ON mk.guru_id = g.id
    JOIN kelas k ON mk.kelas_id = k.id
    JOIN mata_pelajaran mp ON mk.mapel_id = mp.id
    $where
    ORDER BY mk.tanggal DESC, k.nama_kelas, mk.jam_mengajar_mulai
")->fetch_all(MYSQLI_ASSOC);

// Statistik
$total = count($absensi_data);
$tepat = count(array_filter($absensi_data, fn($a) => $a['status_kedatangan'] === 'tepat_waktu'));
$terlambat = count(array_filter($absensi_data, fn($a) => $a['status_kedatangan'] === 'terlambat'));
$tidak = count(array_filter($absensi_data, fn($a) => $a['status_kedatangan'] === 'tidak_hadir'));
$diganti = count(array_filter($absensi_data, fn($a) => $a['status_kedatangan'] === 'digantikan'));
$persen_tepat = $total > 0 ? round(($tepat / $total) * 100, 1) : 0;

// Rekap per kelas
$status_map = [
    'tepat_waktu' => 'tepat',
    'terlambat'   => 'terlambat',
    'tidak_hadir' => 'tidak',
    'digantikan'  => 'diganti',
];
$per_kelas = [];
foreach ($absensi_data as $a) {
    $key = $a['nama_kelas'];
    if (!isset($per_kelas[$key])) $per_kelas[$key] = ['tepat' => 0, 'terlambat' => 0, 'tidak' => 0, 'diganti' => 0, 'total' => 0];
    $per_kelas[$key][$status_map[$a['status_kedatangan']] ?? 'tidak']++;
    $per_kelas[$key]['total']++;
}

$guru_list = $conn->query("SELECT id, nama_guru FROM guru WHERE status='aktif' ORDER BY nama_guru")->fetch_all(MYSQLI_ASSOC);
$kelas_list_all = $conn->query("SELECT id, nama_kelas FROM kelas WHERE status='aktif' ORDER BY tingkat, nama_kelas")->fetch_all(MYSQLI_ASSOC);
$mapel_list = $conn->query("SELECT id, kode_mapel, nama_mapel FROM mata_pelajaran WHERE status='aktif' ORDER BY kode_mapel")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Report Header (print) -->
<div class="report-header">
    <h2>LAPORAN ABSENSI GURU MENGAJAR</h2>
    <p>SMK PK TGI JAKARTA | Periode: <?= formatTanggal($tgl_mulai) ?> s/d <?= formatTanggal($tgl_selesai) ?></p>
    <p>Dicetak: <?= formatTanggal(date('Y-m-d')) ?> Jam <?= date('H:i') ?></p>
</div>

<!-- Filter -->
<div id="ajax-area">
<div class="card mb-4" id="filterSection">
    <div class="filter-bar">
        <form class="d-flex gap-2 flex-wrap ajax-filter" method="GET">
            <input type="date" class="form-control" name="tgl_mulai" value="<?= $tgl_mulai ?>" style="width:170px;">
            <span class="text-secondary align-self-center">s/d</span>
            <input type="date" class="form-control" name="tgl_selesai" value="<?= $tgl_selesai ?>" style="width:170px;">
            <select class="form-select" name="kelas_id" style="width:160px;">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelas_list_all as $k): ?>
                <option value="<?= $k['id'] ?>" <?= $kelas_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="guru_id" style="width:180px;">
                <option value="">Semua Guru</option>
                <?php foreach ($guru_list as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $guru_filter == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_guru']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="mapel_id" style="width:160px;">
                <option value="">Semua Mapel</option>
                <?php foreach ($mapel_list as $m): ?>
                <option value="<?= $m['id'] ?>" <?= $mapel_filter == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['kode_mapel'] . ' - ' . $m['nama_mapel']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control" name="search" placeholder="Cari guru / kelas / mapel..." value="<?= htmlspecialchars($search) ?>" style="width:180px;">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="laporan-absensi.php" class="btn btn-outline-light">Reset</a>
        </form>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-light" onclick="exportCSV('laporanAbsensiTable', 'laporan_absensi_guru')">
                <i class="bi bi-download me-1"></i>CSV
            </button>
            <button class="btn btn-primary" onclick="printReport()">
                <i class="bi bi-printer me-1"></i>Cetak
            </button>
        </div>
    </div>
</div>

<!-- Statistik -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $tepat ?></div>
            <div class="stat-label">Tepat Waktu</div>
            <div class="stat-change up"><?= $persen_tepat ?>% dari total</div>
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
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box cyan"><i class="bi bi-arrow-repeat"></i></div>
            <div class="stat-value"><?= $diganti ?></div>
            <div class="stat-label">Digantikan</div>
            <div class="stat-change text-secondary">Total: <?= $total ?> absensi</div>
        </div>
    </div>
</div>

<!-- Persentase per Kelas -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Persentase Ketepatan Mengajar per Kelas</h5>
    </div>
    <div class="card-body">
        <?php foreach ($per_kelas as $nama => $data):
            $persen = $data['total'] > 0 ? round(($data['tepat'] / $data['total']) * 100) : 0;
            $barColor = $persen >= 90 ? 'bg-success' : ($persen >= 70 ? 'bg-warning' : 'bg-danger');
        ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between mb-1">
                <span><strong><?= htmlspecialchars($nama) ?></strong></span>
                <span><?= $data['tepat'] ?>/<?= $data['total'] ?> (<?= $persen ?>%)</span>
            </div>
            <div class="progress" style="height:20px;">
                <div class="progress-bar <?= $barColor ?> d-flex align-items-center justify-content-center" style="width:<?= $persen ?>%">
                    <?= $persen ?>%
                </div>
            </div>
            <div class="d-flex gap-3 mt-1" style="font-size:12px;">
                <span class="text-success"><i class="bi bi-circle-fill" style="font-size:8px;"></i> Tepat: <?= $data['tepat'] ?></span>
                <span class="text-warning"><i class="bi bi-circle-fill" style="font-size:8px;"></i> Terlambat: <?= $data['terlambat'] ?></span>
                <span class="text-danger"><i class="bi bi-circle-fill" style="font-size:8px;"></i> Tidak: <?= $data['tidak'] ?></span>
                <span style="color:var(--info);"><i class="bi bi-circle-fill" style="font-size:8px;"></i> Diganti: <?= $data['diganti'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Detail -->
<div class="card">
    <div class="card-header">
        <h5>Detail Absensi Guru Mengajar</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" id="laporanAbsensiTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Guru</th>
                        <th>Mata Pelajaran</th>
                        <th>Kelas</th>
                        <th>Jam Mengajar</th>
                        <th>Status</th>
                        <th>Jam Masuk</th>
                        <th>Durasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($absensi_data)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Tidak ada data absensi guru pada periode ini</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($absensi_data as $i => $a): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= formatTanggal($a['tanggal']) ?></td>
                        <td><strong><?= htmlspecialchars($a['nama_guru']) ?></strong></td>
                        <td><span class="mapel-kode-inline"><?= htmlspecialchars($a['kode_mapel']) ?></span> <?= htmlspecialchars($a['nama_mapel']) ?></td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($a['nama_kelas']) ?></span></td>
                        <td class="jam-mengajar">
                            <?php if ($a['jam_mengajar_mulai']): ?>
                                <?= formatJam($a['jam_mengajar_mulai']) ?>-<?= formatJam($a['jam_mengajar_selesai']) ?>
                            <?php else: ?>-<?php endif; ?>
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
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div></div><!-- /ajax-area -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
