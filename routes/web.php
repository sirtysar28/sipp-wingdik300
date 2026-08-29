<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController, DashboardController, LeaderboardController,
    PesertaController, NilaiController, LemdikController, SkadikController,
    AngkatanController, RekapController, KepribadianController,
    NilaiAkademikController, NilaiSamaptaController,
    KompilasiNilaiController, LaporanController, UserController,
    ReportController, PenandatanganController, MataPelajaranController,
    PasswordResetController, SmtpSettingController
};

Route::get('/', fn() => redirect('/dashboard'));

// ══════════════════════════════════════════════════════════════
//  BACKWARD COMPAT: /skadik/* → /sekolah/*
//  (URL master data Skadik/Sekolah dipindahkan ke /sekolah. Redirect ini
//   menjaga bookmark / link lama tetap berfungsi.)
// ══════════════════════════════════════════════════════════════
Route::redirect('/skadik', '/sekolah', 301);
Route::get('/skadik/{any}', fn($any = '') => redirect('/sekolah/'.$any, 301))->where('any', '.*');

Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout',[AuthController::class, 'logout'])->name('logout');

// ══════════════════════════════════════════════════════════════
//  LUPA PASSWORD / RESET PASSWORD (tanpa login)
//  User klik "Lupa Password?" di halaman login → input email →
//  link reset dikirim ke email terdaftar via SMTP yang dikonfigurasi
//  Super Admin di menu Pengaturan SMTP.
// ══════════════════════════════════════════════════════════════
Route::get('/forgot-password',      [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('/forgot-password',     [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password',      [PasswordResetController::class, 'resetPassword'])->name('password.update');

// ══════════════════════════════════════════════════════════════
//  ROUTES YANG BISA DIAKSES SEMUA ROLE YANG LOGIN
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard',  [DashboardController::class,  'index'])->name('dashboard');
    Route::post('/dashboard/periode/{periode}/aktifkan', [DashboardController::class, 'aktifkanPeriode'])->name('dashboard.periode.aktifkan');
});

// Leaderboard: super_admin, admin only (per Module Tracker)
Route::middleware(['auth', 'role:super_admin,admin'])->group(function () {
    Route::get('/leaderboard',[LeaderboardController::class,'index'])->name('leaderboard');
    Route::get('/leaderboard/ekspor', [LeaderboardController::class,'ekspor'])->name('leaderboard.ekspor');
});

// ══════════════════════════════════════════════════════════════
//  MODUL KEPRIBADIAN — admin_kepribadian, admin, instruktur
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:admin_kepribadian,admin'])->group(function () {
    Route::get('/kepribadian', [KepribadianController::class,'index'])->name('kepribadian.index');

    // ── PERIODE NPK ──
    // PENTING: route periode WAJIB didaftarkan SEBELUM wildcard
    // /kepribadian/{peserta} dan wildcard {peserta} dibatasi numerik.
    // Revisi 26 Agustus 2026: sebelumnya POST /kepribadian/periode tertimpa
    // wildcard POST /kepribadian/{peserta} (peserta='periode') sehingga
    // Danflight (admin_kepribadian) kena error 404 saat Buat Periode Baru
    // di menu NPK, sementara Opsdik (admin) selamat karena memakai
    // route /nilai/periode yang berbeda.
    Route::get('/kepribadian/periode/create',  [KepribadianController::class,'createPeriode'])->name('kepribadian.periode.create');
    Route::post('/kepribadian/periode',         [KepribadianController::class,'storePeriode'])->name('kepribadian.periode.store');
    // Revisi 26 Agustus 2026: hard delete periode NPK + seluruh isinya
    Route::delete('/kepribadian/periode/{periode}', [KepribadianController::class,'destroyPeriode'])->name('kepribadian.periode.destroy');

    Route::get('/kepribadian/aspek',            [KepribadianController::class,'aspekIndex'])->name('kepribadian.aspek');
    Route::post('/kepribadian/aspek',           [KepribadianController::class,'aspekStore'])->name('kepribadian.aspek.store');
    Route::put('/kepribadian/aspek/{aspek}',    [KepribadianController::class,'aspekUpdate'])->name('kepribadian.aspek.update');
    Route::delete('/kepribadian/aspek/{aspek}', [KepribadianController::class,'aspekDestroy'])->name('kepribadian.aspek.destroy');
    Route::get('/kepribadian/import',           [KepribadianController::class,'importForm'])->name('kepribadian.import.form');
    Route::post('/kepribadian/import',          [KepribadianController::class,'import'])->name('kepribadian.import');
    Route::get('/kepribadian/template',         [KepribadianController::class,'downloadTemplate'])->name('kepribadian.template');
    Route::get('/kepribadian/ekspor', [KepribadianController::class,'ekspor'])->name('kepribadian.ekspor');

    // Wildcard peserta: dibatasi ID numerik agar tidak menimpa route literal di atas
    Route::get('/kepribadian/{peserta}/show', [KepribadianController::class,'show'])
        ->name('kepribadian.show')->whereNumber('peserta');
    Route::get('/kepribadian/{peserta}/form',  [KepribadianController::class,'form'])
        ->name('kepribadian.form')->whereNumber('peserta');
    Route::post('/kepribadian/{peserta}',      [KepribadianController::class,'store'])
        ->name('kepribadian.store')->whereNumber('peserta');
});

// ══════════════════════════════════════════════════════════════
//  MODUL NILAI AKADEMIK (NPA) — admin_akademik, super_admin, admin
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:admin_akademik,super_admin,admin'])->prefix('nilai-akademik')->name('nilai-akademik.')->group(function () {
    Route::get('/',             [NilaiAkademikController::class, 'index'])->name('index');
    Route::get('/import',       [NilaiAkademikController::class, 'importForm'])->name('import.form');
    Route::post('/import',      [NilaiAkademikController::class, 'import'])->name('import');
    Route::get('/manual',       [NilaiAkademikController::class, 'manualForm'])->name('manual.form');
    Route::post('/manual',      [NilaiAkademikController::class, 'manualStore'])->name('manual.store');
    Route::get('/edit',         [NilaiAkademikController::class, 'editForm'])->name('edit');
    Route::post('/update',      [NilaiAkademikController::class, 'update'])->name('update');
    Route::post('/destroy',     [NilaiAkademikController::class, 'destroy'])->name('destroy');
    Route::post('/recalculate', [NilaiAkademikController::class, 'recalculate'])->name('recalculate');
    Route::get('/ekspor',       [NilaiAkademikController::class, 'ekspor'])->name('ekspor');
});

// ══════════════════════════════════════════════════════════════
//  MODUL NILAI SAMAPTA (NPS) — admin_samapta, super_admin, admin
//  Versi sederhana: 5 field manual (Jarak Lari, Nilai Lari/Garjas A,
//  Garjas B, Nilai Akhir, Nilai Konversi). TANPA rumus.
//  Input via bulk upload + template. Putaran membedakan input.
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:admin_samapta,super_admin,admin'])->prefix('nilai-samapta')->name('nilai-samapta.')->group(function () {
    Route::get('/',          [NilaiSamaptaController::class, 'index'])->name('index');
    Route::get('/template',  [NilaiSamaptaController::class, 'downloadTemplate'])->name('template');
    Route::post('/import',   [NilaiSamaptaController::class, 'import'])->name('import');
    Route::get('/manual',    [NilaiSamaptaController::class, 'manualForm'])->name('manual.form');
    Route::post('/manual',   [NilaiSamaptaController::class, 'manualStore'])->name('manual.store');
    Route::get('/edit',      [NilaiSamaptaController::class, 'editForm'])->name('edit');
    Route::post('/update',   [NilaiSamaptaController::class, 'update'])->name('update');
    Route::delete('/destroy',[NilaiSamaptaController::class, 'destroy'])->name('destroy');
    Route::get('/ekspor',    [NilaiSamaptaController::class, 'ekspor'])->name('ekspor');
    Route::get('/cetak',     [NilaiSamaptaController::class, 'cetak'])->name('cetak');
    Route::get('/putaran/create', [NilaiSamaptaController::class, 'createPeriode'])->name('putaran.create');
    Route::post('/putaran',      [NilaiSamaptaController::class, 'storePeriode'])->name('putaran.store');
});

// ══════════════════════════════════════════════════════════════
//  MODUL KOMPILASI NILAI — super_admin, admin (internal, not in sidebar)
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin,admin'])->prefix('kompilasi')->name('kompilasi.')->group(function () {
    Route::get('/',         [KompilasiNilaiController::class, 'index'])->name('index');
    Route::post('/proses',  [KompilasiNilaiController::class, 'proses'])->name('proses');
    Route::get('/ekspor',   [KompilasiNilaiController::class, 'ekspor'])->name('ekspor');
});

// ══════════════════════════════════════════════════════════════
//  REPORT NPP (ANGKATAN) — super_admin, admin, admin_akademik
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin,admin,admin_akademik'])->group(function () {
    Route::get('/report/npp',      [ReportController::class, 'reportNPP'])->name('report.npp');
    Route::get('/report/npp/ekspor',[KompilasiNilaiController::class, 'ekspor'])->name('report.npp.ekspor');
});

// ══════════════════════════════════════════════════════════════
//  MODUL CETAK LAPORAN (NPP) — super_admin, admin
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin,admin'])->prefix('laporan')->name('laporan.')->group(function () {
    Route::get('/',              [LaporanController::class, 'index'])->name('index');
    Route::get('/cetak-individu',[LaporanController::class, 'cetakIndividu'])->name('cetak.individu');
    Route::get('/cetak-semua',  [LaporanController::class, 'cetakSemua'])->name('cetak.semua');
    Route::get('/ekspor-semua', [LaporanController::class, 'eksporSemua'])->name('ekspor.semua');
});

// ══════════════════════════════════════════════════════════════
//  REPORT — NPA, NPK, NPS, INDIVIDUAL
// ══════════════════════════════════════════════════════════════
//  Revisi 29 Agustus 2026 - pembagian hak CETAK PDF:
//  - PDF NPA -> super_admin, Opsdik(admin), Kepala Sekolah (admin_akademik)
//  - PDF NPK -> super_admin, Opsdik(admin), Danflight (admin_kepribadian)
//  - PDF NPS -> super_admin, Opsdik(admin), Binjaswing (admin_samapta)
//  - PDF NPP -> HANYA super_admin + Opsdik (lihat grup /laporan)
// ===============================================================
Route::middleware(['auth', 'role:super_admin,admin,admin_akademik,admin_kepribadian,admin_samapta'])->group(function () {
    // Report NPA (akademik bisa lihat)
    Route::get('/report/npa',      [ReportController::class, 'reportNPA'])->name('report.npa');
    Route::get('/report/npa/ekspor',[ReportController::class, 'eksporNPA'])->name('report.npa.ekspor');

    // Report NPK (kepribadian bisa lihat) - redirect ke Rekap (satu sumber)
    Route::get('/report/npk',      [ReportController::class, 'reportNPK'])->name('report.npk');
    Route::get('/report/npk/ekspor',[ReportController::class, 'eksporNPK'])->name('report.npk.ekspor');

    // Report NPS (samapta bisa lihat)
    Route::get('/report/nps',      [ReportController::class, 'reportNPS'])->name('report.nps');
    Route::get('/report/nps/ekspor',[ReportController::class, 'eksporNPS'])->name('report.nps.ekspor');
});

// -- CETAK PDF NPA: super_admin + Opsdik + Kepala Sekolah --
Route::middleware(['auth', 'role:super_admin,admin,admin_akademik'])->group(function () {
    Route::get('/report/npa/cetak', [ReportController::class, 'cetakNPA'])->name('report.npa.cetak');
});

// -- CETAK PDF NPK: super_admin + Opsdik + Danflight --
Route::middleware(['auth', 'role:super_admin,admin,admin_kepribadian'])->group(function () {
    Route::get('/report/npk/cetak', [ReportController::class, 'cetakNPK'])->name('report.npk.cetak');
});

// -- CETAK PDF NPS: super_admin + Opsdik + Binjaswing --
Route::middleware(['auth', 'role:super_admin,admin,admin_samapta'])->group(function () {
    Route::get('/report/nps/cetak', [NilaiSamaptaController::class, 'cetak'])->name('report.nps.cetak');
});

// ══════════════════════════════════════════════════════════════
//  REPORT INDIVIDUAL — super_admin, admin, admin_akademik SAJA
//  (Revisi 13 Agustus 2026: Danflight & BinJas TIDAK boleh melihat
//   hasil individu akhir pendidikan.)
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin,admin,admin_akademik'])->group(function () {
    Route::get('/report/individu', [ReportController::class, 'reportIndividu'])->name('report.individu');
});

// ══════════════════════════════════════════════════════════════
//  SUPER ADMIN SKADIK SWITCHER
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth'])->post('/set-skadik', function (\Illuminate\Http\Request $request) {
    $skadikId = $request->input('skadik_id');
    if ($skadikId) {
        session(['active_skadik_id' => $skadikId]);
    } else {
        session()->forget('active_skadik_id');
    }
    return back();
});

// ══════════════════════════════════════════════════════════════
//  MODUL NILAI (lama) — admin
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/nilai',                   [NilaiController::class,'index'])->name('nilai.index');
    Route::post('/nilai',                  [NilaiController::class,'store'])->name('nilai.store');
    Route::post('/nilai/bulk',             [NilaiController::class,'bulkStore'])->name('nilai.bulk');
    Route::get('/nilai/periode/create',    [NilaiController::class,'createPeriode'])->name('nilai.periode.create');
    Route::post('/nilai/periode',          [NilaiController::class,'storePeriode'])->name('nilai.periode.store');
    Route::get('/nilai/ekspor',            [NilaiController::class,'eksporXlsx'])->name('nilai.ekspor');
    Route::get('/nilai/ekspor-leaderboard',[NilaiController::class,'eksporLeaderboard'])->name('nilai.ekspor.leaderboard');
    Route::get('/nilai/import',            [NilaiController::class,'importForm'])->name('nilai.import.form');
    Route::post('/nilai/import',           [NilaiController::class,'import'])->name('nilai.import');
    Route::get('/nilai/template',          [NilaiController::class,'downloadTemplate'])->name('nilai.template');
});

// ══════════════════════════════════════════════════════════════
//  MASTER DATA — super_admin SAJA
//  (Revisi 13 Agustus 2026: modul Master Data di-takeout dari role
//   Opsdik/admin. Hanya WingDik/super_admin yang mengelola data
//   struktur: Lembaga, Sekolah, Angkatan, Peserta, dan User.)
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/peserta',                [PesertaController::class,'index'])->name('peserta.index');
    Route::get('/peserta/create',         [PesertaController::class,'create'])->name('peserta.create');
    Route::post('/peserta',               [PesertaController::class,'store'])->name('peserta.store');
    Route::get('/peserta/{peserta}/edit', [PesertaController::class,'edit'])->name('peserta.edit');
    Route::put('/peserta/{peserta}',      [PesertaController::class,'update'])->name('peserta.update');
    Route::delete('/peserta/{peserta}',   [PesertaController::class,'destroy'])->name('peserta.destroy');
    Route::delete('/peserta/{peserta}/force', [PesertaController::class,'forceDelete'])->name('peserta.force-delete');
    Route::get('/peserta/import',         [PesertaController::class,'importForm'])->name('peserta.import.form');
    Route::post('/peserta/import',        [PesertaController::class,'import'])->name('peserta.import');
    Route::get('/peserta/template',       [PesertaController::class,'downloadTemplate'])->name('peserta.template');
    Route::resource('lemdik',  LemdikController::class)->except(['show']);
    // URL /sekolah (sebelumnya /skadik) agar tidak tertukar dengan skadik-switcher.
    // Route name tetap 'skadik.*' supaya semua route('skadik.index') di view tidak berubah.
    Route::resource('sekolah', SkadikController::class)
        ->except(['show'])
        ->names('skadik')
        // Parameter binding pakai 'skadik' agar cocok dgn type-hint variabel
        // `Skadik $skadik` di controller. Tanpa ini, implicit model binding
        // gagal (route param default 'sekolah') & route('skadik.update', $skadik)
        // melempar UrlGenerationException: Missing parameter: sekolah.
        ->parameters(['sekolah' => 'skadik']);
    Route::resource('angkatan', AngkatanController::class)->except(['show']);
    Route::get('/manage-user',             [UserController::class, 'index'])->name('manage-user.index');
    Route::post('/manage-user',            [UserController::class, 'store'])->name('manage-user.store');
    Route::put('/manage-user/{id}',        [UserController::class, 'update'])->name('manage-user.update');
    Route::delete('/manage-user/{id}',     [UserController::class, 'destroy'])->name('manage-user.destroy');

    // ── PENGATURAN SMTP (super_admin saja) ──
    // Email aktif pengirim untuk notifikasi reset password (lupa password).
    Route::get('/pengaturan-smtp',         [SmtpSettingController::class, 'index'])->name('smtp-settings.index');
    Route::post('/pengaturan-smtp',        [SmtpSettingController::class, 'save'])->name('smtp-settings.save');
    Route::post('/pengaturan-smtp/test',   [SmtpSettingController::class, 'test'])->name('smtp-settings.test');

    // Mata Pelajaran dipindahkan ke grup NPA (lihat bawah) agar bisa diakses
    // super_admin, admin, dan admin_akademik (admin NPA).
});

// ══════════════════════════════════════════════════════════════
//  PENANDATANGAN — super_admin, admin, admin_akademik,
//  admin_kepribadian, admin_samapta
//  (Revisi 13 Agustus 2026: fitur penandatanganan ditambahkan ke
//   Kepala Sekolah, Danflight & BinJas agar bisa membubuhkan tanda
//   tangan pada laporan NPA/NPK/NPS. Module-admin hanya mengelola
//   signer sekolahnya sendiri — lihat Skadik::listForUser().)
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin,admin,admin_akademik,admin_kepribadian,admin_samapta'])->group(function () {
    Route::get('/penandatangan',          [PenandatanganController::class, 'index'])->name('penandatangan.index');
    Route::post('/penandatangan',         [PenandatanganController::class, 'store'])->name('penandatangan.store');
    Route::put('/penandatangan/{id}',     [PenandatanganController::class, 'update'])->name('penandatangan.update');
    Route::delete('/penandatangan/{id}',  [PenandatanganController::class, 'destroy'])->name('penandatangan.destroy');
});

// Kepala Sekolah: kelola angkatan & mata pelajaran
Route::middleware(['auth', 'role:admin_akademik,super_admin,admin'])->prefix('manage-akademik')->name('manage-akademik.')->group(function () {
    Route::get('/angkatan', [AngkatanController::class, 'index'])->name('angkatan.index');
    Route::get('/angkatan/create', [AngkatanController::class, 'create'])->name('angkatan.create');
    Route::post('/angkatan', [AngkatanController::class, 'store'])->name('angkatan.store');
    Route::get('/angkatan/{angkatan}/edit', [AngkatanController::class, 'edit'])->name('angkatan.edit');
    Route::put('/angkatan/{angkatan}', [AngkatanController::class, 'update'])->name('angkatan.update');
    Route::delete('/angkatan/{angkatan}', [AngkatanController::class, 'destroy'])->name('angkatan.destroy');
});

// ══════════════════════════════════════════════════════════════
//  MATA PELAJARAN (mapping ke sekolah) — super_admin, admin, admin_akademik
//  1 matpel bisa dipetakan ke banyak sekolah (many-to-many). Menu ini
//  ditempatkan di area NPA (lihat sidebar).
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:super_admin,admin,admin_akademik'])->group(function () {
    Route::get('/mata-pelajaran',                 [MataPelajaranController::class, 'index'])->name('mata-pelajaran.index');
    Route::post('/mata-pelajaran',                [MataPelajaranController::class, 'store'])->name('mata-pelajaran.store');
    Route::post('/mata-pelajaran/map-existing',   [MataPelajaranController::class, 'mapExisting'])->name('mata-pelajaran.map-existing');
    Route::put('/mata-pelajaran/{mataPelajaran}', [MataPelajaranController::class, 'update'])->name('mata-pelajaran.update');
    Route::delete('/mata-pelajaran/{mataPelajaran}', [MataPelajaranController::class, 'destroy'])->name('mata-pelajaran.destroy');
    Route::post('/mata-pelajaran/duplikasi',      [MataPelajaranController::class, 'duplikasi'])->name('mata-pelajaran.duplikasi');
    Route::post('/mata-pelajaran/load-template',  [MataPelajaranController::class, 'loadTemplate'])->name('mata-pelajaran.load-template');
});

// Peserta show (all authenticated)
Route::middleware(['auth', 'role:admin,super_admin,admin_akademik,admin_kepribadian,admin_samapta'])
    ->get('/peserta/{peserta}', [PesertaController::class,'show'])->name('peserta.show');

// ══════════════════════════════════════════════════════════════
//  REKAP & ANALISIS — routes kept internally (removed from sidebar)
// ══════════════════════════════════════════════════════════════
Route::middleware(['auth', 'role:admin,super_admin,admin_kepribadian'])->group(function () {
    Route::get('/rekap',    [RekapController::class,'index'])->name('rekap.index');
    Route::get('/rekap/ekspor', [RekapController::class,'ekspor'])->name('rekap.ekspor');
});
