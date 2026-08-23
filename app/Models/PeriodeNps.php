<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodeNps extends Model
{
    protected $table = 'periode_nps';

    protected $fillable = [
        'angkatan_id',
        'label',
        'tanggal_mulai',
        'tanggal_selesai',
        'aktif',
    ];

    protected $casts = [
        'tanggal_mulai'   => 'date',
        'tanggal_selesai' => 'date',
        'aktif'           => 'boolean',
    ];

    public function angkatan()
    {
        return $this->belongsTo(Angkatan::class);
    }
}
