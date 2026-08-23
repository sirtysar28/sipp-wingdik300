<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable {
    use Notifiable;
    protected $fillable = ['name', 'email', 'password', 'role', 'peserta_didik_id', 'angkatan_id', 'skadik_id'];
    protected $hidden = ['password', 'remember_token'];

    public function peserta() { return $this->belongsTo(PesertaDidik::class, 'peserta_didik_id'); }
    public function angkatan() { return $this->belongsTo(Angkatan::class); }
    public function skadik() { return $this->belongsTo(Skadik::class); }

    /** Relasi many-to-many: satu user bisa mengelola >1 Sekolah/Skadik. */
    public function skadiks()
    {
        return $this->belongsToMany(Skadik::class, 'user_skadik');
    }

    public function isAdmin()       { return $this->role === 'admin'; }

    /**
     * Kirim notifikasi (email) berisi link reset password lupa password.
     * Token dibuat oleh Password broker; email memakai SMTP dari
     * Pengaturan SMTP (dikelola Super Admin).
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
    public function isSuperAdmin()  { return $this->role === 'super_admin'; }
    public function isAdminAkademik()   { return $this->role === 'admin_akademik'; }
    public function isAdminKepribadian(){ return $this->role === 'admin_kepribadian'; }
    public function isAdminSamapta()    { return $this->role === 'admin_samapta'; }

    public function canSeeAll() { return in_array($this->role, ['super_admin', 'admin']); }

    /**
     * Daftar ID Sekolah/Skadik yg boleh diakses user.
     * - super_admin / admin: null = semua skadik
     * - admin modul: gabungan pivot user_skadik (fallback ke skadik_id)
     */
    public function assignedSkadikIds(): ?array
    {
        if ($this->canSeeAll()) return null;

        $ids = $this->skadiks()->pluck('skadik.id')->all();
        if (empty($ids) && $this->skadik_id) {
            $ids = [$this->skadik_id];
        }
        return $ids;
    }

    /**
     * Cek apakah user boleh mengakses sebuah skadik.
     */
    public function canAccessSkadik($skadikId): bool
    {
        if ($this->canSeeAll()) return true;
        $ids = $this->assignedSkadikIds() ?? [];
        return in_array((int)$skadikId, array_map('intval', $ids), true);
    }

    /**
     * Get active skadik ID for filtering data.
     * Super Admin uses session('active_skadik_id'), others use their assigned skadik_id.
     */
    public static function activeSkadikId() {
        $user = auth()->user();
        if (!$user) return null;
        if ($user->role === 'super_admin') {
            return session('active_skadik_id');
        }
        return $user->skadik_id;
    }

    public function getModuleLabelAttribute() {
        return match($this->role) {
            'super_admin'        => 'Super Admin (WingDik)',
            'admin_akademik'     => 'Kepala Sekolah',
            'admin_kepribadian'  => 'Danflight',
            'admin_samapta'      => 'Binjaswing',
            'admin'              => 'Opsdik',
            default              => ucfirst($this->role),
        };
    }
}
