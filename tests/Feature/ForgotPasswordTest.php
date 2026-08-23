<?php

namespace Tests\Feature;

use App\Models\SmtpSetting;
use App\Models\User;
use App\Services\SmtpConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Smoke-test fitur Lupa Password + Pengaturan SMTP.
 * Run: php artisan test --filter=ForgotPasswordTest
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'testuser@sipp.id',
            'password' => Hash::make('password-lama'),
            'role'     => 'admin',
        ]);
    }

    public function test_halaman_forgot_password_bisa_diakses(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('Kirim Link Reset Password');
    }

    public function test_email_tidak_valid_ditolak(): void
    {
        $this->post('/forgot-password', ['email' => 'bukan-email'])
            ->assertSessionHasErrors('email');
    }

    public function test_gagal_jika_smtp_belum_dikonfigurasi(): void
    {
        $this->post('/forgot-password', ['email' => $this->user->email])
            ->assertSessionHasErrors();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
        ]);
    }

    public function test_link_reset_terkirim_dan_password_berubah(): void
    {
        SmtpSetting::create([
            'host'        => 'smtp.test.id',
            'port'        => 587,
            'encryption'  => 'tls',
            'username'    => 'pengirim@sipp.id',
            'password'    => 'rahasia',
            'from_address'=> 'pengirim@sipp.id',
            'from_name'   => 'SIPP',
            'is_active'   => true,
        ]);

        $this->assertTrue(SmtpConfigService::isConfigured());

        // 1) Minta link reset
        $this->post('/forgot-password', ['email' => $this->user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($this->user, \App\Notifications\ResetPasswordNotification::class, 1);
        // 2) Ambil token mentah (DB menyimpan hash-nya, bukan token asli)
        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', $this->user->email)
            ->first();
        $this->assertNotNull($tokenRow, 'Token reset harus tersimpan');
        $rawToken = \Illuminate\Support\Facades\Password::broker()->createToken($this->user);

        // 3) Reset password via token
        $this->post('/reset-password', [
            'token'                 => $rawToken,
            'email'                 => $this->user->email,
            'password'              => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertRedirect('/login');

        $this->user->refresh();
        $this->assertTrue(Hash::check('password-baru-123', $this->user->password));
    }

    public function test_token_salah_ditolak(): void
    {
        SmtpSetting::create([
            'host'        => 'smtp.test.id',
            'port'        => 587,
            'encryption'  => 'tls',
            'username'    => 'pengirim@sipp.id',
            'password'    => 'rahasia',
            'from_address'=> 'pengirim@sipp.id',
            'from_name'   => 'SIPP',
            'is_active'   => true,
        ]);

        $this->post('/reset-password', [
            'token'                 => 'token-ngawur',
            'email'                 => $this->user->email,
            'password'              => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertSessionHasErrors();

        $this->user->refresh();
        $this->assertTrue(Hash::check('password-lama', $this->user->password));
    }

    public function test_pengaturan_smtp_hanya_super_admin(): void
    {
        // Guest → redirect login
        $this->get('/pengaturan-smtp')->assertRedirect('/login');

        // Non super admin → 403
        $this->actingAs($this->user)->get('/pengaturan-smtp')->assertForbidden();

        // Super admin → 200
        $super = User::create([
            'name'     => 'Super',
            'email'    => 'super@sipp.id',
            'password' => Hash::make('x'),
            'role'     => 'super_admin',
        ]);
        $this->actingAs($super)
            ->get('/pengaturan-smtp')
            ->assertOk()
            ->assertSee('Pengaturan SMTP');
    }

    public function test_super_admin_bisa_simpan_smtp(): void
    {
        $super = User::create([
            'name'     => 'Super',
            'email'    => 'super@sipp.id',
            'password' => Hash::make('x'),
            'role'     => 'super_admin',
        ]);

        $this->actingAs($super)->post('/pengaturan-smtp', [
            'mailer'       => 'smtp',
            'host'         => 'smtp.gmail.com',
            'port'         => 587,
            'encryption'   => 'tls',
            'username'     => 'email.aktif@gmail.com',
            'password'     => 'app-password',
            'from_address' => 'email.aktif@gmail.com',
            'from_name'    => 'SIPP',
            'is_active'    => '1',
        ])->assertRedirect(route('smtp-settings.index'));

        $this->assertDatabaseHas('smtp_settings', [
            'host'     => 'smtp.gmail.com',
            'username' => 'email.aktif@gmail.com',
        ]);

        // Konfigurasi diterapkan ke runtime mail
        SmtpConfigService::apply();
        $this->assertEquals('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertEquals('email.aktif@gmail.com', config('mail.from.address'));
    }

    /** Replikasi persis payload browser: tanpa field mailer (hidden input
     *  terkadang hilang) & checkbox is_active mengirim "on" saat dicentang. */
    public function test_simpan_smtp_payload_persis_form_html(): void
    {
        $super = User::create([
            'name'     => 'Super',
            'email'    => 'super@sipp.id',
            'password' => Hash::make('x'),
            'role'     => 'super_admin',
        ]);

        // is_active = "on" (nilai asli checkbox HTML saat dicentang)
        $this->actingAs($super)->post('/pengaturan-smtp', [
            'host'         => 'smtp.gmail.com',
            'port'         => 587,
            'encryption'   => 'tls',
            'username'     => 'email.aktif@gmail.com',
            'password'     => 'app-password',
            'from_address' => 'email.aktif@gmail.com',
            'from_name'    => 'SIPP',
            'is_active'    => 'on',
        ])->assertRedirect(route('smtp-settings.index'));

        $this->assertDatabaseHas('smtp_settings', [
            'host'      => 'smtp.gmail.com',
            'is_active' => true,
        ]);

        // Tanpa checkbox (uncheck) → is_active = false
        $this->post('/pengaturan-smtp', [
            'host'         => 'smtp.gmail.com',
            'port'         => 587,
            'encryption'   => 'tls',
            'username'     => 'email.aktif@gmail.com',
            'password'     => 'app-password',
            'from_address' => 'email.aktif@gmail.com',
            'from_name'    => 'SIPP',
        ])->assertRedirect(route('smtp-settings.index'));

        $this->assertDatabaseHas('smtp_settings', [
            'host'      => 'smtp.gmail.com',
            'is_active' => false,
        ]);
    }
}
