<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NilaiAkademik extends Model {
    protected $table = 'nilai_akademik';
    protected $fillable = [
        'peserta_didik_id', 'angkatan_id', 'detail_nilai', 'jumlah_nilai', 'npa', 'rank', 'input_oleh'
    ];
    protected $casts = [
        'detail_nilai'  => 'array',
        'jumlah_nilai'  => 'float',
        'npa'           => 'float',
    ];

    public function peserta()  { return $this->belongsTo(PesertaDidik::class, 'peserta_didik_id'); }
    public function angkatan() { return $this->belongsTo(Angkatan::class); }
    public function inputOleh(){ return $this->belongsTo(User::class, 'input_oleh'); }
}
