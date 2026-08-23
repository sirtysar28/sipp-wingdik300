<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DetailKepribadian extends Model {
    protected $table    = 'detail_kepribadian';
    protected $fillable = ['nilai_kepribadian_id','aspek_kepribadian_id','kriteria','poin'];
    protected $casts    = ['poin' => 'float'];

    public function nilaiKepribadian() { return $this->belongsTo(NilaiKepribadian::class); }
    public function aspek()            { return $this->belongsTo(AspekKepribadian::class, 'aspek_kepribadian_id'); }
}
