<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class KompilasiNilai extends Model {
    protected $table = 'kompilasi_nilai';
    protected $fillable = [
        'peserta_didik_id', 'angkatan_id', 'nilai_akademik_id',
        'nilai_akademik', 'bobot_akademik',
        'nilai_kepribadian', 'bobot_kepribadian',
        'nilai_samapta', 'bobot_samapta',
        'nilai_akhir', 'predikat_angka', 'predikat_huruf', 'rank',
        'input_oleh',
    ];
    protected $casts = [
        'nilai_akademik'    => 'float',
        'bobot_akademik'    => 'float',
        'nilai_kepribadian' => 'float',
        'bobot_kepribadian' => 'float',
        'nilai_samapta'     => 'float',
        'bobot_samapta'     => 'float',
        'nilai_akhir'       => 'float',
        'predikat_angka'    => 'float',
    ];

    public function peserta()        { return $this->belongsTo(PesertaDidik::class, 'peserta_didik_id'); }
    public function angkatan()       { return $this->belongsTo(Angkatan::class); }
    public function nilaiAkademik()  { return $this->belongsTo(NilaiAkademik::class); }
    public function inputOleh()      { return $this->belongsTo(User::class, 'input_oleh'); }

    /**
     * Hitung predikat huruf berdasarkan nilai akhir
     */
    public static function getPredikat($nilai): array
    {
        if ($nilai >= 92) return ['angka' => 4.00, 'huruf' => 'A'];
        if ($nilai >= 84) return ['angka' => 3.50, 'huruf' => 'B+'];
        if ($nilai >= 76) return ['angka' => 3.00, 'huruf' => 'B'];
        if ($nilai >= 68) return ['angka' => 2.50, 'huruf' => 'C+'];
        if ($nilai >= 60) return ['angka' => 2.00, 'huruf' => 'C'];
        if ($nilai >= 50) return ['angka' => 1.50, 'huruf' => 'D'];
        return ['angka' => 1.00, 'huruf' => 'E'];
    }
}
