<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Nilai extends Model {
    protected $table = 'nilai';
    protected $fillable = ['peserta_didik_id', 'periode_nilai_id', 'akademik', 'fisik', 'sikap', 'kepemimpinan', 'input_oleh'];
    protected $casts = ['akademik' => 'float', 'fisik' => 'float', 'sikap' => 'float', 'kepemimpinan' => 'float', 'total' => 'float'];
    public function peserta() { return $this->belongsTo(PesertaDidik::class, 'peserta_didik_id'); }
    public function periode() { return $this->belongsTo(PeriodeNilai::class, 'periode_nilai_id'); }
    public function inputOleh() { return $this->belongsTo(User::class, 'input_oleh'); }
}
