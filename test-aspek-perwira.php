<?php
/**
 * SMOKE TEST — Aspek kepribadian khusus perwira (revisi 9 November 2026)
 * Verifikasi tanpa DB produksi: SQLite in-memory.
 * Jalankan: php test-aspek-perwira.php
 */
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.default' => 'testing', 'database.connections.testing' => [
    'driver'   => 'sqlite',
    'database' => ':memory:',
    'prefix'   => '',
]]);
$capsule = DB::connection('testing');
Schema::connection('testing')->create('aspek_kepribadian', function (Blueprint $t) {
    $t->id();
    $t->integer('nomor')->default(0);
    $t->string('nama');
    $t->text('deskripsi')->nullable();
    $t->boolean('aktif')->default(true);
    $t->timestamps();
});

// ==== 1. Jalankan migrasi baru (kolom jenjang + seed 5 aspek perwira) ====
$mig = require __DIR__.'/database/migrations/2026_11_09_000000_add_jenjang_to_aspek_kepribadian.php';
$mig->up();
echo "1) Kolom jenjang ada? " . (Schema::connection('testing')->hasColumn('aspek_kepribadian', 'jenjang') ? "YA ✓" : "TIDAK ✗") . PHP_EOL;

// Seed 10 aspek universal dulu (simulasi DB eksisting) — jalankan SEBELUM cek idempotensi
DB::connection('testing')->table('aspek_kepribadian')->where('jenjang', 'perwira')->delete();
\App\Models\AspekKepribadian::ensureSeeded(); // tabel tidak kosong? tidak — sudah terisi 5 perwira oleh migrasi
$jmlPerwira = DB::connection('testing')->table('aspek_kepribadian')->where('jenjang', 'perwira')->count();
echo "2) Aspek khusus perwira ter-seed: {$jmlPerwira} (harus 5) " . ($jmlPerwira === 5 ? '✓' : '✗') . PHP_EOL;

// Idempotensi: jalankan up() lagi — tidak boleh menduplikasi
$mig->up();
$jmlPerwira2 = DB::connection('testing')->table('aspek_kepribadian')->where('jenjang', 'perwira')->count();
$total2 = DB::connection('testing')->table('aspek_kepribadian')->count();
echo "3) Idempoten (up() 2x): perwira={$jmlPerwira2}, total={$total2} (harus 15) " . ($total2 === 15 && $jmlPerwira2 === 5 ? '✓' : '✗') . PHP_EOL;

// ==== 4. Filter untukAngkatan: perwira=15, bintara/tamtama/PNS=10 ====
$mkAngkatan = fn($jenjang) => (object)['skadik' => (object)['jenjang' => $jenjang]];
$perwira  = \App\Models\AspekKepribadian::untukAngkatan($mkAngkatan('perwira'))->get();
$bintara  = \App\Models\AspekKepribadian::untukAngkatan($mkAngkatan('bintara'))->get();
$tamtama  = \App\Models\AspekKepribadian::untukAngkatan($mkAngkatan('tamtama'))->get();
$pns      = \App\Models\AspekKepribadian::untukAngkatan($mkAngkatan('pns'))->get();
$nullJenj = \App\Models\AspekKepribadian::untukAngkatan(null)->get();

echo "4) Perwira  : {$perwira->count()} aspek " . ($perwira->count() === 15 ? '✓' : '✗ HARUS 15') . PHP_EOL;
echo "5) Bintara  : {$bintara->count()} aspek " . ($bintara->count() === 10 ? '✓' : '✗ HARUS 10') . PHP_EOL;
echo "6) Tamtama  : {$tamtama->count()} aspek " . ($tamtama->count() === 10 ? '✓' : '✗ HARUS 10') . PHP_EOL;
echo "7) PNS      : {$pns->count()} aspek " . ($pns->count() === 10 ? '✓' : '✗ HARUS 10') . PHP_EOL;
echo "8) Tanpa jenjang skadik (legacy): {$nullJenj->count()} aspek " . ($nullJenj->count() === 10 ? '✓' : '✗ HARUS 10') . PHP_EOL;

// ==== 5. Urutan nomor & daftar aspek perwira 11–15 ====
echo "9) Urutan aspek perwira: " . $perwira->pluck('nomor')->implode(',') . " (harus 1..15) " .
     ($perwira->pluck('nomor')->toArray() === range(1, 15) ? '✓' : '✗') . PHP_EOL;
echo "    Aspek 11–15 (khusus perwira):\n";
foreach ($perwira->skip(10) as $a) {
    echo "      {$a->nomor}. {$a->nama}\n";
}

// ==== 6. ensureSeeded pada tabel kosong → harus 15 (10 universal + 5 perwira) ====
DB::connection('testing')->table('aspek_kepribadian')->truncate();
\App\Models\AspekKepribadian::ensureSeeded();
$totalFresh = DB::connection('testing')->table('aspek_kepribadian')->count();
$freshPerwira = DB::connection('testing')->table('aspek_kepribadian')->where('jenjang', 'perwira')->count();
echo "10) ensureSeeded DB kosong: total={$totalFresh}, khusus perwira={$freshPerwira} (harus 15/5) " .
     ($totalFresh === 15 && $freshPerwira === 5 ? '✓' : '✗') . PHP_EOL;

// ==== 7. Aspek nonaktif tidak ikut ====
DB::connection('testing')->table('aspek_kepribadian')->where('nomor', 11)->update(['aktif' => false]);
$perwiraOff = \App\Models\AspekKepribadian::untukAngkatan($mkAngkatan('perwira'))->get();
$bintaraOff = \App\Models\AspekKepribadian::untukAngkatan($mkAngkatan('bintara'))->get();
echo "11) Aspek 11 dinonaktifkan → perwira={$perwiraOff->count()} (14), bintara={$bintaraOff->count()} (10) " .
     ($perwiraOff->count() === 14 && $bintaraOff->count() === 10 ? '✓' : '✗') . PHP_EOL;

// ==== 8. down() bersih ====
DB::connection('testing')->table('aspek_kepribadian')->where('nomor', 11)->update(['aktif' => true]);
$mig->down();
$totalDown = DB::connection('testing')->table('aspek_kepribadian')->count();
echo "12) down(): sisa aspek={$totalDown} (harus 10, kolom jenjang hilang) " .
     ($totalDown === 10 && !Schema::connection('testing')->hasColumn('aspek_kepribadian', 'jenjang') ? '✓' : '✗') . PHP_EOL;

echo "\nSELESAI.\n";
