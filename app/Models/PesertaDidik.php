<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PesertaDidik extends Model {
    use HasFactory;
    protected $table = 'peserta_didik';
    protected $fillable = ['angkatan_id', 'nama', 'nrp', 'pangkat', 'nosis', 'aktif'];

    public function angkatan()          { return $this->belongsTo(Angkatan::class); }
    public function nilai()             { return $this->hasMany(Nilai::class); }
    public function user()              { return $this->hasOne(User::class); }
    public function nilaiKepribadian()  { return $this->hasMany(NilaiKepribadian::class); }
    public function nilaiAkademik()     { return $this->hasOne(NilaiAkademik::class); }
    public function nilaiSamapta()      { return $this->hasOne(NilaiSamapta::class); }
    public function kompilasiNilai()    { return $this->hasOne(KompilasiNilai::class); }

    public function nilaiPeriode($periodeId) {
        return $this->nilai()->where('periode_nilai_id', $periodeId)->first();
    }
    public function getRataRataAttribute() {
        return $this->nilai->avg('total') ?? 0;
    }

    public function getNilaiKepribadianRataAttribute() {
        return $this->nilaiKepribadian->avg('nilai_akhir') ?? 0;
    }
}
