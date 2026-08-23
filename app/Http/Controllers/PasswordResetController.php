<?php

namespace App\Http\Controllers;

use App\Services\SmtpConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

/**
 * Flow Lupa Password:
 *  1. GET  /forgot-password          → form input email
 *  2. POST /forgot-password          → kirim link reset ke email terdaftar
 *  3. GET  /reset-password/{token}   → form password baru (via link email)
 *  4. POST /reset-password           → simpan password baru
 */
class PasswordResetController extends Controller
{
    /** Tampilkan form "Lupa Password" (input email). */
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    /** Kirim link reset password ke email user (jika terdaftar). */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // SMTP harus sudah dikonfigurasi Super Admin sebelum bisa kirim email.
        if (!SmtpConfigService::isConfigured()) {
            return back()->withErrors([
                'email' => 'Fitur reset password belum aktif. Silakan hubungi Super Admin untuk mengonfigurasi pengaturan SMTP.',
            ])->withInput($request->only('email'));
        }

        // Terapkan konfigurasi SMTP dari database ke runtime.
        SmtpConfigService::apply();

        $status = Password::broker()->sendResetLink(
            $request->only('email')
        );

        // Pesan generik (tidak membocorkan apakah email terdaftar atau tidak).
        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', 'Link reset password telah dikirim ke email Anda. Silakan cek kotak masuk (atau folder spam).')
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }

    /** Tampilkan form password baru (dari link di email). */
    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', old('email')),
        ]);
    }

    /** Simpan password baru. */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect('/login')->with('status', __($status))->with('reset_success', true)
            : back()->withErrors(['email' => __($status)])->withInput($request->only('email'));
    }
}
