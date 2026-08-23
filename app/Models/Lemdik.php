<?php
// app/Models/Lemdik.php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lemdik extends Model {
    use HasFactory;
    protected $table = 'lemdik';
    protected $fillable = ['nama', 'kode', 'alamat', 'kota', 'wingdik', 'aktif'];

    public function skadik() {
        return $this->hasMany(Skadik::class);
    }

    /**
     * Nama ringkas skadron: "Skadron Pendidikan 302" / kode "SKADIK302"
     * → "Skadik 302". Dipakai di Manage User agar tampilan ringkas.
     */
    public function getSingkatAttribute(): string
    {
        $src = trim(($this->nama ?? '') . ' ' . ($this->kode ?? ''));
        if (preg_match('/(\d{3})/', $src, $m)) {
            return 'Skadik ' . $m[1];
        }
        return $this->nama ?: ($this->kode ?: 'Lembaga');
    }
}
