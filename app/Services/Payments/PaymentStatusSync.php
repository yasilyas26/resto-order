<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Cadangan kalau webhook telat atau hilang: tanya langsung status ke Midtrans dari server.
 * Datanya tetap resmi (diambil server-ke-server memakai server key), lalu diproses
 * lewat jalur yang sama dengan webhook.
 */
class PaymentStatusSync
{
    public function __construct(
        private PaymentGateway $gateway,
        private PaymentNotificationHandler $handler,
    ) {}

    /** @throws PaymentGatewayException */
    public function sync(Order $order): void
    {
        if ($order->isPaid()) {
            return;
        }

        foreach ($order->payments()->latest('id')->get() as $payment) {
            $status = $this->gateway->fetchStatus($payment->midtrans_order_id);

            if ($status === null) {
                continue;
            }

            $this->handler->handle($status, verifySignature: false);
        }
    }
}
