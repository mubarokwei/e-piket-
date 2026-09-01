<?php
/**
 * Header - Layout utama aplikasi E-Piket
 */
// Output buffering agar redirect (header Location) tetap bekerja meski
// handler POST dieksekusi setelah HTML header dikeluarkan.
session_start();
ob_start();
require_once __DIR__ . '/../config/database.php';

// Cek login
if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/index.php');
}

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// =============================================
// Otorisasi Role-Based Access Control (RBAC)
// =============================================
$current_role = $_SESSION['role'] ?? 'guru';

// Peta akses halaman per role
$page_access = [
    'dashboard'        => ['admin', 'guru_piket', 'guru'],
    'guru'             => ['admin'],
    'mapel'            => ['admin'],
    'kelas'            => ['admin'],
    'wa-nomor'         => ['admin'],
    'jadwal'           => ['admin', 'guru_piket', 'guru'],
    'jadwal-piket'     => ['admin', 'guru_piket'],
    'absensi'          => ['admin', 'guru_piket'],
    'laporan-mengajar' => ['admin', 'guru_piket', 'guru'],
    'laporan-absensi'  => ['admin', 'guru_piket', 'guru'],
    'monitoring'       => ['admin', 'guru_piket'],
    'kelas-kosong'     => ['admin', 'guru_piket'],
    'users'            => ['admin'],
];

// Cek akses halaman yang sedang dibuka (termasuk request POST/GET apa pun)
if (isset($page_access[$current_page]) && !in_array($current_role, $page_access[$current_page], true)) {
    flash('danger', 'Anda tidak memiliki akses ke halaman ' . ucfirst(str_replace('-', ' ', $current_page)) . '.');
    redirect(BASE_URL . '/admin/dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'E-Piket' ?> - E-Piket</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/favicon.png">
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <!-- GSAP -->
    <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.7/dist/gsap.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="Logo E-Piket" class="sidebar-logo-img">
            <span>E-Piket</span>
        </div>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item <?= ($current_dir === 'admin' && $current_page === 'dashboard') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/dashboard.php">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if ($current_role === 'admin'): ?>
                <li class="nav-section">Manajemen</li>
                
                <li class="nav-item <?= ($current_dir === 'guru') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/guru.php">
                        <i class="bi bi-people-fill"></i>
                        <span>Guru</span>
                    </a>
                </li>
                
                <li class="nav-item <?= ($current_dir === 'mapel') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/mapel.php">
                        <i class="bi bi-book-fill"></i>
                        <span>Mata Pelajaran</span>
                    </a>
                </li>
                
                <li class="nav-item <?= ($current_dir === 'kelas') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/kelas.php">
                        <i class="bi bi-building"></i>
                        <span>Kelas</span>
                    </a>
                </li>
                
                <li class="nav-item <?= ($current_page === 'wa-nomor') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/wa-nomor.php">
                        <i class="bi bi-whatsapp"></i>
                        <span>No. HP WhatsApp</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-section">Jadwal</li>
                
                <li class="nav-item <?= ($current_dir === 'jadwal') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/jadwal.php">
                        <i class="bi bi-calendar-week-fill"></i>
                        <span>Jadwal Mengajar</span>
                    </a>
                </li>
                
                <?php if ($current_role !== 'guru'): ?>
                <li class="nav-item <?= ($current_page === 'jadwal-piket') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/jadwal-piket.php">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>Jadwal Piket</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-section">Absensi & Laporan</li>
                
                <?php if ($current_role !== 'guru'): ?>
                <li class="nav-item <?= ($current_dir === 'absensi') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/absensi.php">
                        <i class="bi bi-person-check-fill"></i>
                        <span>Absensi Guru Mengajar</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item <?= ($current_page === 'laporan-mengajar') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/laporan-mengajar.php">
                        <i class="bi bi-journal-text"></i>
                        <span>Laporan Mengajar</span>
                    </a>
                </li>
                
                <li class="nav-item <?= ($current_page === 'laporan-absensi') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/laporan-absensi.php">
                        <i class="bi bi-clipboard-data"></i>
                        <span>Laporan Absensi Guru</span>
                    </a>
                </li>
                
                <?php if ($current_role !== 'guru'): ?>
                <li class="nav-item <?= ($current_dir === 'monitoring') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/monitoring.php">
                        <i class="bi bi-eye-fill"></i>
                        <span>Monitoring Guru</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($current_role !== 'guru'): ?>
                <li class="nav-item <?= ($current_page === 'kelas-kosong') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/kelas-kosong.php">
                        <i class="bi bi-door-open-fill"></i>
                        <span>Kelas Kosong</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if ($current_role === 'admin'): ?>
                <li class="nav-section">Sistem</li>
                
                <li class="nav-item <?= ($current_page === 'users') ? 'active' : '' ?>">
                    <a href="<?= BASE_URL ?>/admin/users.php">
                        <i class="bi bi-person-gear"></i>
                        <span>Pengguna</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></span>
                    <span class="user-role"><?= ucfirst(str_replace('_', ' ', $_SESSION['role'])) ?></span>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="bi bi-list"></i>
                </button>
                <div class="page-info">
                    <h2 class="page-title"><?= $page_title ?? 'Dashboard' ?></h2>
                    <p class="page-subtitle"><?= $page_subtitle ?? 'Selamat datang di E-Piket' ?></p>
                </div>
            </div>
            
            <div class="topbar-right">
                <div class="topbar-date" id="currentDate"></div>
                <div class="topbar-time" id="currentTime"></div>
                <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </header>
        
        <!-- Flash Messages -->
        <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
            <?= $flash['message'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="content-wrapper">
