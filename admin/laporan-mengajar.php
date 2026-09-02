<?php
$page_title = 'Laporan Mengajar';
$page_subtitle = 'Laporan guru mengajar per kelas';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

// Filter
$kelas_filter = $_GET['kelas_id'] ?? '';
$search = sanitize($_GET['search'] ?? '');

$where = "WHERE jm.status = 'aktif'";
if ($kelas_filter) $where .= " AND jm.kelas_id = " . intval($kelas_filter);
if ($search) $where .= " AND (g.nama_guru LIKE '%$search%' OR k.nama_kelas LIKE '%$search%' OR mp.nama_mapel LIKE '%$search%')";

// Data jadwal mengajar
$jadwal_mengajar = $conn->query("
    SELECT jm.*, g.nama_guru, g.nip, k.nama_kelas, k.tingkat, mp.nama_mapel, mp.kode_mapel, mp.jam_per_minggu
    FROM jadwal_mengajar jm
    JOIN guru g ON jm.guru_id = g.id
    JOIN kelas k ON jm.kelas_id = k.id
    JOIN mata_pelajaran mp ON jm.mapel_id = mp.id
    $where
    ORDER BY k.tingkat, k.nama_kelas, jm.hari, jm.jam_mulai
")->fetch_all(MYSQLI_ASSOC);

// Statistik
$total_jadwal = count($jadwal_mengajar);
$guru_aktif = count(array_unique(array_column($jadwal_mengajar, 'guru_id')));
$kelas_terjadwal = count(array_unique(array_column($jadwal_mengajar, 'kelas_id')));

// Data untuk filter
$kelas_list_all = $conn->query("SELECT id, nama_kelas FROM kelas WHERE status='aktif' ORDER BY tingkat, nama_kelas")->fetch_all(MYSQLI_ASSOC);

// Group by kelas
$jadwal_per_kelas = [];
foreach ($jadwal_mengajar as $j) {
    $jadwal_per_kelas[$j['nama_kelas']][] = $j;
}

// Group by guru
$jadwal_per_guru = [];
foreach ($jadwal_mengajar as $j) {
    $jadwal_per_guru[$j['nama_guru']][] = $j;
}
?>

<!-- Report Header (for print) -->
<div class="report-header" id="reportHeader">
    <h2>LAPORAN GURU MENGAJAR PER KELAS</h2>
    <p>SMK PK TGI JAKARTA | Tahun Ajaran <?= date('Y') ?>/<?= date('Y')+1 ?></p>
    <p>Dicetak: <?= formatTanggal(date('Y-m-d')) ?> Jam <?= date('H:i') ?></p>
</div>

<!-- Filter -->
<div id="ajax-area">
<div class="card mb-4" id="filterSection">
    <div class="filter-bar">
        <form class="d-flex gap-2 align-items-center flex-wrap ajax-filter" method="GET" id="filterForm">
            <select class="form-select" name="kelas_id" style="width:200px;">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelas_list_all as $k): ?>
                <option value="<?= $k['id'] ?>" <?= $kelas_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control" name="search" placeholder="Cari guru / mapel..." value="<?= htmlspecialchars($search) ?>" style="width:220px;">
            <button type="button" class="btn btn-sm btn-outline-light ms-auto" onclick="exportCSV('laporanMengajarTable', 'laporan_mengajar')">
                <i class="bi bi-download me-1"></i>CSV
            </button>
            <button type="button" class="btn btn-sm btn-primary" onclick="printReport()">
                <i class="bi bi-printer me-1"></i>Cetak
            </button>
        </form>
    </div>
</div>

<!-- Statistik Ringkas -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box blue"><i class="bi bi-calendar-week"></i></div>
            <div class="stat-value"><?= $total_jadwal ?></div>
            <div class="stat-label">Total Jadwal Mengajar</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box green"><i class="bi bi-people"></i></div>
            <div class="stat-value"><?= $guru_aktif ?></div>
            <div class="stat-label">Guru Mengajar</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box purple"><i class="bi bi-building"></i></div>
            <div class="stat-value"><?= $kelas_terjadwal ?></div>
            <div class="stat-label">Kelas Terjadwal</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box cyan"><i class="bi bi-clock"></i></div>
            <div class="stat-value"><?= $total_jadwal * 90 ?></div>
            <div class="stat-label">Total Jam Mengajar (mnt)</div>
        </div>
    </div>
</div>

<!-- Tabel Laporan per Kelas -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-table me-2"></i>Jadwal Mengajar per Kelas</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" id="laporanMengajarTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kelas</th>
                        <th>Hari</th>
                        <th>Jam</th>
                        <th>Guru</th>
                        <th>NIP</th>
                        <th>Mata Pelajaran</th>
                        <th>Ruangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($jadwal_mengajar as $j): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($j['nama_kelas']) ?></span></td>
                        <td><span class="badge badge-info"><?= $j['hari'] ?></span></td>
                        <td><?= formatJam($j['jam_mulai']) ?> - <?= formatJam($j['jam_selesai']) ?></td>
                        <td><strong><?= htmlspecialchars($j['nama_guru']) ?></strong></td>
                        <td class="text-muted" style="font-size:12px;"><?= htmlspecialchars($j['nip']) ?></td>
                        <td>
                            <span class="badge badge-secondary"><?= htmlspecialchars($j['kode_mapel']) ?></span>
                            <?= htmlspecialchars($j['nama_mapel']) ?>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($j['nama_kelas']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Laporan per Kelas (Detail) -->
<?php foreach ($jadwal_per_kelas as $nama_kelas => $jds): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-building me-2"></i>Kelas <?= htmlspecialchars($nama_kelas) ?></h5>
        <span class="badge badge-primary"><?= count($jds) ?> Jadwal</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Hari</th>
                        <th>Jam</th>
                        <th>Mata Pelajaran</th>
                        <th>Guru</th>
                        <th>Ruangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jds as $j): ?>
                    <tr>
                        <td><span class="badge badge-info"><?= $j['hari'] ?></span></td>
                        <td><?= formatJam($j['jam_mulai']) ?> - <?= formatJam($j['jam_selesai']) ?></td>
                        <td><span class="badge badge-secondary"><?= htmlspecialchars($j['kode_mapel']) ?></span> <?= htmlspecialchars($j['nama_mapel']) ?></td>
                        <td><strong><?= htmlspecialchars($j['nama_guru']) ?></strong></td>
                        <td class="text-muted"><?= htmlspecialchars($j['nama_kelas']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Laporan per Guru -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-person me-2"></i>Rekapitulasi per Guru</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Guru</th>
                        <th>NIP</th>
                        <th>Jumlah Jadwal</th>
                        <th>Kelas yang Diampu</th>
                        <th>Mapel yang Diampu</th>
                        <th>Hari Mengajar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($jadwal_per_guru as $nama_guru => $jds): 
                        $kelas_unique = array_unique(array_column($jds, 'nama_kelas'));
                        $mapel_unique = array_unique(array_column($jds, 'nama_mapel'));
                        $hari_unique = array_unique(array_column($jds, 'hari'));
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= htmlspecialchars($nama_guru) ?></strong></td>
                        <td class="text-muted" style="font-size:12px;"><?= htmlspecialchars($jds[0]['nip']) ?></td>
                        <td><span class="badge badge-primary"><?= count($jds) ?></span></td>
                        <td>
                            <?php foreach ($kelas_unique as $ku): ?>
                                <span class="badge badge-secondary"><?= htmlspecialchars($ku) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach ($mapel_unique as $mu): ?>
                                <span class="badge badge-info"><?= htmlspecialchars($mu) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <?php foreach ($hari_unique as $hu): ?>
                                <span class="badge badge-success"><?= $hu ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div><!-- /ajax-area -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
