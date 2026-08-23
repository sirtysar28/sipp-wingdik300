<?php

namespace App\Http\Controllers;

use App\Models\SmtpSetting;
use App\Services\SmtpConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Pengaturan SMTP — khusus Super Admin.
 * Email aktif yang diisi di sini dipakai untuk mengirim email ke user,
 * termasuk link reset password (lupa password).
 */
class SmtpSettingController extends Controller
{
    /** Pengaturan SMTP HANYA untuk Super Admin (guard eksplisit —
     *  RoleMiddleware masih memberi akses penuh ke role 'admin' lama). */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()?->isSuperAdmin()) {
                abort(403, 'Akses tidak diizinkan. Pengaturan SMTP hanya untuk Super Admin.');
            }
            return $next($request);
        });
    }

    /** Form pengaturan SMTP. */
    public function index()
    {
        $setting = SmtpSetting::latest('id')->first();

        // Jangan kirim password asli ke view — cukup penanda sudah terisi.
        $passwordSet = $setting && $setting->password !== null && $setting->password !== '';

        return view('smtp-settings.index', compact('setting', 'passwordSet'));
    }

    /** Simpan / perbarui konfigurasi SMTP. */
    public function save(Request $request)
    {
        $setting = SmtpSetting::latest('id')->first();

        $rules = [
            'host'        => 'required|string|max:255',
            'port'        => 'required|integer|between:1,65535',
            'encryption'  => 'required|in:tls,ssl,none',
            'username'    => 'required|email|max:255',
            'from_address'=> 'required|email|max:255',
            'from_name'   => 'required|string|max:255',
            // mailer & is_active TIDAK divalidasi:
            // - mailer selalu 'smtp' (satu-satunya opsi) — di-hardcode di bawah.
            // - is_active: checkbox HTML mengirim "on" saat dicentang (tidak
            //   lolos rule boolean). Nilainya dibaca via $request->has().
        ];

        // Password wajib saat pertama kali; boleh dikosongkan saat edit
        // (pertahankan password lama).
        if (!$setting || $request->filled('password')) {
            $rules['password'] = 'required|string|min:4';
        }

        $validated = $request->validate($rules);
        $validated['mailer'] = 'smtp';

        $data = [
            'mailer'       => $validated['mailer'],
            'host'         => trim($validated['host']),
            'port'         => (int) $validated['port'],
            'encryption'   => $validated['encryption'] === 'none' ? null : $validated['encryption'],
            'username'     => $validated['username'],
            'from_address' => $validated['from_address'],
            'from_name'    => $validated['from_name'],
            'is_active'    => $request->has('is_active'),
        ];

        if ($request->filled('password')) {
            $data['password'] = $request->input('password');
        }

        if ($setting) {
            $setting->update($data);
        } else {
            SmtpSetting::create($data);
        }

        return redirect()->route('smtp-settings.index')
            ->with('success', 'Pengaturan SMTP berhasil disimpan.');
    }

    /** Kirim email percobaan untuk memverifikasi konfigurasi SMTP. */
    public function test(Request $request)
    {
        $request->validate(['test_email' => 'required|email']);

        if (!SmtpConfigService::isConfigured()) {
            return back()->withErrors(['Konfigurasi SMTP belum lengkap. Isi host, email, dan pengirim lalu simpan.']);
        }

        SmtpConfigService::apply();

        try {
            Mail::raw(
                "Ini adalah email percobaan dari SIPP.\n\nJika Anda menerima email ini, konfigurasi SMTP sudah benar dan siap dipakai untuk mengirim link reset password.",
                function ($message) use ($request) {
                    $message->to($request->test_email)
                            ->subject('Test Email — Pengaturan SMTP SIPP');
                }
            );

            return back()->with('success', 'Email percobaan berhasil dikirim ke ' . $request->test_email . '. Cek kotak masuk (atau folder spam).');
        } catch (\Throwable $e) {
            return back()->withErrors(['Gagal mengirim email: ' . $e->getMessage()]);
        }
    }
}
