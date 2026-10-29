<?php
/**
 * SMOKE TEST lokal (tidak menyentuh DB produksi):
 * - Boot Laravel dengan sqlite :memory:
 * - Jalankan migration drop unique global NRP & NOSIS
 * - Uji ATURAN FINAL (revisi 29 Okt 2026, se-Wingdik 300):
 *
 *   ┌──────────────────────────────────────┬─────────┬─────────┐
 *   │ KONDISI                              │  NRP    │  NOSIS  │
 *   ├──────────────────────────────────────┼─────────┼─────────┤
 *   │ 1 angkatan yang sama                 │ ❌ dobel│ ❌ dobel│
 *   │ Sekolah sama, angkatan beda          │ ✅ boleh│ ✅ boleh│
 *   │ (TIDAK LULUS → mengulang, NRP sama)  │         │ (syarat)│
 *   │ Nosis sama + NRP BEDA (sek. sama)    │    —    │ ❌ tolak│
 *   │ Sekolah lain (Wingdik 300)           │ ✅ boleh│ ❌ dobel│
 *   └──────────────────────────────────────┴─────────┴─────────┘
 *
 * Jalankan:  php smoke-test-nrp-scope.php
 */
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Models\{Lemdik, Skadik, Angkatan, PesertaDidik};

// Bangun skema awal (persis hasil migration lama di MySQL produksi):
// nrp & nosis sama-sama unique GLOBAL bernama — lalu jalankan DUA migration
// baru yang melepas unique global keduanya (kasus mengulang butuh ruang).
Schema::dropIfExists('peserta_didik');
Schema::dropIfExists('angkatan');
Schema::dropIfExists('skadik');
Schema::dropIfExists('lemdik');
Schema::create('lemdik', fn(Blueprint $t) => $t->id());
Schema::create('skadik', fn(Blueprint $t) => $t->id());
Schema::create('angkatan', fn(Blueprint $t) => $t->id());
foreach (['lemdik' => ['nama', 'singkat'], 'skadik' => ['lemdik_id', 'nama', 'jenjang', 'jenis_pendidikan'], 'angkatan' => ['skadik_id', 'nomor_angkatan', 'tahun_masuk', 'aktif']] as $tbl => $cols) {
    foreach ($cols as $c) {
        DB::statement("ALTER TABLE {$tbl} ADD COLUMN {$c} VARCHAR(100)");
    }
    DB::statement("ALTER TABLE {$tbl} ADD COLUMN created_at TEXT");
    DB::statement("ALTER TABLE {$tbl} ADD COLUMN updated_at TEXT");
}
DB::statement('CREATE TABLE peserta_didik (id INTEGER PRIMARY KEY AUTOINCREMENT, angkatan_id INTEGER, nama VARCHAR(100), nrp VARCHAR(50), pangkat VARCHAR(50), nosis VARCHAR(50), aktif INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT)');
DB::statement('CREATE UNIQUE INDEX peserta_didik_nrp_unique ON peserta_didik (nrp)');
DB::statement('CREATE UNIQUE INDEX peserta_didik_nosis_unique ON peserta_didik (nosis)');

// Catat migration lama sebagai sudah jalan, jalankan HANYA 2 migration baru
if (!Schema::hasTable('migrations')) {
    Schema::create('migrations', function (Blueprint $t) {
        $t->increments('id');
        $t->string('migration');
        $t->integer('batch');
    });
}
$newMigrations = [
    '2026_10_29_000000_drop_global_unique_on_peserta_nrp',
    '2026_10_29_010000_drop_global_unique_on_peserta_nosis',
];
foreach (glob(__DIR__ . '/database/migrations/*.php') as $file) {
    $name = basename($file, '.php');
    if (in_array($name, $newMigrations, true)) {
        continue; // migration baru → biarkan dieksekusi
    }
    DB::table('migrations')->insert(['migration' => $name, 'batch' => 1]);
}
Artisan::call('migrate', ['--force' => true]);
echo "Migrate OK: " . trim(Artisan::output()) . "\n";

// ── Setup data ──────────────────────────────────────────────
$lemdik  = Lemdik::create(['nama' => 'Skadron 300', 'singkat' => 'Skadik 300']);
$skadikA = Skadik::create(['lemdik_id' => $lemdik->id, 'nama' => 'Sekolah A', 'jenjang' => 'bintara', 'jenis_pendidikan' => 'dikbangspes']);
$skadikB = Skadik::create(['lemdik_id' => $lemdik->id, 'nama' => 'Sekolah B', 'jenjang' => 'bintara', 'jenis_pendidikan' => 'dikbangspes']);
$angkA1  = Angkatan::create(['skadik_id' => $skadikA->id, 'nomor_angkatan' => 1, 'tahun_masuk' => 2026, 'aktif' => true]);
$angkA2  = Angkatan::create(['skadik_id' => $skadikA->id, 'nomor_angkatan' => 2, 'tahun_masuk' => 2026, 'aktif' => true]);
$angkB1  = Angkatan::create(['skadik_id' => $skadikB->id, 'nomor_angkatan' => 1, 'tahun_masuk' => 2026, 'aktif' => true]);

// Andi tidak lulus di Sekolah A Angkatan 1 → NRP 123, Nosis 001
PesertaDidik::create(['angkatan_id' => $angkA1->id, 'nama' => 'Andi', 'nrp' => '123', 'pangkat' => 'Serda', 'nosis' => '001', 'aktif' => true]);

$pass = 0; $fail = 0;
function check(string $label, bool $cond): void {
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ✔ $label\n"; }
    else       { $fail++; echo "  ✘ GAGAL: $label\n"; }
}

echo "\n[1] MENGULANG: nosis & NRP sama, sekolah sama, angkatan beda → BOLEH\n";
check('nosisConflict(001, A2, NRP 123) = null (boleh, kasus mengulang)', PesertaDidik::nosisConflict('001', $angkA2->id, '123') === null);
check('nrpUsedInAngkatan(123, A2) = null (boleh)', PesertaDidik::nrpUsedInAngkatan('123', $angkA2->id) === null);
try {
    $ulang = PesertaDidik::create(['angkatan_id' => $angkA2->id, 'nama' => 'Andi', 'nrp' => '123', 'pangkat' => 'Serda', 'nosis' => '001', 'aktif' => true]);
    check('Record mengulang tersimpan di DB (2 record, nosis sama)', $ulang->exists() && PesertaDidik::where('nosis', '001')->count() === 2);
} catch (\Throwable $e) {
    check('Record mengulang tersimpan di DB — ' . $e->getMessage(), false);
}

echo "\n[2] NRP dobel dalam SATU ANGKATAN → TOLAK\n";
check('nrpUsedInAngkatan(123, A1) menemukan duplikat (Andi)', PesertaDidik::nrpUsedInAngkatan('123', $angkA1->id)?->nama === 'Andi');
check('nrpUsedInAngkatan(123, A2) menemukan duplikat (record mengulang)', PesertaDidik::nrpUsedInAngkatan('123', $angkA2->id) !== null);

echo "\n[3] Nosis dobel dalam SATU ANGKATAN → TOLAK\n";
check('nosisConflict(001, A1, NRP lain) = error', PesertaDidik::nosisConflict('001', $angkA1->id, '777') !== null);
check('nosisConflict(001, A2, NRP lain) = error', PesertaDidik::nosisConflict('001', $angkA2->id, '777') !== null);

echo "\n[4] Nosis sama SEKOLAH sama angkatan beda tapi NRP BEDA → TOLAK (bukan kasus mengulang)\n";
$err = PesertaDidik::nosisConflict('001', $angkA2->id, '999');
check('nosisConflict(001, A2, NRP 999) = error', $err !== null);
check('pesan error menyebut hanya boleh mengulang dengan NRP sama', $err !== null && str_contains($err, 'MENGULANG'));

echo "\n[5] Nosis sama di SEKOLAH LAIN → TOLAK (unik se-Wingdik 300)\n";
$err = PesertaDidik::nosisConflict('001', $angkB1->id, '123');
check('nosisConflict(001, B1) = error', $err !== null);
check('pesan error menyebut sekolah lain', $err !== null && str_contains($err, 'Sekolah A'));

echo "\n[6] NRP sama di SEKOLAH LAIN → BOLEH\n";
check('nrpUsedInAngkatan(123, B1) = null (boleh)', PesertaDidik::nrpUsedInAngkatan('123', $angkB1->id) === null);
try {
    PesertaDidik::create(['angkatan_id' => $angkB1->id, 'nama' => 'Andi di Sekolah B', 'nrp' => '123', 'pangkat' => 'Serda', 'nosis' => '555', 'aktif' => true]);
    check('NRP "123" tersimpan juga di Sekolah B (nosis beda)', PesertaDidik::where('nrp', '123')->count() === 3);
} catch (\Throwable $e) {
    check('NRP "123" tersimpan juga di Sekolah B — ' . $e->getMessage(), false);
}

echo "\n[7] Pencarian NRP ter-scope per sekolah (fallback import nilai)\n";
check('findByNrpInSkadik(A) → peserta Sekolah A', PesertaDidik::findByNrpInSkadik('123', $skadikA->id)?->angkatan?->skadik_id == $skadikA->id);
check('findByNrpInSkadik(B) → peserta Sekolah B', PesertaDidik::findByNrpInSkadik('123', $skadikB->id)?->angkatan?->skadik_id == $skadikB->id);

echo "\n[8] Status index di database\n";
$idx = collect(DB::select("PRAGMA index_list('peserta_didik')"));
$uniq = $idx->filter(fn($r) => (int) $r->unique === 1)->pluck('name');
check('Index unique GLOBAL nrp sudah DIHAPUS', $uniq->contains(fn($n) => str_contains($n, 'nrp')) === false);
check('Index unique GLOBAL nosis sudah DIHAPUS (kasus mengulang butuh ini)', $uniq->contains(fn($n) => str_contains($n, 'nosis')) === false);

echo "\n══════════════════════════════\nHASIL: {$pass} lulus, {$fail} gagal\n";
exit($fail > 0 ? 1 : 0);
