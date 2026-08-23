<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\MataPelajaran;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Skadik extends Model {
    use HasFactory;
    protected $table = 'skadik';
    protected $fillable = ['lemdik_id', 'nama', 'kode', 'keterangan', 'kepala_sekolah', 'pangkat_kepala', 'nrp_kepala', 'jenjang', 'jenis_pendidikan'];

    public function lemdik() {
        return $this->belongsTo(Lemdik::class);
    }

    public function angkatan() {
        return $this->hasMany(Angkatan::class);
    }

    public function mataPelajaran() {
        return $this->hasMany(MataPelajaran::class);
    }

    public function users() {
        return $this->belongsToMany(User::class, 'user_skadik');
    }

    /**
     * Label ringkas tugas sekolah seorang user.
     *
     * Jika user memiliki SEMUA sekolah dari sebuah Lemdik (Skadron, mis.
     * 301/302/303/304), maka ditampilkan satu label ringkas "Skadik XXX"
     * alih-alih nama-nama sekolah individual. Sehingga baris di tabel Manage
     * User tetap ringkas meski user mengelola banyak sekolah.
     *
     * @param \Illuminate\Support\Collection $assigned       Skadik yg ditugaskan (relasi lemdik harus ter-load)
     * @param array                          $lemdikCounts   Map lemdik_id => jumlah total skadik di skadron tsb
     * @return array<int,string>  Daftar label untuk ditampilkan sebagai badge
     */
    public static function compactLabels(\Illuminate\Support\Collection $assigned, array $lemdikCounts): array
    {
        if ($assigned->isEmpty()) return [];

        $labels = [];
        foreach ($assigned->groupBy('lemdik_id') as $lemdikId => $group) {
            $total = $lemdikCounts[$lemdikId] ?? 0;
            $lemdik = $group->first()->lemdik ?? null;

            // Cakup seluruh skadron → tampilan ringkas "Skadik XXX".
            if ($total > 0 && $group->count() >= $total) {
                $labels[] = $lemdik?->singkat ?? ('Skadron ' . ($lemdik?->nama ?? ''));
                continue;
            }

            // Sebagian skadron saja → tetap tampilkan nama tiap sekolah.
            foreach ($group as $sk) {
                $labels[] = $sk->nama_singkat ?? $sk->nama;
            }
        }
        return $labels;
    }

    /**
     * Daftar Skadik (Sekolah) yang BOLEH diakses user yg sedang login.
     * - super_admin / admin : semua skadik yg punya angkatan aktif
     * - admin modul         : hanya skadik yg ditugaskan kepadanya
     *
     * Digunakan semua controller untuk membangun dropdown filter agar data
     * ter-scope sesuai wewenang user.
     */
    public static function listForUser() {
        $query = self::whereHas('angkatan', fn($q) => $q->where('aktif', true))->with('lemdik');

        $user = auth()->user();
        if ($user && !$user->canSeeAll()) {
            $ids = $user->assignedSkadikIds();
            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            } else {
                // belum ditugaskan skadik apapun → hasil kosong
                $query->whereRaw('1 = 0');
            }
        }
        return $query->orderBy('nama')->get();
    }

    /**
     * Nama singkat untuk tampilan kop surat / judul laporan.
     * Membuang deskripsi panjang dalam tanda kurung, mis:
     *   "AFS (Pemeliharaan Sistem Fuel Pesawat Terbang A-22)" → "AFS"
     */
    public function getNamaSingkatAttribute(): string {
        $nama = trim($this->nama ?? '');
        // Buang konten dalam tanda kurung ( ) atau [ ]
        $stripped = trim(preg_replace('/\s*[\(\[].*?[\)\]]\s*/u', ' ', $nama));
        if ($stripped !== '') {
            return $stripped;
        }
        // fallback: kode, lalu nama asli
        return trim($this->kode ?? '') !== '' ? $this->kode : ($nama ?: 'SEKOLAH');
    }

    /**
     * Daftar opsi Jenis Pendidikan (radio, pilih salah satu).
     */
    public const JENIS_PENDIDIKAN_OPTIONS = [
        'dikbangspes'  => 'Dikbangspes',
        'dikcabpa'     => 'Dikcabpa',
        'dikjurbata'   => 'Dikjurbata',
        'dikjurPNS'    => 'Dikjur PNS',
        'dikmatuklih'  => 'Dikmatuklih',
    ];

    /**
     * Daftar opsi Jenjang (radio, pilih salah satu).
     */
    public const JENJANG_OPTIONS = [
        'perwira'  => 'Perwira',
        'bintara'  => 'Bintara',
        'tamtama'  => 'Tamtama',
        'pns'      => 'PNS',
    ];

    /**
     * Nilai awal NPK berdasarkan jenjang:
     * - perwira = 80
     * - bintara / tamtama / pns = 70
     */
    public function getStartingNpkAttribute(): int
    {
        return $this->jenjang === 'perwira' ? 80 : 70;
    }

    public function getJenjangLabelAttribute(): string
    {
        return self::JENJANG_OPTIONS[$this->jenjang] ?? ucfirst((string) $this->jenjang);
    }

    public function getJenisPendidikanLabelAttribute(): string
    {
        return self::JENIS_PENDIDIKAN_OPTIONS[$this->jenis_pendidikan] ?? ucfirst((string) $this->jenis_pendidikan);
    }
}
