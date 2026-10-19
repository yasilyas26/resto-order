<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gambar QR pembayaran QRIS/GoPay. API status Midtrans tidak menyertakan kode QR-nya, jadi gambarnya
 * diambil dari endpoint QR Midtrans memakai server key (tidak pernah dikirim ke browser) dan diteruskan ke pelanggan.
 */
class PaymentQrController extends Controller
{
    public function __invoke(Order $order): Response
    {
        /** @var Payment|null $payment */
        $payment = $order->payments()->latest('id')->get()->first(
            fn (Payment $p) => $p->transaction_status === 'pending' && in_array($p->payment_type, ['qris', 'gopay'], true) && ! empty($p->raw_payload['transaction_id']),
        );

        abort_if($order->isPaid() || ! $payment, 404);

        try {
            $response = Http::withBasicAuth((string) config('midtrans.server_key'), '')
                ->timeout(10)
                ->get(rtrim(config('midtrans.api_url'), '/')."/v2/{$payment->payment_type}/{$payment->raw_payload['transaction_id']}/qr-code");
        } catch (Throwable $e) {
            report($e);
            abort(502);
        }

        abort_unless($response->successful(), 502);

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type') ?: 'image/png',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}
