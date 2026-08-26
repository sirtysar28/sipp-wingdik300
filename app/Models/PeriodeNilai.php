<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PeriodeNilai extends Model {
    protected $table = 'periode_nilai';
    protected $fillable = ['angkatan_id', 'label', 'tanggal_mulai', 'tanggal_selesai', 'aktif'];
    protected $casts = ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date'];
    public function angkatan() { return $this->belongsTo(Angkatan::class); }
    public function nilai() { return $this->hasMany(Nilai::class); }
    public function nilaiKepribadian() { return $this->hasMany(NilaiKepribadian::class); }
}
