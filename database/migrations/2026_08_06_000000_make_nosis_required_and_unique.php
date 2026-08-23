<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuat kolom `nosis` pada tabel `peserta_didik` menjadi:
 *   1. NOT NULL  — nosis wajib diisi (sesuai revisi: "Nosis diwajibkan isi")
 *   2. UNIQUE    — tidak boleh ada nosis yang sama antar peserta (sesuai revisi:
 *                  "NPK/NRP dan Nosis jangan sampai ada yg sama. Kalo ada
 *                  kesamaan, dibuatkan pesan error bahwa yg baru diinput tidak
 *                  bisa dipakai (karena sudah ada yg punya)").
 *
 * Migration ini membersihkan data LAMA terlebih dahulu (NULL / kosong /
 * placeholder / duplikat) sebelum memberlakukan constraint, sehingga aman
 * dijalankan di environment lain (staging/production) yang mungkin masih
 * punya data nosis yang belum dibersihkan.
 */
return new class extends Migration
{
    /**
     * Kandidat nilai nosis yang dianggap "kosong" / belum diisi.
     */
    private function isEmptyPlaceholder(?string $value): bool
    {
        if ($value === null) {
            return true;
        }
        $v = trim($value);
        return $v === '' || in_array($v, ['-', '--', '—', 'n/a', 'N/A', 'null', 'NULL', 'tidak ada', '-'], true);
    }

    /**
     * Cari nosis yang belum dipakai peserta lain.
     */
    private function uniqueCandidate(string $base): string
    {
        $candidate = $base;
        $i = 2;
        while (DB::table('peserta_didik')->where('nosis', $candidate)->exists()) {
            $candidate = $base . '-' . $i++;
        }
        return $candidate;
    }

    public function up(): void
    {
        // ── 1. Isi nosis yang NULL / kosong / placeholder ──────────────
        //      Generate nilai unik berbasis ID peserta agar stabil & mudah
        //      ditelusuri admin untuk diperbaiki manual kemudian.
        $emptyRows = DB::table('peserta_didik')
            ->orderBy('id')
            ->get(['id', 'nosis', 'nrp']);

        foreach ($emptyRows as $row) {
            if (!$this->isEmptyPlaceholder($row->nosis)) {
                continue;
            }
            // Basis: nomor urut berbasis ID → NOSIS-0001
            $base = 'NOSIS-' . str_pad((string) $row->id, 4, '0', STR_PAD_LEFT);
            $candidate = $this->uniqueCandidate($base);
            DB::table('peserta_didik')->where('id', $row->id)->update(['nosis' => $candidate]);
        }

        // ── 2. Resolve duplikat nosis ──────────────────────────────────
        //      Pertahankan record paling awal (id terkecil); record sisanya
        //      diberi suffix unik (-2, -3, ...).
        $dupes = DB::table('peserta_didik')
            ->select('nosis', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('nosis')
            ->groupBy('nosis')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dupes as $dupe) {
            $rows = DB::table('peserta_didik')
                ->where('nosis', $dupe->nosis)
                ->orderBy('id')
                ->pluck('id');

            // Lewati id pertama (yang dipertahankan), rename sisanya
            foreach ($rows->skip(1) as $id) {
                $suffix = 2;
                $candidate = $dupe->nosis . '-' . $suffix;
                while (DB::table('peserta_didik')->where('nosis', $candidate)->exists()) {
                    $candidate = $dupe->nosis . '-' . (++$suffix);
                }
                DB::table('peserta_didik')->where('id', $id)->update(['nosis' => $candidate]);
            }
        }

        // ── 3. Normalisasi whitespace (trim) untuk semua nosis ─────────
        //      Mencegah duplikat "semu" karena spasi: " 001" vs "001 ".
        DB::statement("UPDATE peserta_didik SET nosis = TRIM(nosis) WHERE nosis IS NOT NULL");

        // Setelah trim bisa muncul duplikat baru (mis. "001 " & "001") → resolve lagi
        $dupesAfterTrim = DB::table('peserta_didik')
            ->select('nosis', DB::raw('COUNT(*) as cnt'))
            ->groupBy('nosis')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($dupesAfterTrim as $dupe) {
            $rows = DB::table('peserta_didik')
                ->where('nosis', $dupe->nosis)
                ->orderBy('id')
                ->pluck('id');
            foreach ($rows->skip(1) as $id) {
                $suffix = 2;
                $candidate = $dupe->nosis . '-' . $suffix;
                while (DB::table('peserta_didik')->where('nosis', $candidate)->exists()) {
                    $candidate = $dupe->nosis . '-' . (++$suffix);
                }
                DB::table('peserta_didik')->where('id', $id)->update(['nosis' => $candidate]);
            }
        }

        // ── 4. Ubah kolom menjadi NOT NULL ─────────────────────────────
        Schema::table('peserta_didik', function (Blueprint $table) {
            $table->string('nosis')->nullable(false)->default('')->change();
        });

        // ── 5. Tambah UNIQUE index ─────────────────────────────────────
        //      Gunakan nama index eksplisit agar konsisten & mudah di-rollback.
        Schema::table('peserta_didik', function (Blueprint $table) {
            $table->unique('nosis', 'peserta_didik_nosis_unique');
        });
    }

    public function down(): void
    {
        // Rollback: hapus unique index, kembalikan kolom jadi nullable.
        Schema::table('peserta_didik', function (Blueprint $table) {
            $table->dropUnique('peserta_didik_nosis_unique');
        });

        Schema::table('peserta_didik', function (Blueprint $table) {
            $table->string('nosis')->nullable()->default(null)->change();
        });
    }
};
