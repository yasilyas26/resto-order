<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Table;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentStatusSync;
use App\Support\PaymentLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Halaman status order. Terbuka untuk siapa pun yang tahu kode ordernya (PRD: kode acak, tanpa login),
 * jadi hanya menampilkan item dan status, tidak pernah nama atau kontak pelanggan.
 */
class OrderStatusController extends Controller
{
    public function show(Order $order): View
    {
        $order->load(['items.options', 'table', 'payments']);

        return view('visitor.order-status', [
            'order' => $order,
            'status' => $order->toStatus(),
            'paymentLabel' => PaymentLabel::for($order->payments->whereNotNull('payment_type')->last()?->payment_type),
            // "Pesan tambahan" hanya masuk akal kalau pengunjung masih punya meja aktif.
            'canOrderMore' => Table::fromSession() !== null,
            'snapUrl' => config('midtrans.snap_js_url'),
            'clientKey' => config('midtrans.client_key'),
        ]);
    }

    /** Dipolling halaman status tiap beberapa detik. */
    public function status(Order $order): JsonResponse
    {
        return response()->json($order->toStatus());
    }

    /** Tombol "Cek ulang pembayaran": tanya langsung ke Midtrans kalau webhook telat. */
    public function recheck(Order $order, PaymentGateway $gateway, PaymentStatusSync $sync): JsonResponse
    {
        $message = null;

        if ($gateway->isConfigured()) {
            try {
                $sync->sync($order);
            } catch (PaymentGatewayException) {
                $message = 'Belum bisa menghubungi Midtrans. Coba lagi sebentar lagi.';
            }
        }

        return response()->json($order->fresh()->toStatus($message));
    }
}
