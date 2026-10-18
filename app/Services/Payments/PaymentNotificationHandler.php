<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Satu-satunya tempat yang menentukan sebuah order lunas.
 * Dipakai oleh webhook Midtrans, dan oleh tombol "Cek ulang pembayaran" (yang memakai data resmi dari API Midtrans).
 */
class PaymentNotificationHandler
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  bool  $verifySignature  false hanya untuk data yang kita ambil sendiri dari API Midtrans lewat TLS
     */
    public function handle(array $payload, bool $verifySignature = true): NotificationResult
    {
        if ($verifySignature && ! MidtransSignature::isValid($payload, (string) config('midtrans.server_key'))) {
            Log::warning('Notifikasi Midtrans ditolak: signature tidak cocok.', ['order_id' => $payload['order_id'] ?? null]);

            return NotificationResult::InvalidSignature;
        }

        $payment = Payment::where('midtrans_order_id', $payload['order_id'] ?? '')->first();

        if (! $payment) {
            // Contoh: tombol "test notification" di dashboard Midtrans. Signature valid, jadi tidak berbahaya; abaikan.
            return NotificationResult::UnknownPayment;
        }

        if ($this->grossAmount($payload) !== $payment->gross_amount) {
            Log::warning('Notifikasi Midtrans ditolak: nominal tidak sama dengan database.', [
                'order_id' => $payment->midtrans_order_id,
                'dikirim' => $payload['gross_amount'] ?? null,
                'di_database' => $payment->gross_amount,
            ]);

            return NotificationResult::AmountMismatch;
        }

        DB::transaction(function () use ($payload, $payment) {
            // Kunci baris supaya dua notifikasi yang datang bersamaan diproses bergantian (idempoten).
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);
            $order = Order::lockForUpdate()->findOrFail($payment->order_id);

            $payment->update([
                'transaction_status' => $payload['transaction_status'] ?? $payment->transaction_status,
                'payment_type' => $payload['payment_type'] ?? $payment->payment_type,
                'raw_payload' => $payload,
            ]);

            $this->applyToOrder($order, $this->map($payload), $payload);
        });

        return NotificationResult::Processed;
    }

    /** Null = tidak mengubah status order (mis. masih pending, atau capture yang masih ditinjau fraud). */
    private function map(array $payload): ?PaymentStatus
    {
        $status = $payload['transaction_status'] ?? null;

        return match ($status) {
            'settlement' => PaymentStatus::Paid,
            'capture' => ($payload['fraud_status'] ?? null) === 'accept' ? PaymentStatus::Paid : null,
            'expire' => PaymentStatus::Expired,
            'cancel', 'deny' => PaymentStatus::Failed,
            default => null,
        };
    }

    private function applyToOrder(Order $order, ?PaymentStatus $new, array $payload): void
    {
        // Order yang sudah lunas tidak pernah diturunkan lagi, apa pun yang datang belakangan.
        if ($new === null || $order->isPaid()) {
            return;
        }

        if ($new === PaymentStatus::Paid) {
            $order->payment_status = PaymentStatus::Paid;
            $order->paid_at = $this->paidAt($payload);
            $order->save();

            return;
        }

        // Satu percobaan kedaluwarsa/gagal belum berarti order gagal kalau masih ada percobaan lain yang berjalan.
        $anotherAttemptRunning = $order->payments()
            ->where(fn ($q) => $q->whereNull('transaction_status')->orWhere('transaction_status', 'pending'))
            ->exists();

        if (! $anotherAttemptRunning) {
            $order->payment_status = $new;
            $order->save();
        }
    }

    private function grossAmount(array $payload): int
    {
        // Midtrans mengirim string seperti "51700.00".
        return (int) round((float) ($payload['gross_amount'] ?? -1));
    }

    private function paidAt(array $payload): Carbon
    {
        $time = $payload['settlement_time'] ?? $payload['transaction_time'] ?? null;

        // Waktu dari Midtrans memakai zona WIB.
        return $time ? Carbon::parse($time, 'Asia/Jakarta') : now();
    }
}
