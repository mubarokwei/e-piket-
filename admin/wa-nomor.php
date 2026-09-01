<?php
$page_title = 'No. HP Guru (WhatsApp)';
$page_subtitle = 'Isi nomor HP guru agar tombol pengingat WhatsApp aktif';
require_once __DIR__ . '/../includes/header.php';

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nohp = $_POST['nohp'] ?? [];
    $ok = true; $count = 0;
    foreach ($nohp as $gid => $hp) {
        $gid = intval($gid);
        if (!$gid) continue;
        $hp = trim((string)$hp);
        $v = ($hp === '') ? null : $hp;
        $stmt = $conn->prepare("UPDATE guru SET no_hp = ? WHERE id = ?");
        $stmt->bind_param("si", $v, $gid);
        if ($stmt->execute()) $count++;
        else $ok = false;
    }
    $msg = $ok ? "$count nomor HP guru berhasil disimpan." : 'Sebagian gagal: ' . $conn->error;
    flash($ok ? 'success' : 'danger', $msg);
    if (isAjax()) jsonOut(['success' => $ok, 'message' => $msg]);
    redirect('wa-nomor.php');
}

$gurus = $conn->query("SELECT id, nip, nama_guru, no_hp FROM guru WHERE status='aktif' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>

<div id="ajax-area">
<div class="card mb-4">
    <div class="filter-bar">
        <div class="d-flex align-items-center gap-2 text-secondary">
            <i class="bi bi-whatsapp"></i>
            <span>Format nomor: <b>08xxxxxxxxxx</b> — otomatis dikonversi ke 62xx saat mengirim.</span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5><i class="bi bi-telephone-fill me-2"></i>Isi No. HP Guru</h5>
        <span class="badge badge-primary"><?= count($gurus) ?> Guru Aktif</span>
    </div>
    <div class="card-body">
        <?php if (empty($gurus)): ?>
        <div class="empty-state">
            <i class="bi bi-people"></i>
            <h5>Tidak ada guru aktif</h5>
        </div>
        <?php else: ?>
        <form method="POST" class="ajax-form">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:50px;">No</th>
                            <th style="width:70px;">Kode</th>
                            <th>Nama Guru</th>
                            <th style="width:220px;">No. HP (WhatsApp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gurus as $i => $g): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><span class="sch-code">#<?= htmlspecialchars(substr($g['nip'] ?? '', -2)) ?></span></td>
                            <td><strong><?= htmlspecialchars($g['nama_guru']) ?></strong></td>
                            <td>
                                <input type="text" class="form-control" name="nohp[<?= $g['id'] ?>]"
                                       value="<?= htmlspecialchars($g['no_hp'] ?? '') ?>"
                                       placeholder="08xxxxxxxxxx" maxlength="20"
                                       inputmode="tel">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>Simpan Semua
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="alert alert-info mt-3 d-flex align-items-center gap-2">
    <i class="bi bi-info-circle"></i>
    <span>Setelah nomor terisi, tombol <i class="bi bi-whatsapp"></i> di halaman <b>Jadwal Mengajar</b>, <b>Absensi</b>, <b>Monitoring</b>, dan <b>Kelas</b> langsung aktif untuk mengirim pengingat jam mengajar.</span>
</div>
</div><!-- /ajax-area -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
