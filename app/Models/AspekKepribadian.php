<?php
// app/Models/AspekKepribadian.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AspekKepribadian extends Model {
    protected $table    = 'aspek_kepribadian';
    protected $fillable = ['nomor','nama','deskripsi','aktif'];

    public function detailKepribadian() {
        return $this->hasMany(DetailKepribadian::class);
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

        $defaults = [
            ['Mental dan Moral',     'Ketakwaan, Keyakinan dan Pengenalan Pancasila dan UUD 45, Sikap Semangat Juang, Toleransi, Budi Luhur.'],
            ['Kejujuran',            'Pegang Teguh Kebenaran, Keberanian Mengungkap Kebenaran, Kesadaran untuk Jujur, Perbuatan sesuai dengan perkataan.'],
            ['Disiplin',             'Ketertiban Sikap, Ketertiban Berkomunikasi, Ketertiban Waktu, Patuh pada Peraturan dan Ketentuan, Patuh pada Perintah.'],
            ['Kewibawaan',           'Penampilan, Mempengaruhi Orang Lain, Keberanian Tindakan, Kepercayaan Diri.'],
            ['Inisiatif',            'Kemampuan Menciptakan, Memanfaatkan Kesempatan, Menemukan Cara Kerja, Mengambil Risiko.'],
            ['Sosiobilitas',         'Adaptasi Lingkungan, Adaptasi Kondisi Sosial, Berkomunikasi, Perhatian terhadap Lingkungan.'],
            ['Loyalitas',            'Rela Berkorban, Patuh pada Peraturan dan Ketentuan, Patuh pada Atasan, Patuh pada Teman dan Kelompok Patuh pada Tugas.'],
            ['Tanggung  Jawab',      'Taat pada Peraturan/ Ketentuan, Rela Mengemban Tugas, Sedia Mengemban Tugas, Menerima Resiko.'],
            ['Kedewasaan',           'Keseimbangan Emosi dan Sikap Rasional, Kerja Sama, Pengendalian Diri, Penyesuaian terhadap Tugas.'],
            ['Ketabahan',            'Keuletan, Semangat, Pengendalian Diri, Rasional dan Realistis, Daya Tahan.'],
        ];

        foreach ($defaults as $i => [$nama, $deskripsi]) {
            static::create([
                'nomor'     => $i + 1,
                'nama'      => $nama,
                'deskripsi' => $deskripsi,
                'aktif'     => true,
            ]);
        }
        return true;
    }
}
