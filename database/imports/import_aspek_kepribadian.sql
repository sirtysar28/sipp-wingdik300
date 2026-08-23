-- ============================================================
--  IMPORT SQL — ASPEK KEPRIBADIAN
--  Sumber : u1526346_penilaian (database lama)
--  Tujuan : simonik_smart (SIPP)
--  Tanggal: 2026-07-03
-- ============================================================
--
-- 10 Aspek Kepribadian standar militer
-- (BS=Baik Sekali +0.5 | B=Baik +0.25 | C=Cukup 0 | K=Kurang -0.25 | KS=Kurang Sekali -0.5)
-- Nilai Akhir = 75 + Σ poin aspek
-- ============================================================

-- Hapus data lama jika ada (aman dari foreign key)
DELETE FROM `detail_kepribadian`;
DELETE FROM `aspek_kepribadian`;

-- Reset auto-increment
ALTER TABLE `aspek_kepribadian` AUTO_INCREMENT = 1;

-- Insert 10 aspek kepribadian
INSERT INTO `aspek_kepribadian` (`id`, `nomor`, `nama`, `deskripsi`, `aktif`, `created_at`, `updated_at`) VALUES
(1, 1, 'Mental dan Moral', 'Ketakwaan, Keyakinan dan Pengenalan Pancasila dan UUD 45, Sikap Semangat Juang, Toleransi, Budi Luhur.', 1, NOW(), NOW()),
(2, 2, 'Kejujuran', 'Pegang Teguh Kebenaran, Keberanian Mengungkap Kebenaran, Kesadaran untuk Jujur, Perbuatan sesuai dengan perkataan.', 1, NOW(), NOW()),
(3, 3, 'Disiplin', 'Ketertiban Sikap, Ketertiban Berkomunikasi, Ketertiban Waktu, Patuh pada Peraturan dan Ketentuan, Patuh pada Perintah.', 1, NOW(), NOW()),
(4, 4, 'Kewibawaan', 'Penampilan, Mempengaruhi Orang Lain, Keberanian Tindakan, Kepercayaan Diri.', 1, NOW(), NOW()),
(5, 5, 'Inisiatif', 'Kemampuan Menciptakan, Memanfaatkan Kesempatan, Menemukan Cara Kerja, Mengambil Risiko.', 1, NOW(), NOW()),
(6, 6, 'Sosiobilitas', 'Adaptasi Lingkungan, Adaptasi Kondisi Sosial, Berkomunikasi, Perhatian terhadap Lingkungan.', 1, NOW(), NOW()),
(7, 7, 'Loyalitas', 'Rela Berkorban, Patuh pada Peraturan dan Ketentuan, Patuh pada Atasan, Patuh pada Teman dan Kelompok Patuh pada Tugas.', 1, NOW(), NOW()),
(8, 8, 'Tanggung  Jawab', 'Taat pada Peraturan/ Ketentuan, Rela Mengemban Tugas, Sedia Mengemban Tugas, Menerima Resiko.', 1, NOW(), NOW()),
(9, 9, 'Kedewasaan', 'Keseimbangan Emosi dan Sikap Rasional, Kerja Sama, Pengendalian Diri, Penyesuaian terhadap Tugas.', 1, NOW(), NOW()),
(10, 10, 'Ketabahan', 'Keuletan, Semangat, Pengendalian Diri, Rasional dan Realistis, Daya Tahan.', 1, NOW(), NOW());
