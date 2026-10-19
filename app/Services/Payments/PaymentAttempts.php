<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentMethods;

/**
 * Mengelola percobaan bayar sebuah order. Satu order bisa dicoba beberapa kali (gagal, kedaluwarsa, ganti metode),
 * dan tiap percobaan memakai ID Midtrans yang unik: ORD-XXXXXX-1, ORD-XXXXXX-2, dst.
 *
 * Ada dua jenis percobaan: kartu memakai Snap token (popup), semua metode lain dibuat langsung lewat
 * Core API sehingga nomor VA / QR-nya langsung tersimpan di payments.raw_payload.
 */
class PaymentAttempts
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * Percobaan bayar yang bisa dipakai sekarang: pakai ulang yang masih berjalan, atau buat yang baru.
     * $method (kunci PaymentMethods) menentukan metodenya; null = pakai percobaan yang ada apa adanya.
     * Percobaan lama dengan metode lain TIDAK dibuang di sini: lewat ChangePaymentMethod, yang memastikan
     * transaksi lama belum dibayar.
     *
     * @throws PaymentGatewayException
     */
    public function current(Order $order, ?string $method = null): Payment
    {
        $latest = $order->payments()->latest('id')->first();

        if ($latest && $latest->isOpen()) {
            // Masih berlaku: pakai ulang supaya tidak menumpuk transaksi (dan VA yang sudah dibuat tetap sama).
            if ($this->hasPaymentData($latest) && $order->expires_at?->isFuture() && ($method === null || $latest->method === $method)) {
                return $latest;
            }

            // Baris sudah ada tapi belum sempat dibuat di Midtrans (mis. Midtrans sedang error): coba lagi.
            if (! $this->hasPaymentData($latest)) {
                return $this->open($order, $latest);
            }
        }

        return $this->open($order, $this->newAttempt($order, $method));
    }

    private function hasPaymentData(Payment $payment): bool
    {
        return filled($payment->snap_token) || ($payment->transaction_status === 'pending' && is_array($payment->raw_payload));
    }

    private function newAttempt(Order $order, ?string $method): Payment
    {
        $number = $order->payments()->count() + 1;

        return $order->payments()->create([
            'midtrans_order_id' => "{$order->code}-{$number}",
            'gross_amount' => $order->total,
            'method' => $method,
        ]);
    }

    /** Membuat transaksinya di Midtrans (Core API atau Snap token) lalu memulai/melanjutkan hitung mundur. */
    private function open(Order $order, Payment $payment): Payment
    {
        // Hitung mundur dimulai saat pelanggan menekan Bayar (percobaan pertama dibuat), bukan saat order dibuat.
        // Ganti metode di tengah jalan tidak memperpanjangnya: transaksi baru hanya berlaku sampai batas yang sama.
        $deadline = $order->expires_at?->isFuture() ? $order->expires_at : now()->addMinutes((int) config('midtrans.expiry_minutes'));
        $minutes = max(1, (int) ceil(now()->diffInSeconds($deadline, true) / 60));

        $spec = PaymentMethods::chargeSpec($payment->method);

        if ($spec) {
            $reply = $this->gateway->charge($payment->midtrans_order_id, $payment->gross_amount, $order->customer_name, $spec, $minutes);

            $payment->update([
                'transaction_status' => $reply['transaction_status'] ?? 'pending',
                'payment_type' => $reply['payment_type'] ?? $spec['payment_type'],
                'raw_payload' => $reply,
            ]);
        } else {
            $token = $this->gateway->createSnapToken(
                $payment->midtrans_order_id,
                $payment->gross_amount,
                $order->customer_name,
                $minutes,
                PaymentMethods::enabledFor($payment->method),
            );

            $payment->update(['snap_token' => $token]);
        }

        $order->expires_at = $deadline;

        // Mencoba bayar lagi setelah kedaluwarsa/gagal membuat order kembali "menunggu pembayaran".
        // Status lunas tidak pernah diberikan di sini, itu hanya hak webhook.
        if (in_array($order->payment_status, [PaymentStatus::Expired, PaymentStatus::Failed], true)) {
            $order->payment_status = PaymentStatus::Pending;
        }

        $order->save();

        return $payment;
    }
}
