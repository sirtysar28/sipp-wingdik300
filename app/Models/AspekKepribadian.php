<?php
// app/Models/AspekKepribadian.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AspekKepribadian extends Model {
    protected $table    = 'aspek_kepribadian';
    protected $fillable = ['nomor','nama','deskripsi','aktif','jenjang'];

    public function detailKepribadian() {
        return $this->hasMany(DetailKepribadian::class);
    }

    /**
     * Revisi 9 November 2026 — aspek aktif yang BERLAKU utk golongan sekolah.
     * - jenjang NULL/kosong : universal (tamtara/bintara/perwira/PNS)
     * - jenjang 'perwira'   : tambahan khusus Sekolah Perwira (aspek 11–15)
     * Golongan selain perwira hanya melihat aspek universal (10 eksisting).
     */
    public static function untukJenjang(?string $jenjang)
    {
        return static::where('aktif', true)
            ->where(function ($q) use ($jenjang) {
                $q->whereNull('jenjang')->orWhere('jenjang', '');
                if ($jenjang) {
                    $q->orWhere('jenjang', $jenjang);
                }
            })
            ->orderBy('nomor');
    }

    /** Aspek aktif berdasar angkatan (jenjang diselesaikan dari skadik). */
    public static function untukAngkatan($angkatan)
    {
        return static::untukJenjang($angkatan?->skadik?->jenjang ?? null);
    }

    /**
     * Pastikan aspek kepribadian default tersedia. Jika tabel kosong
     * (mis. database tertimpa), otomatis diisi ulang dari data baku.
     * Mengembalikan true jika baru saja melakukan re-seed.
     */
    public static function ensureSeeded(): bool
    {
        if (static::query()->exists()) {
            return false;
        }

        // 10 aspek universal (semua golongan: tamtama/bintara/perwira/PNS)
        $defaults = [
            ['Mental dan Moral',     'Ketakwaan, Keyakinan dan Pengenalan Pancasila dan UUD 45, Sikap Semangat Juang, Toleransi, Budi Luhur.', null],
            ['Kejujuran',            'Pegang Teguh Kebenaran, Keberanian Mengungkap Kebenaran, Kesadaran untuk Jujur, Perbuatan sesuai dengan perkataan.', null],
            ['Disiplin',             'Ketertiban Sikap, Ketertiban Berkomunikasi, Ketertiban Waktu, Patuh pada Peraturan dan Ketentuan, Patuh pada Perintah.', null],
            ['Kewibawaan',           'Penampilan, Mempengaruhi Orang Lain, Keberanian Tindakan, Kepercayaan Diri.', null],
            ['Inisiatif',            'Kemampuan Menciptakan, Memanfaatkan Kesempatan, Menemukan Cara Kerja, Mengambil Risiko.', null],
            ['Sosiobilitas',         'Adaptasi Lingkungan, Adaptasi Kondisi Sosial, Berkomunikasi, Perhatian terhadap Lingkungan.', null],
            ['Loyalitas',            'Rela Berkorban, Patuh pada Peraturan dan Ketentuan, Patuh pada Atasan, Patuh pada Teman dan Kelompok Patuh pada Tugas.', null],
            ['Tanggung  Jawab',      'Taat pada Peraturan/ Ketentuan, Rela Mengemban Tugas, Sedia Mengemban Tugas, Menerima Resiko.', null],
            ['Kedewasaan',           'Keseimbangan Emosi dan Sikap Rasional, Kerja Sama, Pengendalian Diri, Penyesuaian terhadap Tugas.', null],
            ['Ketabahan',            'Keuletan, Semangat, Pengendalian Diri, Rasional dan Realistis, Daya Tahan.', null],
            // Revisi 9 November 2026 — 5 aspek tambahan KHUSUS PERWIRA (11–15):
            // hanya muncul di NPK golongan Perwira (total 15 item).
            ['Kemampuan Mengenali Masalah',   'Pengenalan Masalah, Pemilihan Solusi, Keputusan Pemecahan Masalah.', 'perwira'],
            ['Kemampuan Menyatakan Pendapat', 'Kemantapan Pendapat, Penggunaan Kata, Kejelasan Pernyataan, Konsistensi Pendapat, Rasional Pendapat.', 'perwira'],
            ['Ketekunan',                     'Konsentrasi, Upaya Penyelesaian Tugas, Pemanfaatan Waktu yang Efektif dan Efisien.', 'perwira'],
            ['Kemampuan Mengorganisasikan',   'Keyakinan Tujuan, Membagi Tugas, Membangkitkan Kerja Sama, Identifikasi Kemampuan Orang Lain.', 'perwira'],
            ['Kemampuan Memutuskan',          'Kecepatan dan Ketepatan, Prioritas Penyelesaian, Mengatasi Ketidakpastian.', 'perwira'],
        ];

        foreach ($defaults as $i => [$nama, $deskripsi, $jenjang]) {
            static::create([
                'nomor'     => $i + 1,
                'nama'      => $nama,
                'deskripsi' => $deskripsi,
                'aktif'     => true,
                'jenjang'   => $jenjang,
            ]);
        }
        return true;
    }
}
