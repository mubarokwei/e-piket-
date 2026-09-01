-- =============================================
-- IMPORT DATA GURU & MATA PELAJARAN
-- Sumber: PDF "JADWAL PELAJARAN 2026-2027 - SMK Tunas Grafika Informatika Jakarta"
-- (Daftar Guru hal.2: kode guru 2 s/d 26)
--
-- Cara pakai:  mysql -u root e_piket < db/import_guru_mapel_2026.sql
-- Catatan:
--   * id guru = kode guru di PDF (2..26) agar sinkron dengan import jadwal nanti
--   * NIP = placeholder (20260002..20260026) - silakan perbaiki di halaman Guru
--   * No. HP & email tidak ada di PDF -> kosong (isi agar tombol WhatsApp muncul)
--   * Jenis kelamin = perkiraan dari nama, harap diverifikasi (kode 13, 21, 24)
-- =============================================

-- Hapus data sample lama (relasi FK di-cascade otomatis)
DELETE FROM kelas_kosong;
DELETE FROM monitoring_kehadiran;
DELETE FROM absensi_siswa;
DELETE FROM detail_absensi_siswa;
DELETE FROM jadwal_mengajar;
DELETE FROM jadwal_piket;
DELETE FROM guru;
DELETE FROM mata_pelajaran;

-- =============================================
-- MATA PELAJARAN (22 mapel dari PDF)
-- =============================================
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

-- =============================================
-- GURU (25 guru, id = kode di PDF)
-- =============================================
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

-- Reset auto_increment
ALTER TABLE guru AUTO_INCREMENT = 27;
ALTER TABLE mata_pelajaran AUTO_INCREMENT = 23;