<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\{User, Lemdik, Skadik, Angkatan, PesertaDidik, PeriodeNilai};
use Carbon\Carbon;

class DatabaseSeeder extends Seeder {
    public function run(): void {
        // ══════════════════════════════════════════════════════════════════
        //  SEEDER INI AMAN DIJALANKAN ULANG DI PRODUCTION (IDEMPOTENT)
        //  • Semua entitas pakai firstOrCreate() → TIDAK menimpa / menghapus
        //    data eksisting. Password, role, skadik, angkatan tetap utuh.
        //  • Rename label akun default HANYA dilakukan bila `name` masih
        //    bernilai label LAMA (artinya belum dikustomisasi admin).
        //  • Data contoh (peserta & periode) hanya di-seed bila tabel
        //    masih kosong, supaya tidak mencemari data production.
        // ══════════════════════════════════════════════════════════════════

        // ─── DATA MASTER: Lembaga / Sekolah / Angkatan ──────────────────
        $lemdik = Lemdik::firstOrCreate(
            ['kode' => 'SKADIK302'],
            ['nama' => 'Skadron Pendidikan 302', 'alamat' => 'Lanud Adisutjipto, Yogyakarta', 'wingdik' => 'Wing Pendidikan 300/Teknik']
        );

        $skadik = Skadik::firstOrCreate(
            ['kode' => 'SKADIK302'],
            ['lemdik_id' => $lemdik->id, 'nama' => 'Skadik 302']
        );

        $angkatan = Angkatan::firstOrCreate(
            ['skadik_id' => $skadik->id, 'nomor_angkatan' => 'XXXII'],
            ['jurusan' => 'Pemeliharaan Sistem Fuel Pesawat Terbang A-22', 'tahun_masuk' => 2026, 'aktif' => true]
        );

        // ─── AKUN DEFAULT (demo) ────────────────────────────────────────
        //  firstOrCreate: hanya bikin bila belum ada. Akun eksisting tidak
        //  disentuh sama sekali (password, role, relasi tetap aman).
        User::firstOrCreate(
            ['email' => 'superadmin@simonik.id'],
            ['name' => 'Super Administrator', 'password' => Hash::make('superadmin123'), 'role' => 'super_admin']
        );
        User::firstOrCreate(
            ['email' => 'admin.akademik@simonik.id'],
            ['name' => 'Kepala Sekolah', 'password' => Hash::make('akademik123'), 'role' => 'admin_akademik', 'angkatan_id' => $angkatan->id]
        );
        User::firstOrCreate(
            ['email' => 'admin.kepribadian@simonik.id'],
            ['name' => 'Danflight', 'password' => Hash::make('kepribadian123'), 'role' => 'admin_kepribadian', 'angkatan_id' => $angkatan->id]
        );
        User::firstOrCreate(
            ['email' => 'admin.samapta@simonik.id'],
            ['name' => 'Binjaswing', 'password' => Hash::make('samapta123'), 'role' => 'admin_samapta', 'angkatan_id' => $angkatan->id]
        );
        User::firstOrCreate(
            ['email' => 'admin@simonik.id'],
            ['name' => 'Administrator', 'password' => Hash::make('admin123'), 'role' => 'admin']
        );
        User::firstOrCreate(
            ['email' => 'instruktur@simonik.id'],
            ['name' => 'Instruktur Utama', 'password' => Hash::make('instruktur123'), 'role' => 'instruktur', 'angkatan_id' => $angkatan->id]
        );

        // ─── REFRESH LABEL AKUN DEFAULT KE WORDING BARU ─────────────────
        //  Update `name` HANYA bila nilainya masih = label LAMA, supaya
        //  akun yang sudah dikustomisasi admin (misal diganti nama orang
        //  sungguhan) TIDAK ikut ditimpa. Non-destructive: password, role,
        //  angkatan_id, skadik_id sama sekali tidak disentuh.
        //
        //  Pemetaan: Admin Akademik → Kepala Sekolah, dst. (konsisten dgn
        //  label dropdown di Manage User).
        $renameMap = [
            'admin.akademik@simonik.id'    => ['Admin Akademik'    => 'Kepala Sekolah'],
            'admin.kepribadian@simonik.id' => ['Admin Kepribadian' => 'Danflight'],
            'admin.samapta@simonik.id'     => ['Admin Samapta'     => 'Binjaswing'],
        ];
        foreach ($renameMap as $email => $labels) {
            foreach ($labels as $old => $new) {
                User::where('email', $email)
                    ->where('name', $old)   // guard: hanya yang belum dikustomisasi
                    ->update(['name' => $new]);
            }
        }

        // ─── DATA CONTOH: Peserta & Periode ─────────────────────────────
        //  Hanya di-seed bila tabel masih kosong, supaya tidak mencemari
        //  data peserta/periode production yang sudah ada.
        if (PesertaDidik::count() === 0) {
            $pesertaData = [
                ['nama'=>'Letda Rizky Aditya',   'nrp'=>'3100001','pangkat'=>'Letda','nosis'=>'NS-001'],
                ['nama'=>'Letda Hendra Saputra',  'nrp'=>'3100002','pangkat'=>'Letda','nosis'=>'NS-002'],
                ['nama'=>'Letda Sari Wulandari',  'nrp'=>'3100003','pangkat'=>'Letda','nosis'=>'NS-003'],
                ['nama'=>'Letda Bagas Prasetyo',  'nrp'=>'3100004','pangkat'=>'Letda','nosis'=>'NS-004'],
                ['nama'=>'Letda Dian Kusuma',     'nrp'=>'3100005','pangkat'=>'Letda','nosis'=>'NS-005'],
                ['nama'=>'Letda Fajar Abiyoso',   'nrp'=>'3100006','pangkat'=>'Letda','nosis'=>'NS-006'],
                ['nama'=>'Letda Nadia Rahmawati', 'nrp'=>'3100007','pangkat'=>'Letda','nosis'=>'NS-007'],
                ['nama'=>'Letda Yoga Pratama',    'nrp'=>'3100008','pangkat'=>'Letda','nosis'=>'NS-008'],
                ['nama'=>'Letda Anisa Fitri',     'nrp'=>'3100009','pangkat'=>'Letda','nosis'=>'NS-009'],
                ['nama'=>'Letda Budi Santoso',    'nrp'=>'3100010','pangkat'=>'Letda','nosis'=>'NS-010'],
            ];

            foreach ($pesertaData as $d) {
                PesertaDidik::firstOrCreate(
                    ['nrp' => $d['nrp']],
                    array_merge($d, ['angkatan_id' => $angkatan->id])
                );
            }
        }

        if (PeriodeNilai::count() === 0) {
            // 6 periode nilai (tiap 2 minggu)
            $startDate = Carbon::create(2026, 1, 8);
            for ($p = 1; $p <= 6; $p++) {
                $endDate = $startDate->copy()->addDays(13);
                PeriodeNilai::firstOrCreate(
                    ['angkatan_id' => $angkatan->id, 'label' => "Periode $p"],
                    ['tanggal_mulai' => $startDate->toDateString(), 'tanggal_selesai' => $endDate->toDateString(), 'aktif' => $p === 6]
                );
                $startDate = $endDate->addDay();
            }
        }
    }
}
