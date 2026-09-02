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

// Status tiap slot untuk timeline hari ini
$slot_done = 0;
foreach ($slot_list as &$s) {
    $st = strtotime($s['mulai']);
    $en = strtotime($s['selesai']);
    if ($now_ts > $en)      { $s['status'] = 'selesai'; $slot_done++; }
    elseif ($now_ts >= $st) { $s['status'] = 'berlangsung'; }
    else                    { $s['status'] = 'berikutnya'; }
}
unset($s);
$slot_total = count($slot_list);
$total_jp_hari_ini = count($jadwal_hari_ini_list);

// Statistik status kehadiran hari ini
$hadir_count = $conn->query("SELECT COUNT(*) FROM monitoring_kehadiran WHERE tanggal=CURDATE() AND status_kedatangan='tepat_waktu'")->fetch_row()[0];
$terlambat_count = $conn->query("SELECT COUNT(*) FROM monitoring_kehadiran WHERE tanggal=CURDATE() AND status_kedatangan='terlambat'")->fetch_row()[0];
$tidak_hadir_count = $conn->query("SELECT COUNT(*) FROM monitoring_kehadiran WHERE tanggal=CURDATE() AND status_kedatangan='tidak_hadir'")->fetch_row()[0];
$total_monitoring = $hadir_count + $terlambat_count + $tidak_hadir_count;

// Statistik kelas kosong
$kk_hari_ini = (int) $conn->query("SELECT COUNT(*) FROM kelas_kosong WHERE tanggal=CURDATE()")->fetch_row()[0];
$kk_belum = (int) $conn->query("SELECT COUNT(*) FROM kelas_kosong WHERE tanggal=CURDATE() AND tindakan='belum_ditangani'")->fetch_row()[0];
$kk_sudah = $kk_hari_ini - $kk_belum;
$kk_total = (int) $conn->query("SELECT COUNT(*) FROM kelas_kosong")->fetch_row()[0];
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

<!-- Panel Jadwal Mengajar Hari Ini (timeline) -->
<div class="card mb-4">
    <div class="card-header">
        <h5><i class="bi bi-alarm me-2"></i>Jadwal Mengajar Hari Ini — <?= $hari_ini ?></h5>
        <div class="d-flex align-items-center gap-3">
            <?php if (!empty($cd_slot)):
                $cd_mulai = $cd_date . 'T' . $cd_slot['mulai'];
                $cd_selesai = $cd_date . 'T' . $cd_slot['selesai'];
                $cd_status = $current_slot ? 'berlangsung' : 'berikutnya';
            ?>
            <div class="text-end d-none d-md-block">
                <div class="text-secondary small fw-semibold" id="cdLabel">
                    <?= $current_slot ? '<i class="bi bi-broadcast me-1"></i>Sisa waktu mengajar' : '<i class="bi bi-hourglass-split me-1"></i>Menuju jam berikutnya' ?>
                </div>
                <div class="fw-bold" id="cdDisplay" data-mulai="<?= $cd_mulai ?>" data-selesai="<?= $cd_selesai ?>" data-status="<?= $cd_status ?>" style="font-size:1.25rem;color:#67e8f9;font-variant-numeric:tabular-nums;line-height:1.15;">--:--:--</div>
            </div>
            <?php endif; ?>
            <a href="jadwal.php" class="btn btn-sm btn-outline-primary">Lihat Jadwal</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($slot_list)): ?>
        <div class="empty-state py-4">
            <i class="bi bi-calendar-check"></i>
            <p class="mb-0">Tidak ada jadwal mengajar hari ini.</p>
        </div>
        <?php else:
            $pct_day = $slot_total > 0 ? round(($slot_done / $slot_total) * 100) : 0;
        ?>
        <!-- Ringkasan hari -->
        <div class="d-flex flex-wrap align-items-center gap-3 mb-3 day-summary">
            <span class="text-secondary small"><i class="bi bi-check2-circle text-success me-1"></i><strong><?= $slot_done ?></strong> dari <?= $slot_total ?> slot selesai</span>
            <span class="text-secondary small"><i class="bi bi-hourglass-top me-1"></i><?= $total_jp_hari_ini ?> JP mengajar</span>
            <div class="progress flex-grow-1" style="min-width:140px;height:6px;">
                <div class="progress-bar bg-success" style="width: <?= $pct_day ?>%"></div>
            </div>
            <span class="text-secondary small"><?= $pct_day ?>%</span>
        </div>
        <!-- Timeline slot -->
        <div class="sch-day-list">
            <?php foreach ($slot_list as $s):
                $rowClass = match($s['status']) { 'berlangsung' => 'sch-live', 'berikutnya' => 'sch-next', default => 'sch-done' };
                $stChip = match($s['status']) { 'berlangsung' => 'badge-success', 'berikutnya' => 'badge-warning', default => 'badge-secondary' };
                $stLabel = match($s['status']) { 'berlangsung' => 'Sedang Berlangsung', 'berikutnya' => 'Jam Berikutnya', default => 'Selesai' };
            ?>
            <div class="sch-day-row <?= $rowClass ?>">
                <div class="sch-day-time"><?= formatJam($s['mulai']) ?><span class="sch-day-sep">–</span><?= formatJam($s['selesai']) ?></div>
                <div class="sch-day-status"><span class="badge <?= $stChip ?>"><?= $stLabel ?></span></div>
                <div class="sch-day-items">
                    <?php foreach ($s['daftar'] as $cdj): ?>
                    <span class="sch-day-chip" title="<?= htmlspecialchars($cdj['nama_kelas'] . ' · ' . $cdj['nama_mapel'] . ' · ' . $cdj['nama_guru']) ?>">
                        <span class="sch-day-kelas"><?= htmlspecialchars($cdj['nama_kelas']) ?></span>
                        <span class="sch-day-mapel"><?= htmlspecialchars($cdj['nama_mapel']) ?></span>
                        <span class="sch-day-guru"><?= htmlspecialchars($cdj['nama_guru']) ?></span>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Kartu Monitoring: Kelas Kosong -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box orange"><i class="bi bi-door-open-fill"></i></div>
            <div class="stat-value"><?= $kk_hari_ini ?></div>
            <div class="stat-label">Kelas Kosong Hari Ini</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-value"><?= $kk_belum ?></div>
            <div class="stat-label">Belum Ditangani</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $kk_sudah ?></div>
            <div class="stat-label">Sudah Ditangani</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box cyan"><i class="bi bi-clipboard-data-fill"></i></div>
            <div class="stat-value"><?= $kk_total ?></div>
            <div class="stat-label">Total Kelas Kosong</div>
        </div>
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
                                <?php // Utamakan jam terekam (1 entri per blok JP), fallback ke slot jadwal
                                $jm_masuk = $m['jam_mengajar_mulai'] ?: ($m['jadwal_mulai'] ?? '');
                                $jm_selesai = $m['jam_mengajar_selesai'] ?: ($m['jadwal_selesai'] ?? ''); ?>
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
        const target = berlangsung ? selesai - now : mulai - now;
        // Slot selesai / jam mulai tiba -> muat ulang agar status timeline segar
        if (target < 0) { location.reload(); return; }
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