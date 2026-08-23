<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Angkatan extends Model {
    use HasFactory;
    protected $table = 'angkatan';
    protected $fillable = ['skadik_id', 'jurusan', 'nomor_angkatan', 'tahun_masuk', 'aktif'];

    public function skadik() {
        return $this->belongsTo(Skadik::class);
    }

    public function peserta() {
        return $this->hasMany(PesertaDidik::class);
    }

    public function periodeNilai() {
        return $this->hasMany(PeriodeNilai::class);
    }

    public function getNamaLengkapAttribute() {
        return "{$this->skadik->nama} - Angkatan {$this->nomor_angkatan} ({$this->tahun_masuk})";
    }
}
