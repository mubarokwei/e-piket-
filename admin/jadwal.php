<?php
$page_title = 'Jadwal Mengajar';
$page_subtitle = 'Jadwal mengajar guru per hari dan kelas';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Role Guru hanya boleh MELIHAT jadwal, tidak boleh menambah/menghapus
    if (($current_role ?? '') === 'guru') {
        $msg = 'Role Guru hanya dapat melihat jadwal (tidak boleh menambah/menghapus).';
        flash('danger', $msg);
        if (isAjax()) jsonOut(['success' => false, 'message' => $msg]);
        redirect('jadwal.php');
    }
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $guru = intval($_POST['guru_id']);
        $kelas = intval($_POST['kelas_id']);
        $mapel = intval($_POST['mapel_id']);
        $hari = sanitize($_POST['hari']);
        $ruangan = sanitize($_POST['ruangan']);
        $jumlah_jp = max(1, intval($_POST['jumlah_jp'] ?? 1));

        $isCustom = ($_POST['jam_mulai'] ?? '') === 'custom';
        $jam_mulai = $isCustom ? sanitize($_POST['custom_mulai']) : sanitize($_POST['jam_mulai']);
        $jam_selesai = $isCustom ? sanitize($_POST['custom_selesai']) : null;

        if (!$jam_mulai || ($isCustom && !$jam_selesai)) {
            $msg = 'Lengkapi jam mulai (dan jam selesai untuk mode custom).';
            flash('danger', $msg);
            if (isAjax()) jsonOut(['success' => false, 'message' => $msg]);
            redirect('jadwal.php');
        }

        // Susun daftar slot yang akan dibuat (blok JP berturut-turut atau 1 slot custom)
        $slots = [];
        if ($isCustom) {
            $slots[] = ['m' => $jam_mulai, 's' => $jam_selesai];
        } else {
            // Pola jam hari itu diambil dari data jadwal yang sudah ada (satu sumber kebenaran)
            $chain = [];
            $r = $conn->query("SELECT DISTINCT jam_mulai, jam_selesai FROM jadwal_mengajar WHERE hari='" . $conn->real_escape_string($hari) . "' AND status='aktif' ORDER BY jam_mulai");
            if ($r) foreach ($r as $row) $chain[] = ['m' => $row['jam_mulai'], 's' => $row['jam_selesai']];

            $start = -1;
            foreach ($chain as $i => $s) if ($s['m'] === $jam_mulai) { $start = $i; break; }
            if ($start < 0) {
                $msg = 'Jam mulai tidak cocok dengan pola jam ' . $hari . '. Pilih dari daftar atau gunakan mode Custom.';
                flash('danger', $msg);
                if (isAjax()) jsonOut(['success' => false, 'message' => $msg]);
                redirect('jadwal.php');
            }
            // Rangkai slot berurutan; berhenti bila bertemu jam istirahat (jam tidak menyambung)
            for ($i = $start, $n = 0; $n < $jumlah_jp && $i < count($chain); $i++, $n++) {
                if ($n > 0 && $chain[$i]['m'] !== $chain[$i - 1]['s']) break;
                $slots[] = $chain[$i];
            }
            if (count($slots) < $jumlah_jp) {
                $msg = 'Hanya tersedia ' . count($slots) . ' JP berturut-turut sebelum jam istirahat (mulai ' . formatJam($jam_mulai) . '). Kurangi Jumlah JP.';
                flash('danger', $msg);
                if (isAjax()) jsonOut(['success' => false, 'message' => $msg]);
                redirect('jadwal.php');
            }
        }

        // Cek konflik: kelas atau guru sudah terisi di jam yang sama
        $escHari = $conn->real_escape_string($hari);
        foreach ($slots as $s) {
            $escM = $conn->real_escape_string($s['m']);
            $q = $conn->query("SELECT id FROM jadwal_mengajar WHERE hari='$escHari' AND kelas_id=$kelas AND jam_mulai='$escM' AND status='aktif' LIMIT 1");
            if ($q && $q->num_rows > 0) {
                $msg = 'Kelas sudah terisi di jam ' . formatJam($s['m']) . ' — jadwal tidak jadi disimpan.';
                flash('danger', $msg);
                if (isAjax()) jsonOut(['success' => false, 'message' => $msg]);
                redirect('jadwal.php');
            }
            $q2 = $conn->query("SELECT id FROM jadwal_mengajar WHERE hari='$escHari' AND guru_id=$guru AND jam_mulai='$escM' AND status='aktif' LIMIT 1");
            if ($q2 && $q2->num_rows > 0) {
                $msg = 'Guru sudah mengajar di jam ' . formatJam($s['m']) . ' — jadwal tidak jadi disimpan.';
                flash('danger', $msg);
                if (isAjax()) jsonOut(['success' => false, 'message' => $msg]);
                redirect('jadwal.php');
            }
        }

        $ok = true; $err = '';
        $conn->begin_transaction();
        foreach ($slots as $s) {
            $stmt = $conn->prepare("INSERT INTO jadwal_mengajar (guru_id, kelas_id, mapel_id, hari, jam_mulai, jam_selesai, ruangan) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiissss", $guru, $kelas, $mapel, $hari, $s['m'], $s['s'], $ruangan);
            if (!$stmt->execute()) { $ok = false; $err = $conn->error; break; }
        }
        if ($ok) $conn->commit(); else $conn->rollback();

        $n = count($slots);
        $msg = $ok
            ? ($n > 1 ? $n . ' jadwal ditambahkan: ' . formatJam($slots[0]['m']) . ' – ' . formatJam($slots[$n - 1]['s']) . ' (' . $n . ' JP)' : 'Jadwal berhasil ditambahkan!')
            : 'Gagal: ' . ($err ?: $conn->error);
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('jadwal.php');
    }
    
    if ($action === 'delete') {
        // Bisa menerima satu id atau beberapa id (blok) dipisah koma
        $ids = array_filter(array_map('intval', explode(',', $_POST['id'] ?? '')));
        if ($ids) {
            $conn->query("DELETE FROM jadwal_mengajar WHERE id IN (" . implode(',', $ids) . ")");
        }
        $msg = 'Jadwal berhasil dihapus!';
        flash('success', $msg);
        if (isAjax()) jsonOut(['success' => true, 'message' => $msg]);
        redirect('jadwal.php');
    }
}

$hari_options = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hari_indo_full = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$hari_filter = $_GET['hari'] ?? ($hari_indo_full[(int)date('w')] === 'Minggu' ? 'Senin' : $hari_indo_full[(int)date('w')]);
$kelas_filter = $_GET['kelas_id'] ?? '';
$search = sanitize($_GET['search'] ?? '');

$where = "WHERE jm.status = 'aktif'";
if ($hari_filter) $where .= " AND jm.hari = '" . sanitize($hari_filter) . "'";
if ($kelas_filter) $where .= " AND jm.kelas_id = " . intval($kelas_filter);
if ($search) $where .= " AND (g.nama_guru LIKE '%$search%' OR k.nama_kelas LIKE '%$search%' OR mp.nama_mapel LIKE '%$search%' OR COALESCE(jm.ruangan,'') LIKE '%$search%')";

$jadwal_list = $conn->query("
    SELECT jm.*, g.nama_guru, g.no_hp, g.nip, k.nama_kelas, mp.nama_mapel, mp.kode_mapel
    FROM jadwal_mengajar jm
    JOIN guru g ON jm.guru_id = g.id
    JOIN kelas k ON jm.kelas_id = k.id
    JOIN mata_pelajaran mp ON jm.mapel_id = mp.id
    $where
    ORDER BY jm.hari, jm.jam_mulai
")->fetch_all(MYSQLI_ASSOC);

// Pola jam per hari (untuk dropdown & preview blok JP)
$chains = [];
$chain_rows = $conn->query("SELECT hari, jam_mulai, jam_selesai FROM jadwal_mengajar WHERE status='aktif' GROUP BY hari, jam_mulai, jam_selesai ORDER BY hari, jam_mulai");
if ($chain_rows) foreach ($chain_rows as $cr) $chains[$cr['hari']][] = ['m' => $cr['jam_mulai'], 's' => $cr['jam_selesai']];

$guru_list = $conn->query("SELECT id, nama_guru, nip FROM guru WHERE status='aktif' ORDER BY nama_guru")->fetch_all(MYSQLI_ASSOC);
$kelas_list_all = $conn->query("SELECT id, nama_kelas FROM kelas WHERE status='aktif' ORDER BY tingkat, nama_kelas")->fetch_all(MYSQLI_ASSOC);
$mapel_list = $conn->query("SELECT id, kode_mapel, nama_mapel FROM mata_pelajaran WHERE status='aktif' ORDER BY kode_mapel")->fetch_all(MYSQLI_ASSOC);

// Kelompokkan per hari untuk tab & jumlah
$jadwal_per_hari = [];
foreach ($jadwal_list as $j) {
    $jadwal_per_hari[$j['hari']][] = $j;
}

// Gabungkan slot berurutan (guru+kelas+mapel sama, jam menyambung) menjadi satu blok.
// Pengelompokan per (hari, guru, kelas, mapel) — bukan sekadar baris berurutan — agar
// blok tetap tergabung walau baris kelas lain menyelingi di jam yang sama.
$hari_index = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$groups = [];
foreach ($jadwal_list as $j) {
    $key = $j['hari'] . '|' . $j['guru_id'] . '|' . $j['kelas_id'] . '|' . $j['mapel_id'];
    $groups[$key][] = $j;
}

$jadwal_blocks = [];
foreach ($groups as $rows) {
    $cur = null;
    foreach ($rows as $j) {
        if ($cur && $cur['jam_selesai'] === $j['jam_mulai']) {
            $cur['jam_selesai'] = $j['jam_selesai'];
            $cur['jp']++;
            $cur['ids'][] = $j['id'];
        } else {
            if ($cur) $jadwal_blocks[] = $cur;
            $cur = [
                'id' => $j['id'], 'ids' => [$j['id']],
                'guru_id' => $j['guru_id'], 'kelas_id' => $j['kelas_id'], 'mapel_id' => $j['mapel_id'],
                'hari' => $j['hari'],
                'jam_mulai' => $j['jam_mulai'], 'jam_selesai' => $j['jam_selesai'],
                'jp' => 1,
                'nama_guru' => $j['nama_guru'], 'no_hp' => $j['no_hp'], 'kode_guru' => substr($j['nip'] ?? '', -2),
                'nama_kelas' => $j['nama_kelas'], 'nama_mapel' => $j['nama_mapel'],
                'kode_mapel' => $j['kode_mapel'], 'ruangan' => $j['ruangan'] ?? '',
            ];
        }
    }
    if ($cur) $jadwal_blocks[] = $cur;
}

// Urutkan blok: urutan hari, lalu jam mulai
usort($jadwal_blocks, function ($a, $b) use ($hari_index) {
    $da = array_search($a['hari'], $hari_index);
    $db = array_search($b['hari'], $hari_index);
    if ($da !== $db) return $da <=> $db;
    return strcmp($a['jam_mulai'], $b['jam_mulai']);
});

// Tanggal berikutnya sesuai hari jadwal (untuk pesan pengingat WhatsApp)
$tanggalBerikutnya = function ($hari) use ($hari_index) {
    $target = array_search($hari, $hari_index);
    $diff = ($target - (int)date('w') + 7) % 7;
    return date('Y-m-d', strtotime("+$diff days"));
};

$countAll = count($jadwal_list);
?>

<div id="ajax-area">
<div class="card mb-4">
    <div class="filter-bar">
        <form class="d-flex gap-2 flex-wrap ajax-filter" method="GET">
            <select class="form-select" name="hari" style="width:160px;">
                <option value="">Semua Hari</option>
                <?php foreach ($hari_options as $h): ?>
                <option value="<?= $h ?>" <?= $hari_filter === $h ? 'selected' : '' ?>><?= $h ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="kelas_id">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelas_list_all as $k): ?>
                <option value="<?= $k['id'] ?>" <?= $kelas_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control" name="search" placeholder="Cari guru / kelas / mapel..." value="<?= htmlspecialchars($search) ?>" style="width:220px;">
        </form>
        <?php if ($current_role !== 'guru'): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i>Tambah Jadwal
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Daftar Jadwal: satu list rapi, tab per hari -->
<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-calendar-week me-2"></i>Jadwal Mengajar</h5>
        <span class="badge badge-primary"><?= $countAll ?> Jadwal</span>
    </div>
    <div class="card-body">
        <div class="day-tabs">
            <a href="?hari=" class="day-tab <?= $hari_filter === '' ? 'active' : '' ?>" data-hari-tab="">
                <i class="bi bi-grid-3x3-gap me-1"></i>Semua
            </a>
            <?php foreach ($hari_options as $h): ?>
            <?php if (!isset($jadwal_per_hari[$h])) continue; ?>
            <a href="?hari=<?= $h ?>" class="day-tab <?= $hari_filter === $h ? 'active' : '' ?>" data-hari-tab="<?= $h ?>">
                <?= $h ?>
                <span class="ms-1 opacity-75"><?= count($jadwal_per_hari[$h]) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($jadwal_blocks)): ?>
        <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <h5>Tidak ada jadwal</h5>
            <p class="mb-3">Belum ada jadwal mengajar untuk filter ini.</p>
            <?php if ($current_role !== 'guru'): ?>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i>Tambah Jadwal
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="schedule-list">
            <?php foreach ($jadwal_blocks as $j): ?>
            <div class="sch-row">
                <div class="sch-time">
                    <?= formatJam($j['jam_mulai']) ?><br><span class="opacity-75">– <?= formatJam($j['jam_selesai']) ?></span>
                </div>
                <div class="sch-main">
                    <div class="sch-subject"><?= htmlspecialchars($j['nama_mapel']) ?></div>
                    <div class="sch-teacher"><i class="bi bi-person me-1"></i><?php if (!empty($j['kode_guru'])): ?><span class="sch-code">#<?= htmlspecialchars($j['kode_guru']) ?></span><?php endif; ?><?= htmlspecialchars($j['nama_guru']) ?></div>
                </div>
                <div class="sch-meta">
                    <span class="badge badge-secondary"><?= htmlspecialchars($j['nama_kelas']) ?></span>
                    <?php if ($j['jp'] > 1): ?>
                    <span class="badge badge-primary"><?= $j['jp'] ?> JP</span>
                    <?php endif; ?>
                    <?php if (!empty($j['ruangan'])): ?>
                    <span><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($j['ruangan']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="sch-actions">
                    <?php
                    $wa = null;
                    if (!empty($j['no_hp'])) {
                        $pesan = pesanPengingatMengajar(
                            $j['nama_guru'], $j['hari'], $tanggalBerikutnya($j['hari']),
                            formatJam($j['jam_mulai']), formatJam($j['jam_selesai']),
                            $j['nama_kelas'], $j['kode_mapel'] . ' - ' . $j['nama_mapel'],
                            $j['ruangan'] ?? ''
                        );
                        $wa = waLink($j['no_hp'], $pesan);
                    }
                    ?>
                    <?php if ($wa): ?>
                    <a href="<?= $wa ?>" target="_blank" class="btn btn-sm wa-btn btn-icon" title="Ingatkan guru via WhatsApp">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <?php else: ?>
                    <a href="<?= BASE_URL ?>/admin/guru.php?search=<?= urlencode($j['nama_guru']) ?>" class="btn btn-sm wa-btn wa-btn-missing btn-icon" title="No. HP <?= htmlspecialchars($j['nama_guru']) ?> belum diisi — klik untuk lengkapi di halaman Guru">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($current_role !== 'guru'): ?>
                    <form method="POST" class="ajax-form" data-confirm="Hapus jadwal ini?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= implode(',', $j['ids']) ?>">
                        <button class="btn btn-sm btn-outline-danger btn-icon" type="submit"><i class="bi bi-x"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
</div><!-- /ajax-area -->

<!-- Add Modal: input blok JP (jam selesai dihitung otomatis dari pola jam hari itu) -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jadwal Mengajar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Guru <span class="text-danger">*</span></label>
                        <select class="form-select" name="guru_id" required>
                            <option value="">-- Pilih Guru --</option>
                            <?php foreach ($guru_list as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= substr($g['nip'], -2) ?> · <?= htmlspecialchars($g['nama_guru']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Kode di depan = kode guru pada jadwal PDF.</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="kelas_id" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list_all as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            <select class="form-select" name="mapel_id" required>
                                <option value="">-- Pilih Mapel --</option>
                                <?php foreach ($mapel_list as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['kode_mapel'] . ' - ' . $m['nama_mapel']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hari <span class="text-danger">*</span></label>
                            <select class="form-select" name="hari" id="addHari" required>
                                <?php foreach ($hari_options as $h): ?>
                                <option value="<?= $h ?>"><?= $h ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                            <select class="form-select" name="jam_mulai" id="addJamMulai" required>
                                <option value="">-- Pilih Jam --</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jumlah JP <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="jumlah_jp" id="addJumlahJp" min="1" max="10" value="1" required>
                        </div>
                    </div>
                    <div class="row d-none" id="addCustomTime">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam Mulai (manual)</label>
                            <input type="time" class="form-control" name="custom_mulai">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam Selesai (manual)</label>
                            <input type="time" class="form-control" name="custom_selesai">
                        </div>
                    </div>
                    <div class="preview-block mb-3" id="addPreview">
                        <i class="bi bi-clock-history me-2"></i><span id="addPreviewText">Pilih hari &amp; jam mulai — jam selesai dihitung otomatis.</span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ruangan</label>
                        <input type="text" class="form-control" name="ruangan" placeholder="Contoh: R-101">
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

<script>
(function () {
    // Pola jam per hari (dari data jadwal yang sudah ada)
    var CHAIN = <?= json_encode($chains) ?>;
    var selHari = document.getElementById('addHari');
    var selJam = document.getElementById('addJamMulai');
    var inpJp = document.getElementById('addJumlahJp');
    var customRow = document.getElementById('addCustomTime');
    var previewText = document.getElementById('addPreviewText');
    var previewBlock = document.getElementById('addPreview');

    function fmt(t) { return t ? t.slice(0, 5) : ''; }

    function refreshJamOptions() {
        selJam.innerHTML = '';
        var opts = CHAIN[selHari.value] || [];
        if (!opts.length) {
            var o0 = document.createElement('option');
            o0.value = ''; o0.textContent = '-- Tidak ada pola jam --';
            selJam.appendChild(o0);
        } else {
            var ph = document.createElement('option');
            ph.value = ''; ph.textContent = '-- Pilih Jam Mulai --';
            selJam.appendChild(ph);
            opts.forEach(function (s) {
                var o = document.createElement('option');
                o.value = s.m;
                o.textContent = fmt(s.m) + ' – ' + fmt(s.s) + ' (1 JP)';
                selJam.appendChild(o);
            });
        }
        var oc = document.createElement('option');
        oc.value = 'custom'; oc.textContent = '🕒 Custom (atur jam manual)';
        selJam.appendChild(oc);
    }

    function updatePreview() {
        var custom = selJam.value === 'custom';
        customRow.classList.toggle('d-none', !custom);
        if (custom) {
            previewBlock.classList.remove('preview-warn');
            previewText.textContent = 'Mode custom: isi jam mulai & selesai sendiri.';
            return;
        }
        var chain = CHAIN[selHari.value] || [];
        var idx = -1;
        for (var i = 0; i < chain.length; i++) if (chain[i].m === selJam.value) { idx = i; break; }
        if (idx < 0) {
            previewBlock.classList.remove('preview-warn');
            previewText.textContent = 'Pilih hari & jam mulai — jam selesai dihitung otomatis.';
            return;
        }
        var jp = parseInt(inpJp.value, 10) || 1;
        var end = null, used = 0;
        for (var k = idx; k < chain.length && used < jp; k++) {
            if (used > 0 && chain[k].m !== chain[k - 1].s) break; // istirahat
            end = chain[k].s; used++;
        }
        if (used < jp) {
            previewBlock.classList.add('preview-warn');
            previewText.innerHTML = 'Hanya <b>' + used + ' JP</b> berturut-turut tersedia sebelum istirahat (sampai ' + fmt(end) + '). Kurangi Jumlah JP.';
        } else {
            previewBlock.classList.remove('preview-warn');
            previewText.innerHTML = 'Otomatis: <b>' + fmt(selJam.value) + ' – ' + fmt(end) + '</b> · <b>' + jp + ' JP</b> (' + jp + ' slot) akan dibuat sekaligus.';
        }
    }

    selHari.addEventListener('change', function () { refreshJamOptions(); updatePreview(); });
    selJam.addEventListener('change', updatePreview);
    inpJp.addEventListener('input', updatePreview);
    document.getElementById('addModal').addEventListener('shown.bs.modal', function () {
        refreshJamOptions(); updatePreview();
    });
    refreshJamOptions(); updatePreview();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>