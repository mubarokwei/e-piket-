<?php
$page_title = 'Dashboard';
$page_subtitle = 'Selamat datang di Sistem E-Piket';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

// Statistik ringkas
$total_guru = $conn->query("SELECT COUNT(*) FROM guru WHERE status='aktif'")->fetch_row()[0];
$total_kelas = $conn->query("SELECT COUNT(*) FROM kelas WHERE status='aktif'")->fetch_row()[0];
$total_mapel = $conn->query("SELECT COUNT(*) FROM mata_pelajaran WHERE status='aktif'")->fetch_row()[0];
$hari_indo = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hari_ini = $hari_indo[(int)date('w')];
$jadwal_hari_ini = $conn->query("SELECT COUNT(*) FROM jadwal_mengajar WHERE hari = '" . $hari_ini . "'")->fetch_row()[0];

// Guru piket hari ini
$guru_piket_today = $conn->query("
    SELECT jp.*, g.nama_guru 
    FROM jadwal_piket jp 
    JOIN guru g ON jp.guru_id = g.id 
    WHERE jp.tanggal = CURDATE()
")->fetch_all(MYSQLI_ASSOC);

// Monitoring kehadiran hari ini (5 terakhir)
$monitoring_terakhir = $conn->query("
    SELECT mk.*, g.nama_guru, k.nama_kelas, mp.nama_mapel,
           gp.nama_guru AS pelapor,
           jm.jam_mulai AS jadwal_mulai, jm.jam_selesai AS jadwal_selesai
    FROM monitoring_kehadiran mk
    JOIN guru g ON mk.guru_id = g.id
    JOIN kelas k ON mk.kelas_id = k.id
    JOIN mata_pelajaran mp ON mk.mapel_id = mp.id
    LEFT JOIN guru gp ON mk.dilaporkan_oleh = gp.id
    LEFT JOIN jadwal_mengajar jm ON mk.jadwal_id = jm.id
    WHERE mk.tanggal = CURDATE()
    ORDER BY mk.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Jadwal mengajar hari ini (untuk panel countdown)
$jadwal_hari_ini_list = $conn->query("
    SELECT jm.id, jm.hari, jm.jam_mulai, jm.jam_selesai,
           g.nama_guru, k.nama_kelas, mp.nama_mapel
    FROM jadwal_mengajar jm
    JOIN guru g ON jm.guru_id = g.id
    JOIN kelas k ON jm.kelas_id = k.id
    JOIN mata_pelajaran mp ON jm.mapel_id = mp.id
    WHERE jm.hari = '$hari_ini' AND jm.status = 'aktif'
    ORDER BY jm.jam_mulai
")->fetch_all(MYSQLI_ASSOC);

// Kelompokkan per slot waktu (banyak kelas paralel per jam)
$slot_list = [];
foreach ($jadwal_hari_ini_list as $j) {
    $key = $j['jam_mulai'] . '|' . $j['jam_selesai'];
    if (!isset($slot_list[$key])) {
        $slot_list[$key] = ['mulai' => $j['jam_mulai'], 'selesai' => $j['jam_selesai'], 'daftar' => []];
    }
    $slot_list[$key]['daftar'][] = $j;
}
$slot_list = array_values($slot_list);
$now_ts = time();
$current_slot = null;
$next_slot = null;
foreach ($slot_list as $s) {
    $st = strtotime($s['mulai']);
    $en = strtotime($s['selesai']);
    if ($now_ts >= $st && $now_ts <= $en) { $current_slot = $s; break; }
}
if (!$current_slot) {
    foreach ($slot_list as $s) {
        if ($now_ts < strtotime($s['mulai'])) { $next_slot = $s; break; }
    }
}
$cd_slot = $current_slot ?? $next_slot;
$cd_date = date('Y-m-d');

// Statistik status kehadiran hari ini
$hadir_count = $conn->query("SELECT COUNT(*) FROM monitoring_kehadiran WHERE tanggal=CURDATE() AND status_kedatangan='tepat_waktu'")->fetch_row()[0];
$terlambat_count = $conn->query("SELECT COUNT(*) FROM monitoring_kehadiran WHERE tanggal=CURDATE() AND status_kedatangan='terlambat'")->fetch_row()[0];
$tidak_hadir_count = $conn->query("SELECT COUNT(*) FROM monitoring_kehadiran WHERE tanggal=CURDATE() AND status_kedatangan='tidak_hadir'")->fetch_row()[0];
$total_monitoring = $hadir_count + $terlambat_count + $tidak_hadir_count;
?>

<!-- Statistik Ringkas -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box blue"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $total_guru ?></div>
            <div class="stat-label">Total Guru Aktif</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box green"><i class="bi bi-building"></i></div>
            <div class="stat-value"><?= $total_kelas ?></div>
            <div class="stat-label">Total Kelas</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box purple"><i class="bi bi-book-fill"></i></div>
            <div class="stat-value"><?= $total_mapel ?></div>
            <div class="stat-label">Mata Pelajaran</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box cyan"><i class="bi bi-calendar-week-fill"></i></div>
            <div class="stat-value"><?= $jadwal_hari_ini ?></div>
            <div class="stat-label">Jadwal Hari Ini</div>
        </div>
    </div>
</div>

<!-- Panel Countdown Jadwal Hari Ini -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-alarm me-2"></i>Jadwal Mengajar Hari Ini — <?= $hari_ini ?></h5>
        <a href="jadwal.php" class="btn btn-sm btn-outline-primary">Lihat Jadwal</a>
    </div>
    <div class="card-body">
        <?php if (empty($cd_slot)): ?>
        <div class="empty-state py-4">
            <i class="bi bi-calendar-check"></i>
            <p class="mb-0"><?= empty($slot_list) ? 'Tidak ada jadwal mengajar hari ini.' : 'Semua jadwal mengajar hari ini telah selesai.' ?></p>
        </div>
        <?php else: ?>
        <?php
        $cd_mulai = $cd_date . 'T' . $cd_slot['mulai'];
        $cd_selesai = $cd_date . 'T' . $cd_slot['selesai'];
        $cd_status = $current_slot ? 'berlangsung' : 'berikutnya';
        ?>
        <div class="d-flex flex-wrap align-items-center gap-4">
            <div class="countdown-jam">
                <div class="text-secondary text-uppercase small fw-semibold mb-1">
                    <?= $current_slot ? '<i class="bi bi-broadcast me-1"></i>Sedang Berlangsung' : '<i class="bi bi-hourglass-split me-1"></i>Jam Berikutnya' ?>
                </div>
                <div class="fw-bold" style="font-size:1.4rem;color:var(--text-primary);">
                    <?= formatJam($cd_slot['mulai']) ?> – <?= formatJam($cd_slot['selesai']) ?>
                </div>
                <div class="text-secondary small mt-1"><?= count($cd_slot['daftar']) ?> kelas pada jam ini</div>
            </div>
            <div class="countdown-timer text-center">
                <div class="text-secondary text-uppercase small fw-semibold mb-1">
                    <?= $current_slot ? 'Sisa Waktu Mengajar' : 'Menuju Jam Mengajar' ?>
                </div>
                <div class="fw-bold" id="cdDisplay" data-mulai="<?= $cd_mulai ?>" data-selesai="<?= $cd_selesai ?>" data-status="<?= $cd_status ?>" style="font-size:2.2rem;color:#67e8f9;font-variant-numeric:tabular-nums;">--:--:--</div>
            </div>
            <div class="d-flex flex-wrap gap-1" style="flex:1;min-width:220px;">
                <?php foreach ($cd_slot['daftar'] as $cdj): ?>
                <span class="badge badge-secondary" title="<?= htmlspecialchars($cdj['nama_guru']) ?>"><?= htmlspecialchars($cdj['nama_kelas']) ?> · <?= htmlspecialchars($cdj['nama_mapel']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Konten Utama: Monitoring + Panel Kanan -->
<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5>Monitoring Kehadiran Guru Hari Ini</h5>
                <a href="monitoring.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Guru</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Status</th>
                                <th>Jam Masuk</th>
                                <th>Jam Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($monitoring_terakhir)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <h5>Belum Ada Data Monitoring</h5>
                                        <p>Belum ada monitoring kehadiran guru yang dilaporkan hari ini.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($monitoring_terakhir as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['nama_guru']) ?></strong></td>
                                <td><?= htmlspecialchars($m['nama_mapel']) ?></td>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($m['nama_kelas']) ?></span></td>
                                <td>
                                    <?php
                                    $statusClass = match($m['status_kedatangan']) {
                                        'tepat_waktu' => 'badge-success',
                                        'terlambat'    => 'badge-warning',
                                        'tidak_hadir'  => 'badge-danger',
                                        default        => 'badge-info'
                                    };
                                    $statusLabel = match($m['status_kedatangan']) {
                                        'tepat_waktu' => 'Tepat Waktu',
                                        'terlambat'    => 'Terlambat',
                                        'tidak_hadir'  => 'Tidak Hadir',
                                        default        => 'Digantikan'
                                    };
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span>
                                </td>
                                <?php $jm_masuk = $m['jadwal_mulai'] ?: ($m['jam_mengajar_mulai'] ?? ''); ?>
                                <?php $jm_selesai = $m['jadwal_selesai'] ?: ($m['jam_mengajar_selesai'] ?? ''); ?>
                                <td class="jam-mengajar"><?= $jm_masuk ? formatJam($jm_masuk) : '-' ?></td>
                                <td class="jam-mengajar"><?= $jm_selesai ? formatJam($jm_selesai) : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <!-- Guru Piket Hari Ini -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Guru Piket Hari Ini</h5>
            </div>
            <div class="card-body">
                <?php if (empty($guru_piket_today)): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-person-x"></i>
                    <p class="mb-0">Tidak ada jadwal piket hari ini.</p>
                </div>
                <?php else: ?>
                <?php foreach ($guru_piket_today as $gp): ?>
                <div class="d-flex align-items-center justify-content-between p-3 mb-2 guru-piket-item">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon-box blue icon-sm">
                            <i class="bi bi-person-circle"></i>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($gp['nama_guru']) ?></strong>
                            <div class="d-flex gap-1 mt-1">
                                <?php if ($gp['shift_pagi']): ?>
                                    <span class="badge badge-info">Pagi</span>
                                <?php endif; ?>
                                <?php if ($gp['shift_siang']): ?>
                                    <span class="badge badge-warning">Siang</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <span class="badge badge-success"><span class="status-dot active"></span>Hadir</span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status Kehadiran Hari Ini -->
        <div class="card">
            <div class="card-header">
                <h5>Status Kehadiran Guru</h5>
            </div>
            <div class="card-body">
                <?php
                $pct_hadir = $total_monitoring > 0 ? round(($hadir_count / $total_monitoring) * 100) : 0;
                $pct_terlambat = $total_monitoring > 0 ? round(($terlambat_count / $total_monitoring) * 100) : 0;
                $pct_tidak = $total_monitoring > 0 ? round(($tidak_hadir_count / $total_monitoring) * 100) : 0;
                ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-check-circle-fill text-success me-2"></i>Tepat Waktu</span>
                        <strong><?= $hadir_count ?> <span class="stat-percent">(<?= $pct_hadir ?>%)</span></strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" style="width: <?= $pct_hadir ?>%"></div>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-clock-fill text-warning me-2"></i>Terlambat</span>
                        <strong><?= $terlambat_count ?> <span class="stat-percent">(<?= $pct_terlambat ?>%)</span></strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" style="width: <?= $pct_terlambat ?>%"></div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><i class="bi bi-x-circle-fill text-danger me-2"></i>Tidak Hadir</span>
                        <strong><?= $tidak_hadir_count ?> <span class="stat-percent">(<?= $pct_tidak ?>%)</span></strong>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-danger" style="width: <?= $pct_tidak ?>%"></div>
                    </div>
                </div>
                <div class="text-center pt-3 status-total">
                    Total <?= $total_monitoring ?> monitoring kehadiran hari ini
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const el = document.getElementById('cdDisplay');
    if (!el) return;
    const mulai = new Date(el.dataset.mulai).getTime();
    const selesai = new Date(el.dataset.selesai).getTime();
    const berlangsung = el.dataset.status === 'berlangsung';

    function pad(n) { return String(n).padStart(2, '0'); }
    function render() {
        const now = Date.now();
        let target, done = false;
        if (berlangsung) {
            target = selesai - now;
            if (target < 0) { el.textContent = '00:00:00'; el.closest('.card').querySelector('.empty-state') || setTimeout(render, 1000); return; }
        } else {
            target = mulai - now;
            if (target < 0) { location.reload(); return; }
        }
        const h = Math.floor(target / 3600000);
        const m = Math.floor((target % 3600000) / 60000);
        const s = Math.floor((target % 60000) / 1000);
        el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
        setTimeout(render, 1000);
    }
    render();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>