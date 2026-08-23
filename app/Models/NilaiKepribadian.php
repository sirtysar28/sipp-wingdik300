<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NilaiKepribadian extends Model {
    protected $table    = 'nilai_kepribadian';
    protected $fillable = ['peserta_didik_id','periode_nilai_id','nilai_akhir','penjelasan','rekomendasi','input_oleh'];
    protected $casts    = ['nilai_akhir' => 'float'];

    public function peserta()  { return $this->belongsTo(PesertaDidik::class, 'peserta_didik_id'); }
    public function periode()  { return $this->belongsTo(PeriodeNilai::class, 'periode_nilai_id'); }
    public function detail()   { return $this->hasMany(DetailKepribadian::class); }
    public function inputOleh(){ return $this->belongsTo(User::class, 'input_oleh'); }

    // Konstanta poin kriteria
    public static function poinKriteria(string $kriteria): float {
        return match($kriteria) {
            'BS' =>  0.5,
            'B'  =>  0.25,
            'C'  =>  0.0,
            'K'  => -0.25,
            'KS' => -0.5,
            default => 0.0,
        };
    }

    // Hitung ulang nilai akhir dari detail
    public function hitungNilaiAkhir(): float {
        $totalPoin = $this->detail->sum('poin');
        return round(75 + $totalPoin, 2);
    }
}
