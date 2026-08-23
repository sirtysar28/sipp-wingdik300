<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Skadik;

class MataPelajaranDefaultSeeder extends Seeder
{
    public function run(): void
    {
        // Cari semua skadik yang ada di database
        $skadikList = Skadik::all();

        if ($skadikList->isEmpty()) {
            $this->command->warn('⚠️ Tidak ada data sekolah (skadik). Seeder dilewati.');
            return;
        }

        // Default mata pelajaran SBS C-130 Fuel System
        $defaultSubjects = [
            ['nama' => 'Pengetahuan VMT', 'kode' => '1.1', 'jp' => 7, 'bobot' => 4, 'urutan' => 1],
            ['nama' => 'Safety', 'kode' => '1.2', 'jp' => 8, 'bobot' => 6, 'urutan' => 2],
            ['nama' => 'Technical Publications and Component Maintenance Forms', 'kode' => '1.3', 'jp' => 7, 'bobot' => 6, 'urutan' => 3],
            ['nama' => 'Fuel System Publications', 'kode' => '1.4', 'jp' => 7, 'bobot' => 6, 'urutan' => 4],
            ['nama' => 'Tools and Equipments', 'kode' => '1.5', 'jp' => 6, 'bobot' => 6, 'urutan' => 5],
            ['nama' => 'Fuel Theory', 'kode' => '2.1', 'jp' => 8, 'bobot' => 6, 'urutan' => 6],
            ['nama' => 'Material and Hardware', 'kode' => '2.2', 'jp' => 7, 'bobot' => 6, 'urutan' => 7],
            ['nama' => 'Fuel Components', 'kode' => '2.3', 'jp' => 18, 'bobot' => 6, 'urutan' => 8],
            ['nama' => 'Fuel System Overview', 'kode' => '2.4', 'jp' => 14, 'bobot' => 6, 'urutan' => 9],
            ['nama' => 'Aircraft Refueling and Defueling', 'kode' => '3.1', 'jp' => 11, 'bobot' => 6, 'urutan' => 10],
            ['nama' => 'Fuel System Testing & Fuel Strainer Screen Replacement', 'kode' => '3.2', 'jp' => 10, 'bobot' => 6, 'urutan' => 11],
            ['nama' => 'Main Fuel System Description, Operation and Maintenance', 'kode' => '4.1', 'jp' => 45, 'bobot' => 6, 'urutan' => 12],
            ['nama' => 'Main Fuel Tank Repair', 'kode' => '4.2', 'jp' => 33, 'bobot' => 6, 'urutan' => 13],
            ['nama' => 'Auxiliary Fuel System Description, Operation, & Maintenance', 'kode' => '5.1', 'jp' => 11, 'bobot' => 6, 'urutan' => 14],
            ['nama' => 'Fuel Tank Vent System Description, Operation, & Maintenance', 'kode' => '5.2', 'jp' => 9, 'bobot' => 6, 'urutan' => 15],
            ['nama' => 'Aircraft Air Refuelling C-130', 'kode' => '6.1', 'jp' => 7, 'bobot' => 6, 'urutan' => 16],
            ['nama' => 'Ground Aircraft Air Refuelling C-130', 'kode' => '6.2', 'jp' => 7, 'bobot' => 6, 'urutan' => 17],
            ['nama' => 'Latihan Praktis', 'kode' => 'PR', 'jp' => 42, 'bobot' => 6, 'urutan' => 18],
        ];

        // Load ke SEMUA sekolah yang ada
        foreach ($skadikList as $skadik) {
            // Skip kalau skadik ini sudah punya mata pelajaran
            $existing = DB::table('mata_pelajaran')->where('skadik_id', $skadik->id)->count();
            if ($existing > 0) {
                $this->command->info("⏭️  Skipped: {$skadik->nama} (ID: {$skadik->id}) — sudah punya {$existing} mata pelajaran.");
                continue;
            }

            foreach ($defaultSubjects as $s) {
                DB::table('mata_pelajaran')->insert([
                    'skadik_id' => $skadik->id,
                    'nama'      => $s['nama'],
                    'kode'      => $s['kode'],
                    'jp'        => $s['jp'],
                    'bobot'     => $s['bobot'],
                    'urutan'    => $s['urutan'],
                    'aktif'     => true,
                ]);
            }

            $this->command->info("✅ Loaded 18 subjek ke sekolah: {$skadik->nama} (ID: {$skadik->id})");
        }

        $this->command->info("Seeder selesai.");
    }
}
