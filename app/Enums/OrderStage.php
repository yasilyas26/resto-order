<?php

namespace App\Enums;

/**
 * Tahap order seperti yang dilihat pengunjung. Gabungan dari payment_status dan kitchen_status,
 * dihitung saat ditampilkan (bukan disimpan), jadi kedua status aslinya tetap terpisah.
 */
enum OrderStage: string
{
    case Waiting = 'waiting';
    case Paid = 'paid';
    case Cooking = 'cooking';
    case Done = 'done';
    case Expired = 'expired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Menunggu Pembayaran',
            self::Paid => 'Dibayar',
            self::Cooking => 'Sedang Dibuat',
            self::Done => 'Selesai',
            self::Expired => 'Dibatalkan',
            self::Failed => 'Pembayaran Gagal',
        };
    }

    /** Posisi di garis waktu 4 langkah. 0 = di luar alur normal (kedaluwarsa/gagal). */
    public function step(): int
    {
        return match ($this) {
            self::Waiting => 1,
            self::Paid => 2,
            self::Cooking => 3,
            self::Done => 4,
            self::Expired, self::Failed => 0,
        };
    }

    /** Order yang masih berjalan (belum selesai dan belum batal). */
    public function isActive(): bool
    {
        return in_array($this, [self::Waiting, self::Paid, self::Cooking], true);
    }

    public function message(): string
    {
        return match ($this) {
            self::Waiting => 'Selesaikan pembayaran supaya pesananmu masuk ke dapur.',
            self::Paid => 'Pembayaran diterima. Pesananmu sudah masuk antrean dapur.',
            self::Cooking => 'Pesananmu sedang dimasak di dapur. Pelayan akan mengantarkannya ke mejamu.',
            self::Done => 'Pesananmu sudah selesai. Selamat menikmati!',
            self::Expired => 'Batas waktu pembayaran sudah lewat, jadi pesanan ini dibatalkan. Silakan pesan ulang.',
            self::Failed => 'Pembayaran tidak berhasil. Kamu bisa mencoba bayar lagi.',
        };
    }
}
