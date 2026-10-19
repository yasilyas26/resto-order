<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

/**
 * Mengganti metode bayar order yang belum lunas (mis. dari QRIS ke kartu).
 *
 * Midtrans mengunci metode begitu pelanggan memilihnya, jadi mengganti berarti membatalkan transaksi lama
 * lalu membuat percobaan baru dengan ID baru (ORD-XXXXXX-2). Supaya pelanggan tidak bisa membayar dua kali,
 * status transaksi lama selalu ditanyakan ke Midtrans dulu: kalau ternyata sudah dibayar, order dilunasi
 * lewat jalur resmi dan tidak ada percobaan baru.
 */
class ChangePaymentMethod
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentNotificationHandler $handler,
        private PaymentAttempts $attempts,
    ) {}

    /**
     * @param  string|null  $method  kunci PaymentMethods yang dipilih pelanggan (null = tidak dibatasi)
     * @return Payment|null percobaan baru yang siap dibayar, atau null kalau order ternyata sudah lunas
     *
     * @throws PaymentGatewayException kalau Midtrans tidak bisa dihubungi atau menolak pembatalan (tidak ada yang berubah)
     */
    public function execute(Order $order, ?string $method = null): ?Payment
    {
        foreach ($order->payments()->orderBy('id')->get() as $payment) {
            if (! $payment->isOpen()) {
                continue;
            }

            $status = $this->gateway->fetchStatus($payment->midtrans_order_id);

            // Belum ada di Midtrans: pelanggan belum memilih metode di popup, jadi tokennya masih bebas dipakai.
            // Kalau yang diminta metode lain, token itu dibuang (belum ada uang yang terlibat) dan dibuatkan yang baru.
            if ($status === null) {
                if ($method !== null && $payment->method !== $method) {
                    $payment->update(['transaction_status' => 'cancel']);
                }

                continue;
            }

            // Diproses lewat jalur yang sama dengan webhook: kalau sudah dibayar, order langsung lunas di sini.
            $this->handler->handle($status, verifySignature: false);

            if ($order->fresh()->isPaid()) {
                return null;
            }

            if (($status['transaction_status'] ?? null) === 'pending') {
                // Metode yang sama dan masih berlaku: pelanggan hanya kembali ke transaksinya (nomor VA / QR yang sama), bukan ganti.
                if ($method !== null && $payment->method === $method && $order->fresh()->expires_at?->isFuture()) {
                    continue;
                }

                $this->gateway->cancel($payment->midtrans_order_id);
                $payment->update(['transaction_status' => 'cancel']);
            }
        }

        return $this->attempts->current($order->fresh(), $method);
    }
}
