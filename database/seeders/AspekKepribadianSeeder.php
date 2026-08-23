<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\AspekKepribadian;

class AspekKepribadianSeeder extends Seeder
{
    /**
     * Seed aspek kepribadian — data diambil dari database lama
     * (u1526346_penilaian → tabel aspek_kepribadian)
     *
     * Urutan & isi persis sama dengan data asli.
     */
    public function run(): void
    {
        // Hapus data lama (aman dari foreign key)
        AspekKepribadian::query()->delete();

        $aspek = [
            [
                'nomor'     => 1,
                'nama'      => 'Mental dan Moral',
                'deskripsi' => 'Ketakwaan, Keyakinan dan Pengenalan Pancasila dan UUD 45, Sikap Semangat Juang, Toleransi, Budi Luhur.',
            ],
            [
                'nomor'     => 2,
                'nama'      => 'Kejujuran',
                'deskripsi' => 'Pegang Teguh Kebenaran, Keberanian Mengungkap Kebenaran, Kesadaran untuk Jujur, Perbuatan sesuai dengan perkataan.',
            ],
            [
                'nomor'     => 3,
                'nama'      => 'Disiplin',
                'deskripsi' => 'Ketertiban Sikap, Ketertiban Berkomunikasi, Ketertiban Waktu, Patuh pada Peraturan dan Ketentuan, Patuh pada Perintah.',
            ],
            [
                'nomor'     => 4,
                'nama'      => 'Kewibawaan',
                'deskripsi' => 'Penampilan, Mempengaruhi Orang Lain, Keberanian Tindakan, Kepercayaan Diri.',
            ],
            [
                'nomor'     => 5,
                'nama'      => 'Inisiatif',
                'deskripsi' => 'Kemampuan Menciptakan, Memanfaatkan Kesempatan, Menemukan Cara Kerja, Mengambil Risiko.',
            ],
            [
                'nomor'     => 6,
                'nama'      => 'Sosiobilitas',
                'deskripsi' => 'Adaptasi Lingkungan, Adaptasi Kondisi Sosial, Berkomunikasi, Perhatian terhadap Lingkungan.',
            ],
            [
                'nomor'     => 7,
                'nama'      => 'Loyalitas',
                'deskripsi' => 'Rela Berkorban, Patuh pada Peraturan dan Ketentuan, Patuh pada Atasan, Patuh pada Teman dan Kelompok Patuh pada Tugas.',
            ],
            [
                'nomor'     => 8,
                'nama'      => 'Tanggung  Jawab',
                'deskripsi' => 'Taat pada Peraturan/ Ketentuan, Rela Mengemban Tugas, Sedia Mengemban Tugas, Menerima Resiko.',
            ],
            [
                'nomor'     => 9,
                'nama'      => 'Kedewasaan',
                'deskripsi' => 'Keseimbangan Emosi dan Sikap Rasional, Kerja Sama, Pengendalian Diri, Penyesuaian terhadap Tugas.',
            ],
            [
                'nomor'     => 10,
                'nama'      => 'Ketabahan',
                'deskripsi' => 'Keuletan, Semangat, Pengendalian Diri, Rasional dan Realistis, Daya Tahan.',
            ],
        ];

        foreach ($aspek as $item) {
            AspekKepribadian::create([
                'nomor'     => $item['nomor'],
                'nama'      => $item['nama'],
                'deskripsi' => $item['deskripsi'],
                'aktif'     => true,
            ]);
        }

        $this->command->info('✅ Berhasil import ' . count($aspek) . ' aspek kepribadian dari database lama.');
    }
}
