<?php

namespace App\Notifications;

use App\Services\SmtpConfigService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ResetPasswordNotification extends Notification
{
    public function __construct(public string $token)
    {
    }

    public function via($notifiable): array
    {
        // Pastikan SMTP dari Pengaturan (DB) dipakai saat email antre di-render.
        SmtpConfigService::apply();

        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // URL frontend reset password — sesuai route web.php
        $url = URL::to('/reset-password/' . $this->token . '?email=' . urlencode($notifiable->getEmailForPasswordReset()));

        $expire = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset Password — SIPP')
            ->greeting('Halo, ' . $notifiable->name . '!')
            ->line('Kami menerima permintaan reset password untuk akun SIPP Anda (' . $notifiable->getEmailForPasswordReset() . ').')
            ->line('Klik tombol di bawah ini untuk membuat password baru:')
            ->action('Reset Password', $url)
            ->line('Link ini hanya berlaku **' . $expire . ' menit** dan hanya bisa dipakai satu kali.')
            ->line('Jika Anda tidak meminta reset password, abaikan email ini — password Anda tidak akan berubah.')
            ->salutation('Terima kasih, Tim SIPP');
    }
}
