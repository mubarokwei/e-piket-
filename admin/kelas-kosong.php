<?php
$page_title = 'Kelas Kosong';
$page_subtitle = 'Laporan & penanganan kelas yang tidak ada guru mengajar';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

// =============================================
// PROSES FORM
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $tanggal = sanitize($_POST['tanggal'] ?? '');
        $kelas_id = intval($_POST['kelas_id'] ?? 0);
        $jadwal_id = !empty($_POST['jadwal_id']) ? intval($_POST['jadwal_id']) : null;
        $guru_id = !empty($_POST['guru_id']) ? intval($_POST['guru_id']) : null;
        $mapel_id = !empty($_POST['mapel_id']) ? intval($_POST['mapel_id']) : null;
        $jam_mulai = sanitize($_POST['jam_mulai'] ?? '');
        $jam_selesai = sanitize($_POST['jam_selesai'] ?? '');
        $penyebab = sanitize($_POST['penyebab'] ?? 'tanpa_keterangan');
        $tindakan = sanitize($_POST['tindakan'] ?? 'belum_ditangani');
        $catatan = sanitize($_POST['catatan'] ?? '');
        $pelapor = !empty($_POST['dilaporkan_oleh']) ? intval($_POST['dilaporkan_oleh']) : null;

        // s tanggal, i kelas, i jadwal, i guru, i mapel,
        // s jam_mulai, s jam_selesai, s penyebab, s tindakan, s catatan, i pelapor
        $stmt = $conn->prepare("INSERT INTO kelas_kosong (tanggal, kelas_id, jadwal_id, guru_id, mapel_id, jam_mulai, jam_selesai, penyebab, tindakan, catatan, dilaporkan_oleh) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiiisssssi", $tanggal, $kelas_id, $jadwal_id, $guru_id, $mapel_id, $jam_mulai, $jam_selesai, $penyebab, $tindakan, $catatan, $pelapor);
        $ok = $stmt->execute();
        $msg = $ok ? 'Laporan kelas kosong & penanganan berhasil disimpan!' : 'Gagal: ' . $conn->error;
        flash($ok ? 'success' : 'danger', $msg);
        if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
        redirect('kelas-kosong.php');
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $conn->query("DELETE FROM kelas_kosong WHERE id = $id");
        $msg = 'Data kelas kosong berhasil dihapus!';
        flash('success', $msg);
        if (isAjax()) jsonOut(['success' => true, 'message' => $msg]);
        redirect('kelas-kosong.php');
    }
}

// =============================================
// FILTER
// =============================================
$tgl_mulai = sanitize($_GET['tgl_mulai'] ?? date('Y-m-01'));
$tgl_selesai = sanitize($_GET['tgl_selesai'] ?? date('Y-m-d'));
$kelas_filter = $_GET['kelas_id'] ?? '';
$tindakan_filter = sanitize($_GET['tindakan'] ?? '');

$where = "WHERE kk.tanggal BETWEEN '$tgl_mulai' AND '$tgl_selesai'";
if ($kelas_filter) $where .= " AND kk.kelas_id = " . intval($kelas_filter);
if ($tindakan_filter) $where .= " AND kk.tindakan = '$tindakan_filter'";
$search = sanitize($_GET['search'] ?? '');
if ($search) $where .= " AND (k.nama_kelas LIKE '%$search%' OR COALESCE(g.nama_guru,'') LIKE '%$search%' OR COALESCE(kk.catatan,'') LIKE '%$search%')";

// =============================================
// DATA
// =============================================
$list = $conn->query("
    SELECT kk.*, k.nama_kelas, k.tingkat,
           g.nama_guru, g.no_hp,
           mp.nama_mapel, mp.kode_mapel,
           gp.nama_guru AS nama_pelapor
    FROM kelas_kosong kk
    JOIN kelas k ON kk.kelas_id = k.id
    LEFT JOIN guru g ON kk.guru_id = g.id
    LEFT JOIN mata_pelajaran mp ON kk.mapel_id = mp.id
    LEFT JOIN guru gp ON kk.dilaporkan_oleh = gp.id
    $where
    ORDER BY kk.tanggal DESC, kk.jam_mulai ASC
")->fetch_all(MYSQLI_ASSOC);

// Statistik
$total = count($list);
$belum = count(array_filter($list, fn($r) => $r['tindakan'] === 'belum_ditangani'));
$sudah = $total - $belum;
$kelas_dampak = count(array_unique(array_column($list, 'kelas_id')));

// Data untuk form
$guru_list = $conn->query("SELECT id, nama_guru, no_hp FROM guru WHERE status='aktif' ORDER BY nama_guru")->fetch_all(MYSQLI_ASSOC);
$kelas_list_all = $conn->query("SELECT id, nama_kelas FROM kelas WHERE status='aktif' ORDER BY tingkat, nama_kelas")->fetch_all(MYSQLI_ASSOC);
$mapel_list = $conn->query("SELECT id, kode_mapel, nama_mapel FROM mata_pelajaran WHERE status='aktif' ORDER BY kode_mapel")->fetch_all(MYSQLI_ASSOC);
$jadwal_list = $conn->query("
    SELECT jm.*, g.nama_guru, k.nama_kelas, mp.nama_mapel
    FROM jadwal_mengajar jm
    JOIN guru g ON jm.guru_id = g.id
    JOIN kelas k ON jm.kelas_id = k.id
    JOIN mata_pelajaran mp ON jm.mapel_id = mp.id
    WHERE jm.status='aktif'
    ORDER BY jm.hari, jm.jam_mulai
")->fetch_all(MYSQLI_ASSOC);

// Label & warna badge
function penyebabLabel($p) {
    return match ($p) {
        'sakit'            => 'Sakit',
        'izin'             => 'Izin',
        'dinas'            => 'Dinas Luar',
        'lupa'             => 'Lupa Jadwal',
        'tanpa_keterangan' => 'Tanpa Keterangan',
        default            => ucfirst((string)$p),
    };
}
function penyebabClass($p) {
    return match ($p) {
        'sakit'            => 'badge-info',
        'izin'             => 'badge-warning',
        'dinas'            => 'badge-primary',
        'lupa'             => 'badge-danger',
        'tanpa_keterangan' => 'badge-danger',
        default            => 'badge-secondary',
    };
}
function tindakanLabel($t) {
    return match ($t) {
        'belum_ditangani' => 'Belum Ditangani',
        'ditelepon'       => 'Ditelepon',
        'whatsapp'        => 'Diingatkan via WA',
        'digantikan'      => 'Digantikan',
        'didampingi'      => 'Didampingi',
        'dilaporkan'      => 'Dilaporkan',
        default           => ucfirst((string)$t),
    };
}
function tindakanClass($t) {
    return match ($t) {
        'belum_ditangani' => 'badge-danger',
        'ditelepon'       => 'badge-primary',
        'whatsapp'        => 'badge-success',
        'digantikan'      => 'badge-info',
        'didampingi'      => 'badge-warning',
        'dilaporkan'      => 'badge-secondary',
        default           => 'badge-secondary',
    };
}
?>

<!-- Filter & Aksi -->
<div id="ajax-area">
<div class="card mb-4">
    <div class="filter-bar">
        <form class="d-flex gap-2 align-items-center flex-wrap ajax-filter" method="GET">
            <label class="form-label mb-0 me-1 text-secondary">Dari:</label>
            <input type="date" class="form-control" name="tgl_mulai" value="<?= $tgl_mulai ?>" style="width:160px;">
            <label class="form-label mb-0 me-1 text-secondary">s/d:</label>
            <input type="date" class="form-control" name="tgl_selesai" value="<?= $tgl_selesai ?>" style="width:160px;">
            <select class="form-select" name="kelas_id" style="width:150px;">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelas_list_all as $k): ?>
                <option value="<?= $k['id'] ?>" <?= $kelas_filter == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="tindakan" style="width:155px;">
                <option value="">Semua Penanganan</option>
                <option value="belum_ditangani" <?= $tindakan_filter === 'belum_ditangani' ? 'selected' : '' ?>>Belum Ditangani</option>
                <option value="ditelepon" <?= $tindakan_filter === 'ditelepon' ? 'selected' : '' ?>>Ditelepon</option>
                <option value="whatsapp" <?= $tindakan_filter === 'whatsapp' ? 'selected' : '' ?>>Diingatkan via WA</option>
                <option value="digantikan" <?= $tindakan_filter === 'digantikan' ? 'selected' : '' ?>>Digantikan</option>
                <option value="didampingi" <?= $tindakan_filter === 'didampingi' ? 'selected' : '' ?>>Didampingi</option>
                <option value="dilaporkan" <?= $tindakan_filter === 'dilaporkan' ? 'selected' : '' ?>>Dilaporkan</option>
            </select>
            <input type="text" class="form-control" name="search" placeholder="Cari kelas / guru / catatan..." value="<?= htmlspecialchars($search) ?>" style="width:190px;">
            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="?" class="btn btn-outline-light">Reset</a>
        </form>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-light" onclick="printReport()">
                <i class="bi bi-printer me-1"></i>Cetak
            </button>
            <button class="btn btn-outline-light" onclick="exportCSV('kelasKosongTable', 'kelas_kosong_<?= $tgl_mulai ?>_<?= $tgl_selesai ?>')">
                <i class="bi bi-download me-1"></i>CSV
            </button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-lg me-1"></i>Input Kelas Kosong
            </button>
        </div>
    </div>
</div>

<!-- Kartu Statistik -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box blue"><i class="bi bi-door-open"></i></div>
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Total Kejadian</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box red"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-value"><?= $belum ?></div>
            <div class="stat-label">Belum Ditangani</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box green"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-value"><?= $sudah ?></div>
            <div class="stat-label">Sudah Ditangani</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="icon-box yellow"><i class="bi bi-people-fill"></i></div>
            <div class="stat-value"><?= $kelas_dampak ?></div>
            <div class="stat-label">Kelas Terdampak</div>
        </div>
    </div>
</div>

<!-- Tabel Laporan Kelas Kosong -->
<div class="card">
    <div class="card-header">
        <h5>Laporan Kelas Kosong - <?= formatTanggal($tgl_mulai) ?> s/d <?= formatTanggal($tgl_selesai) ?></h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" id="kelasKosongTable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Jam</th>
                        <th>Guru Pengajar</th>
                        <th>Mata Pelajaran</th>
                        <th>Penyebab</th>
                        <th>Penanganan</th>
                        <th>Pelapor</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($list)): ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-check2-circle"></i>
                                <h5>Tidak Ada Kelas Kosong</h5>
                                <p>Belum ada laporan kelas kosong pada periode ini. Semua kelas terisi guru mengajar.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($list as $i => $kk): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td>
                            <strong><?= namaHari($kk['tanggal']) ?></strong><br>
                            <span class="text-secondary"><?= formatTanggal($kk['tanggal']) ?></span>
                        </td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($kk['nama_kelas']) ?></span></td>
                        <td class="jam-mengajar"><?= formatJam($kk['jam_mulai']) ?><?= $kk['jam_selesai'] ? '-' . formatJam($kk['jam_selesai']) : '' ?></td>
                        <td>
                            <strong><?= htmlspecialchars($kk['nama_guru'] ?? 'Tidak diketahui') ?></strong>
                        </td>
                        <td>
                            <?php if ($kk['kode_mapel']): ?>
                                <span class="mapel-kode-inline"><?= htmlspecialchars($kk['kode_mapel']) ?></span><?= htmlspecialchars($kk['nama_mapel']) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><span class="badge <?= penyebabClass($kk['penyebab']) ?>"><?= penyebabLabel($kk['penyebab']) ?></span></td>
                        <td>
                            <span class="badge <?= tindakanClass($kk['tindakan']) ?>"><?= tindakanLabel($kk['tindakan']) ?></span>
                            <?php if ($kk['catatan']): ?>
                                <div class="text-secondary" style="font-size:12px; max-width:220px;"><?= htmlspecialchars($kk['catatan']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-secondary"><?= htmlspecialchars($kk['nama_pelapor'] ?? '-') ?></td>
                        <td class="d-flex gap-1">
                            <?php
                            // Pengingat WhatsApp ke guru pengajar
                            $wa = null;
                            if ($kk['guru_id'] && $kk['jam_mulai']) {
                                $pesan = pesanPengingatMengajar(
                                    $kk['nama_guru'], namaHari($kk['tanggal']), $kk['tanggal'],
                                    formatJam($kk['jam_mulai']), $kk['jam_selesai'] ? formatJam($kk['jam_selesai']) : '',
                                    $kk['nama_kelas'], ($kk['kode_mapel'] ? $kk['kode_mapel'] . ' - ' : '') . $kk['nama_mapel'],
                                    ''
                                );
                                $wa = waLink($kk['no_hp'], $pesan);
                            }
                            ?>
                            <?php if ($wa): ?>
                            <a href="<?= $wa ?>" target="_blank" class="btn btn-sm wa-btn btn-icon" title="Ingatkan guru via WhatsApp">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <?php else: ?>
                            <a href="<?= BASE_URL ?>/admin/guru.php?search=<?= urlencode($kk['nama_guru'] ?? '') ?>" class="btn btn-sm wa-btn wa-btn-missing btn-icon" title="No. HP <?= htmlspecialchars($kk['nama_guru'] ?? 'Guru') ?> belum diisi — klik untuk lengkapi di halaman Guru">
                                <i class="bi bi-whatsapp"></i>
                            </a>
                            <?php endif; ?>
                            <form method="POST" class="d-inline ajax-form" data-confirm="Hapus laporan kelas kosong ini?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $kk['id'] ?>">
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

<!-- Modal Input Kelas Kosong -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" class="ajax-form">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-door-open me-2"></i>Input Kelas Kosong & Penanganan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="f_tanggal" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" id="f_kelas_id" name="kelas_id" required>
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list_all as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jadwal Mengajar (opsional — otomatis mengisi guru, mapel & jam)</label>
                        <select class="form-select" id="f_jadwal_id" name="jadwal_id">
                            <option value="">-- Pilih jadwal sesuai kelas & hari --</option>
                            <?php foreach ($jadwal_list as $j): ?>
                            <option value="<?= $j['id'] ?>"
                                    data-kelas="<?= $j['kelas_id'] ?>"
                                    data-hari="<?= $j['hari'] ?>"
                                    data-guru="<?= $j['guru_id'] ?>"
                                    data-mapel="<?= $j['mapel_id'] ?>"
                                    data-mulai="<?= $j['jam_mulai'] ?>"
                                    data-selesai="<?= $j['jam_selesai'] ?>">
                                <?= $j['hari'] ?> | <?= formatJam($j['jam_mulai']) ?>-<?= formatJam($j['jam_selesai']) ?> | <?= htmlspecialchars($j['nama_guru']) ?> | <?= htmlspecialchars($j['nama_mapel']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guru yang Seharusnya Mengajar <span class="text-danger">*</span></label>
                            <select class="form-select" id="f_guru_id" name="guru_id" required>
                                <option value="">-- Pilih Guru --</option>
                                <?php foreach ($guru_list as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mata Pelajaran</label>
                            <select class="form-select" id="f_mapel_id" name="mapel_id">
                                <option value="">-- Pilih Mapel --</option>
                                <?php foreach ($mapel_list as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['kode_mapel']) ?> - <?= htmlspecialchars($m['nama_mapel']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" id="f_jam_mulai" name="jam_mulai" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Jam Selesai</label>
                            <input type="time" class="form-control" id="f_jam_selesai" name="jam_selesai">
                        </div>
                    </div>

                    <hr style="border-color:var(--border-color);">
                    <h6 class="mb-3"><i class="bi bi-tools me-2"></i>Penyebab & Penanganan</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Penyebab Kelas Kosong <span class="text-danger">*</span></label>
                            <select class="form-select" name="penyebab" required>
                                <option value="tanpa_keterangan">Tanpa Keterangan</option>
                                <option value="sakit">Guru Sakit</option>
                                <option value="izin">Guru Izin</option>
                                <option value="dinas">Guru Dinas Luar</option>
                                <option value="lupa">Guru Lupa Jadwal</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tindakan Penanganan <span class="text-danger">*</span></label>
                            <select class="form-select" name="tindakan" required>
                                <option value="belum_ditangani">Belum Ditangani</option>
                                <option value="whatsapp">Diingatkan via WhatsApp</option>
                                <option value="ditelepon">Ditelepon</option>
                                <option value="digantikan">Digantikan Guru Lain</option>
                                <option value="didampingi">Didampingi Guru Piket</option>
                                <option value="dilaporkan">Dilaporkan ke Waka Kesiswaan</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dilaporkan Oleh (Guru Piket) <span class="text-danger">*</span></label>
                            <select class="form-select" name="dilaporkan_oleh" required>
                                <option value="">-- Pilih Guru Piket --</option>
                                <?php foreach ($guru_list as $g): ?>
                                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_guru']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" name="catatan" rows="2" placeholder="Contoh: siswa dikumpulkan di perpustakaan, guru sudah dihubungi"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Simpan Laporan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// =============================================
// Filter jadwal otomatis berdasarkan kelas & hari,
// lalu isi otomatis guru, mapel, dan jam
// =============================================
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const fTanggal = document.getElementById('f_tanggal');
const fKelas = document.getElementById('f_kelas_id');
const fJadwal = document.getElementById('f_jadwal_id');
const fGuru = document.getElementById('f_guru_id');
const fMapel = document.getElementById('f_mapel_id');
const fJamMulai = document.getElementById('f_jam_mulai');
const fJamSelesai = document.getElementById('f_jam_selesai');

function hariDariTanggal(iso) {
    const d = new Date(iso + 'T00:00:00');
    return HARI[d.getDay()];
}

function filterJadwal() {
    const kelas = fKelas.value;
    const hari = hariDariTanggal(fTanggal.value);
    [...fJadwal.options].forEach(o => {
        if (!o.value) return;
        o.hidden = !(o.dataset.kelas === kelas && o.dataset.hari === hari);
    });
    fJadwal.value = '';
    fGuru.disabled = false;
    fMapel.disabled = false;
    fJamMulai.disabled = false;
    fJamSelesai.disabled = false;
}

function isiOtomatis() {
    const opt = fJadwal.options[fJadwal.selectedIndex];
    if (!opt || !opt.value) {
        fGuru.disabled = false;
        fMapel.disabled = false;
        fJamMulai.disabled = false;
        fJamSelesai.disabled = false;
        return;
    }
    fGuru.value = opt.dataset.guru;
    fGuru.disabled = true;
    fMapel.value = opt.dataset.mapel;
    fMapel.disabled = true;
    fJamMulai.value = opt.dataset.mulai;
    fJamMulai.disabled = true;
    fJamSelesai.value = opt.dataset.selesai;
    fJamSelesai.disabled = true;
}

fKelas.addEventListener('change', filterJadwal);
fTanggal.addEventListener('change', filterJadwal);
fJadwal.addEventListener('change', isiOtomatis);
filterJadwal();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
