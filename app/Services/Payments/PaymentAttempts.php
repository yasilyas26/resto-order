<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

/**
 * Mengelola percobaan bayar sebuah order. Satu order bisa dicoba beberapa kali (gagal, kedaluwarsa),
 * dan tiap percobaan memakai ID Midtrans yang unik: ORD-XXXXXX-1, ORD-XXXXXX-2, dst.
 */
class PaymentAttempts
{
    public function __construct(private PaymentGateway $gateway) {}

    /**
     * Percobaan bayar yang bisa dipakai sekarang: pakai ulang yang masih berjalan, atau buat yang baru.
     *
     * @throws PaymentGatewayException
     */
    public function current(Order $order): Payment
    {
        $latest = $order->payments()->latest('id')->first();

        if ($latest && $latest->isOpen()) {
            // Token masih berlaku: pakai ulang supaya tidak menumpuk transaksi (dan VA yang sudah dibuat tetap sama).
            if ($latest->snap_token && $order->expires_at?->isFuture()) {
                return $latest;
            }

            // Baris sudah ada tapi token belum sempat dibuat (mis. Midtrans sedang error): coba minta lagi.
            if (! $latest->snap_token) {
                return $this->requestToken($order, $latest);
            }
        }

        return $this->requestToken($order, $this->newAttempt($order));
    }

    private function newAttempt(Order $order): Payment
    {
        $number = $order->payments()->count() + 1;

        return $order->payments()->create([
            'midtrans_order_id' => "{$order->code}-{$number}",
            'gross_amount' => $order->total,
        ]);
    }

    private function requestToken(Order $order, Payment $payment): Payment
    {
        $minutes = (int) config('midtrans.expiry_minutes');

        $token = $this->gateway->createSnapToken(
            $payment->midtrans_order_id,
            $payment->gross_amount,
            $order->customer_name,
            $minutes,
        );

        $payment->update(['snap_token' => $token]);

        $order->expires_at = now()->addMinutes($minutes);

        // Mencoba bayar lagi setelah kedaluwarsa/gagal membuat order kembali "menunggu pembayaran".
        // Status lunas tidak pernah diberikan di sini, itu hanya hak webhook.
        if (in_array($order->payment_status, [PaymentStatus::Expired, PaymentStatus::Failed], true)) {
            $order->payment_status = PaymentStatus::Pending;
        }

        $order->save();

        return $payment;
    }
}
