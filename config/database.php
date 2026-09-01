<?php
/**
 * Konfigurasi Database Aplikasi E-Piket
 * ======================================
 */

// Zona waktu aplikasi (WIB) agar date()/time()/strtotime() konsisten
// dengan waktu lokal dan query SQL (NOW/CURDATE).
date_default_timezone_set('Asia/Jakarta');

// Konfigurasi Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'e_piket');

// =====================================
// Deteksi BASE_URL secara otomatis agar aplikasi
// berjalan di folder mana pun:
// - di root docroot (http://localhost/)       -> BASE_URL = ''
// - di subfolder (http://localhost/e-piket/) -> BASE_URL = '/e-piket'
// =====================================
$__docRoot = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
$__appRoot = str_replace('\\', '/', dirname(__DIR__));
if ($__docRoot !== '' && strpos($__appRoot . '/', $__docRoot . '/') === 0) {
    define('BASE_URL', rtrim(substr($__appRoot, strlen($__docRoot)), '/'));
} else {
    define('BASE_URL', '');
}

// Fungsi koneksi database
function getDB() {
    static $conn = null;
    if ($conn === null) {
        try {
            // Nonaktifkan mode exception mysqli: error SQL cukup membuat execute()
            // mengembalikan false + $conn->error, supaya handler form (AJAX/normal)
            // bisa menangkapnya sebagai pesan error, bukan fatal HTML.
            mysqli_report(MYSQLI_REPORT_OFF);
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset("utf8mb4");
            if ($conn->connect_error) {
                die("Koneksi database gagal: " . $conn->connect_error);
            }
        } catch (Exception $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    return $conn;
}

// Fungsi helper
// CATATAN: sanitize hanya mengamankan input untuk SQL (escape + strip tag).
// Output harus di-escape dengan htmlspecialchars() saat ditampilkan, agar
// tidak terjadi double-escape (misal '&' berubah jadi '&amp;amp;').
function sanitize($data) {
    $conn = getDB();
    return $conn->real_escape_string(trim(strip_tags($data)));
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Deteksi request AJAX (fetch dari app.js selalu mengirim X-Requested-With)
function isAjax() {
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || ($_GET['ajax'] ?? '') === '1'
        || ($_POST['ajax'] ?? '') === '1';
}

// Kirim respons JSON untuk request AJAX (membersihkan buffer HTML header)
function jsonOut($data) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Format tanggal Indonesia
function formatTanggal($date) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', strtotime($date));
    $bln = $bulan[(int)date('m', strtotime($date))];
    $thn = date('Y', strtotime($date));
    return "$tgl $bln $thn";
}

// Format jam
function formatJam($time) {
    return date('H:i', strtotime($time));
}

// Nama hari Indonesia dari tanggal
function namaHari($date) {
    $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return $hari[(int)date('w', strtotime($date))];
}

// =============================================
// Helper WhatsApp (notifikasi guru piket -> guru)
// =============================================

// Konversi nomor HP Indonesia ke format internasional untuk wa.me
// 0812xxxx -> 62812xxxx ; 812xxxx -> 62812xxxx ; 62812xxxx tetap
function waNumber($noHp) {
    $n = preg_replace('/\D/', '', (string)$noHp);
    if ($n === '') return null;
    if (strpos($n, '0') === 0) {
        $n = '62' . substr($n, 1);
    } elseif (strpos($n, '8') === 0) {
        $n = '62' . $n;
    } elseif (strpos($n, '62') !== 0) {
        return null; // nomor tidak dikenal
    }
    return $n;
}

// Buat link wa.me, atau null jika nomor tidak valid
function waLink($noHp, $message) {
    $wa = waNumber($noHp);
    if (!$wa) return null;
    return 'https://wa.me/' . $wa . '?text=' . rawurlencode($message);
}

// Pesan pengingat jam mengajar untuk guru
function pesanPengingatMengajar($nama, $hari, $tanggal, $jamMulai, $jamSelesai, $kelas, $mapel, $ruangan = '') {
    $m  = "Halo *{$nama}*,\n";
    $m .= "Guru piket *SMK PK TGI JAKARTA* mengingatkan jadwal mengajar Anda:\n\n";
    $m .= "📅 Hari/Tanggal : {$hari}, " . formatTanggal($tanggal) . "\n";
    $m .= "🕐 Jam         : {$jamMulai} - {$jamSelesai} WIB\n";
    $m .= "🏫 Kelas       : {$kelas}\n";
    if ($mapel)  $m .= "📚 Mata Pelajaran : {$mapel}\n";
    if ($ruangan) $m .= "📍 Ruangan     : {$ruangan}\n";
    $m .= "\nMohon hadir tepat waktu. Terima kasih 🙏";
    return $m;
}
