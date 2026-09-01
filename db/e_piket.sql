-- =============================================
-- DATABASE SCHEMA APLIKASI E-PIKET
-- =============================================

CREATE DATABASE IF NOT EXISTS e_piket CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE e_piket;

-- =============================================
-- TABEL USERS (Login System)
-- =============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'guru_piket', 'guru') NOT NULL DEFAULT 'guru',
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- TABEL GURU
-- =============================================
CREATE TABLE IF NOT EXISTS guru (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(30) NOT NULL UNIQUE,
    nama_guru VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    no_hp VARCHAR(20),
    email VARCHAR(100),
    mata_pelajaran VARCHAR(100),
    alamat TEXT,
    foto VARCHAR(255) DEFAULT 'default.png',
    user_id INT,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- TABEL MATA PELAJARAN
-- =============================================
CREATE TABLE IF NOT EXISTS mata_pelajaran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_mapel VARCHAR(20) NOT NULL UNIQUE,
    nama_mapel VARCHAR(100) NOT NULL,
    kategori ENUM('umum', 'jurusan', 'ekstrakurikuler') DEFAULT 'umum',
    jam_per_minggu INT DEFAULT 1,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- TABEL KELAS
-- =============================================
CREATE TABLE IF NOT EXISTS kelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kelas VARCHAR(30) NOT NULL UNIQUE,
    tingkat INT NOT NULL,
    jurusan VARCHAR(50),
    wali_kelas_id INT,
    kapasitas INT DEFAULT 40,
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wali_kelas_id) REFERENCES guru(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- TABEL JADWAL MENGAJAR
-- =============================================
CREATE TABLE IF NOT EXISTS jadwal_mengajar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guru_id INT NOT NULL,
    kelas_id INT NOT NULL,
    mapel_id INT NOT NULL,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME NOT NULL,
    ruangan VARCHAR(50),
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- TABEL JADWAL PIKET
-- =============================================
CREATE TABLE IF NOT EXISTS jadwal_piket (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guru_id INT NOT NULL,
    tanggal DATE NOT NULL,
    shift_pagi BOOLEAN DEFAULT TRUE,
    shift_siang BOOLEAN DEFAULT FALSE,
    keterangan TEXT,
    status_hadir ENUM('hadir', 'tidak_hadir', 'ganti') DEFAULT 'hadir',
    guru_pengganti_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_pengganti_id) REFERENCES guru(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- TABEL ABSENSI SISWA
-- =============================================
CREATE TABLE IF NOT EXISTS absensi_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kelas_id INT NOT NULL,
    jadwal_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME,
    guru_id INT NOT NULL,
    hadir INT DEFAULT 0,
    sakit INT DEFAULT 0,
    izin INT DEFAULT 0,
    alpha INT DEFAULT 0,
    tidak_hadir_lainnya INT DEFAULT 0,
    total_siswa INT NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_mengajar(id) ON DELETE CASCADE,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- TABEL DETAIL ABSENSI SISWA (per siswa)
-- =============================================
CREATE TABLE IF NOT EXISTS detail_absensi_siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    absensi_id INT NOT NULL,
    nama_siswa VARCHAR(100) NOT NULL,
    status ENUM('hadir', 'sakit', 'izin', 'alpha') NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (absensi_id) REFERENCES absensi_siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- TABEL MONITORING KEHADIRAN GURU
-- =============================================
CREATE TABLE IF NOT EXISTS monitoring_kehadiran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guru_id INT NOT NULL,
    jadwal_id INT NOT NULL,
    kelas_id INT NOT NULL,
    mapel_id INT NOT NULL,
    tanggal DATE NOT NULL,
    status_kedatangan ENUM('tepat_waktu', 'terlambat', 'tidak_hadir', 'digantikan') NOT NULL,
    jam_masuk TIME,
    jam_mengajar_mulai TIME,
    jam_mengajar_selesai TIME,
    durasi_mengajar INT COMMENT 'dalam menit',
    catatan TEXT,
    dilaporkan_oleh INT COMMENT 'guru piket yang melaporkan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE CASCADE,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_mengajar(id) ON DELETE CASCADE,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE CASCADE,
    FOREIGN KEY (dilaporkan_oleh) REFERENCES guru(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- TABEL ABSENSI GURU PIKET
-- =============================================
CREATE TABLE IF NOT EXISTS absensi_guru_piket (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guru_piket_id INT NOT NULL,
    tanggal DATE NOT NULL,
    shift ENUM('pagi', 'siang') NOT NULL,
    jam_masuk TIME,
    jam_keluar TIME,
    status ENUM('hadir', 'tidak_hadir', 'terlambat') NOT NULL,
    keterangan TEXT,
    foto_bukti VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guru_piket_id) REFERENCES guru(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- TABEL KELAS KOSONG (Laporan + Penanganan)
-- =============================================
CREATE TABLE IF NOT EXISTS kelas_kosong (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    kelas_id INT NOT NULL,
    jadwal_id INT DEFAULT NULL COMMENT 'jadwal yang seharusnya berlangsung',
    guru_id INT DEFAULT NULL COMMENT 'guru yang seharusnya mengajar',
    mapel_id INT DEFAULT NULL,
    jam_mulai TIME NOT NULL,
    jam_selesai TIME,
    penyebab ENUM('sakit', 'izin', 'dinas', 'lupa', 'tanpa_keterangan', 'lainnya') DEFAULT 'tanpa_keterangan',
    tindakan ENUM('belum_ditangani', 'ditelepon', 'whatsapp', 'digantikan', 'didampingi', 'dilaporkan', 'lainnya') DEFAULT 'belum_ditangani',
    catatan TEXT,
    dilaporkan_oleh INT COMMENT 'guru piket yang melaporkan',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
    FOREIGN KEY (jadwal_id) REFERENCES jadwal_mengajar(id) ON DELETE SET NULL,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE SET NULL,
    FOREIGN KEY (mapel_id) REFERENCES mata_pelajaran(id) ON DELETE SET NULL,
    FOREIGN KEY (dilaporkan_oleh) REFERENCES guru(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- TABEL LOG AKTIVITAS
-- =============================================
CREATE TABLE IF NOT EXISTS log_aktivitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    aktivitas VARCHAR(255) NOT NULL,
    keterangan TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- INSERT DEFAULT DATA
-- =============================================

-- Default Admin User
INSERT INTO users (username, password, nama_lengkap, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');
-- Password: password

-- Mata Pelajaran (dari PDF JADWAL PELAJARAN 2026/2027 SMK Tunas Grafika Informatika)
INSERT INTO mata_pelajaran (id, kode_mapel, nama_mapel, kategori, jam_per_minggu) VALUES
(1,  'MTK',  'Matematika',                                   'umum',    4),
(2,  'BIN',  'Bahasa Indonesia',                             'umum',    4),
(3,  'BING', 'Bahasa Inggris',                                'umum',    4),
(4,  'IPAS', 'Projek Ilmu Pengetahuan Alam dan Sosial',      'umum',    2),
(5,  'PABP', 'Pendidikan Agama dan Budi Pekerti',             'umum',    3),
(6,  'PP',   'Pendidikan Pancasila',                          'umum',    2),
(7,  'PJOK', 'Pendidikan Jasmani, Olahraga, dan Kesehatan',   'umum',    3),
(8,  'SBY',  'Seni Budaya',                                   'umum',    2),
(9,  'SJH',  'Sejarah',                                       'umum',    2),
(10, 'INF',  'Informatika',                                   'umum',    3),
(11, 'BK',   'Bimbingan Konseling',                           'umum',    1),
(12, 'KWI',  'Kreativitas, Inovasi, dan Kewirausahaan',       'umum',    2),
(13, 'MLK',  'Mulok (Bahasa Arab)',                           'umum',    2),
(14, 'DDKV', 'Dasar Desain Komunikasi Visual',                'jurusan', 6),
(15, 'KDKV', 'Konsentrasi Desain Komunikasi Visual',          'jurusan', 8),
(16, 'DPF',  'Dasar-dasar Program Keahlian PF',               'jurusan', 6),
(17, 'KPF',  'Konsentrasi Keahlian PF',                       'jurusan', 10),
(18, 'DULW', 'Dasar Keahlian ULW',                            'jurusan', 6),
(19, 'KULW', 'Konsentrasi ULW',                               'jurusan', 8),
(20, 'KK',   'Koding dan Keahlian (Koding & KA)',             'jurusan', 4),
(21, 'TKAM', 'Mata Pelajaran Pilihan (Tata Kamera)',          'jurusan', 2),
(22, 'ANM',  'Mata Pelajaran Pilihan (Animasi)',              'jurusan', 2);

-- Guru (dari PDF JADWAL PELAJARAN 2026/2027; id = kode guru di PDF, NIP placeholder)
INSERT INTO guru (id, nip, nama_guru, jenis_kelamin, no_hp, email, mata_pelajaran, status) VALUES
(2,  '20260002', 'Utami Alfiani, S.Kom.',          'P', NULL, NULL, 'Dasar Desain Komunikasi Visual; Koding dan Keahlian (Koding & KA)', 'aktif'),
(3,  '20260003', 'Annisa Sekar Ayu Ramadhani, S.Pd.', 'P', NULL, NULL, 'Projek IPAS', 'aktif'),
(4,  '20260004', 'Bambang Pratama, S.Ag.',        'L', NULL, NULL, 'Mulok (Bahasa Arab)', 'aktif'),
(5,  '20260005', 'Musonah Nurhidayah, S.Pd.',     'P', NULL, NULL, 'Matematika; Konsentrasi ULW', 'aktif'),
(6,  '20260006', 'Shalahuddin Al Afghani, S.Pd.', 'L', NULL, NULL, 'Dasar-dasar Program Keahlian PF; Mata Pelajaran Pilihan (Tata Kamera); Konsentrasi DKV', 'aktif'),
(7,  '20260007', 'Dedy Supriyadi, S.Pd., SE, M.Si.', 'L', NULL, NULL, 'Kreativitas, Inovasi, dan Kewirausahaan', 'aktif'),
(8,  '20260008', 'Nutifah Dewi, S.Pd.',           'P', NULL, NULL, 'Matematika; Konsentrasi ULW', 'aktif'),
(9,  '20260009', 'Ade Candra L., S.Kom.',         'L', NULL, NULL, 'Informatika; Mata Pelajaran Pilihan (Animasi)', 'aktif'),
(10, '20260010', 'Nurhayati, S.Pd.',              'P', NULL, NULL, 'Pendidikan Agama dan Budi Pekerti; Pendidikan Pancasila', 'aktif'),
(11, '20260011', 'Drs. Sutarto',                  'L', NULL, NULL, 'Bahasa Inggris', 'aktif'),
(12, '20260012', 'H. Abdullah Amin, S.Pd.I., M.M.Pd.', 'L', NULL, NULL, 'Pendidikan Agama dan Budi Pekerti', 'aktif'),
(13, '20260013', 'Marlidha, S.Pd.',               'P', NULL, NULL, 'Matematika; Konsentrasi ULW', 'aktif'),
(14, '20260014', 'Yuyun Sufitri, S.Pd.',          'P', NULL, NULL, 'Bahasa Indonesia', 'aktif'),
(15, '20260015', 'Maulana Ardi, S.Kom.',          'L', NULL, NULL, 'Konsentrasi DKV; Konsentrasi Desain Komunikasi Visual', 'aktif'),
(16, '20260016', 'M. Abdul Mubarok, S.Kom.',      'L', NULL, NULL, 'Koding dan Keahlian (Koding & KA)', 'aktif'),
(17, '20260017', 'Sachrowi, S.Kom.',              'L', NULL, NULL, 'Konsentrasi PF; Konsentrasi DKV', 'aktif'),
(18, '20260018', 'M. Adli, S.M.',                 'L', NULL, NULL, 'Sejarah; Kreativitas, Inovasi, dan Kewirausahaan', 'aktif'),
(19, '20260019', 'Fitri Handayani, S.M.',         'P', NULL, NULL, 'Dasar-dasar Program Keahlian ULW; Kreativitas, Inovasi, dan Kewirausahaan; Konsentrasi ULW', 'aktif'),
(20, '20260020', 'Melina Anggraini',              'P', NULL, NULL, 'Seni Budaya', 'aktif'),
(21, '20260021', 'Intan Irawati, S.Pd.',          'P', NULL, NULL, NULL, 'aktif'),
(22, '20260022', 'Yanuar Firmansyah Putra',       'L', NULL, NULL, 'Pendidikan Jasmani, Olahraga, dan Kesehatan', 'aktif'),
(23, '20260023', 'Siska Ayu Yuliana, S.Pd.',      'P', NULL, NULL, 'Bahasa Indonesia; Sejarah', 'aktif'),
(24, '20260024', 'Mithan Anggita Rahman, S.Pd.',  'L', NULL, NULL, 'Bimbingan Konseling; Pendidikan Pancasila', 'aktif'),
(25, '20260025', 'Nurria Puji Lestari, S.Ds.',    'P', NULL, NULL, 'Dasar Keahlian DKV; Informatika; Konsentrasi Desain Komunikasi Visual', 'aktif'),
(26, '20260026', 'Rahmawati Utami, S.Ikom.',      'P', NULL, NULL, 'Dasar Keahlian PF; Konsentrasi Keahlian PF', 'aktif');

-- Sample Kelas
INSERT INTO kelas (nama_kelas, tingkat, jurusan, kapasitas) VALUES
('VII-A', 7, NULL, 36),
('VII-B', 7, NULL, 36),
('VII-C', 7, NULL, 36),
('VIII-A', 8, NULL, 36),
('VIII-B', 8, NULL, 36),
('VIII-C', 8, NULL, 36),
('IX-A', 9, NULL, 36),
('IX-B', 9, NULL, 36),
('IX-C', 9, NULL, 36);

-- (Jadwal mengajar diisi dari import PDF: db/import_guru_mapel_2026.sql + import jadwal menyusul)

-- Sample Jadwal Piket (Minggu ini)
INSERT INTO jadwal_piket (guru_id, tanggal, shift_pagi, shift_siang, status_hadir) VALUES
(6, CURDATE(), TRUE, FALSE, 'hadir'),
(7, CURDATE(), FALSE, TRUE, 'hadir'),
(9, DATE_ADD(CURDATE(), INTERVAL 1 DAY), TRUE, FALSE, 'hadir'),
(10, DATE_ADD(CURDATE(), INTERVAL 1 DAY), FALSE, TRUE, 'hadir'),
(11, DATE_ADD(CURDATE(), INTERVAL 2 DAY), TRUE, FALSE, 'hadir'),
(12, DATE_ADD(CURDATE(), INTERVAL 2 DAY), FALSE, TRUE, 'hadir');

-- (Absensi siswa - data riil diinput via aplikasi)

-- (Monitoring & kelas kosong - data riil diinput via aplikasi)
