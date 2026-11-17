<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email berisi kode verifikasi 6 digit (pengganti link verifikasi bawaan Laravel). */
class VerifyEmailCode extends Notification
{
    public function __construct(private string $code) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = User::EMAIL_CODE_TTL_MINUTES;

        return (new MailMessage)
            ->subject('Kode verifikasi email kamu')
            ->greeting('Halo, '.$notifiable->name.'!')
            ->line('Masukkan kode berikut di halaman verifikasi untuk mengaktifkan akunmu:')
            ->line('# '.$this->code)
            ->line("Kode berlaku {$minutes} menit dan hangus setelah ".User::EMAIL_CODE_MAX_ATTEMPTS.' kali salah ketik.')
            ->line('Jangan bagikan kode ini ke siapa pun. Kalau kamu tidak merasa mendaftar, abaikan saja email ini.')
            ->salutation('Salam, '.config('app.name'));
    }
}
