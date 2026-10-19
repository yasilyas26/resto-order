<?php

namespace App\Http\Controllers\Visitor;

use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Table;
use App\Services\Ordering\InvalidCartException;
use App\Services\Ordering\PlaceOrder;
use App\Services\Payments\ChangePaymentMethod;
use App\Services\Payments\PaymentAttempts;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    /**
     * Membuat order dari keranjang, lalu meminta Snap token.
     * Dipanggil lewat fetch() dari halaman keranjang, jadi balasannya JSON.
     */
    public function store(
        Request $request,
        PaymentGateway $gateway,
        PlaceOrder $placeOrder,
        PaymentAttempts $attempts,
    ): JsonResponse {
        $table = Table::fromSession();

        if (! $table) {
            return response()->json([
                'message' => 'Meja belum dipilih atau sudah tidak aktif. Pilih meja lagi.',
                'redirect' => route('home'),
            ], 409);
        }

        // Dicek sebelum membuat order, supaya tidak ada order yatim yang tidak mungkin dibayar.
        if (! $gateway->isConfigured()) {
            return $this->notConfigured();
        }

        $data = $request->validate([
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'items.*.options' => ['nullable', 'array', 'max:30'],
            'items.*.options.*' => ['integer'],
        ], [
            'items.required' => 'Keranjang masih kosong.',
            'items.min' => 'Keranjang masih kosong.',
        ]);

        try {
            $order = $placeOrder->execute($table, $request->user(), $data);
        } catch (InvalidCartException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['items' => $e->errors]], 422);
        }

        return $this->paymentResponse($order, $attempts, 201);
    }

    /** Membuka pembayaran lagi untuk order yang belum lunas (popup ditutup, kedaluwarsa, atau gagal). */
    public function pay(Order $order, PaymentGateway $gateway, PaymentAttempts $attempts): JsonResponse
    {
        if ($order->isPaid()) {
            return response()->json(['message' => 'Pesanan ini sudah dibayar.'], 409);
        }

        if (! $gateway->isConfigured()) {
            return $this->notConfigured();
        }

        return $this->paymentResponse($order, $attempts, 200);
    }

    /** Ganti metode bayar: transaksi lama dibatalkan, lalu percobaan baru dibuat (lihat ChangePaymentMethod). */
    public function changeMethod(Order $order, PaymentGateway $gateway, ChangePaymentMethod $change): JsonResponse
    {
        if ($order->isPaid()) {
            return response()->json(['message' => 'Pesanan ini sudah dibayar.'], 409);
        }

        if (! $gateway->isConfigured()) {
            return $this->notConfigured();
        }

        try {
            $payment = $change->execute($order);
        } catch (PaymentGatewayException) {
            return response()->json(['message' => 'Metode pembayaran belum bisa diganti. Tunggu sebentar lalu coba lagi.'], 502);
        }

        // Ternyata transaksi lama sudah dibayar: tidak ada percobaan baru, supaya tidak terbayar dua kali.
        if ($payment === null) {
            return response()->json(['message' => 'Pembayaranmu sudah kami terima. Pesanan ini tidak perlu dibayar lagi.'], 409);
        }

        return response()->json([
            'order_code' => $order->code,
            'order_url' => route('order.show', $order),
            'snap_token' => $payment->snap_token,
        ]);
    }

    private function paymentResponse(Order $order, PaymentAttempts $attempts, int $status): JsonResponse
    {
        try {
            $payment = $attempts->current($order);
        } catch (PaymentGatewayException) {
            // Order sudah tersimpan; pengunjung bisa mencoba bayar lagi dari halaman status.
            return response()->json([
                'message' => 'Pesananmu sudah tersimpan, tapi pembayaran belum bisa dibuka. Coba lagi dari halaman status.',
                'order_code' => $order->code,
                'order_url' => route('order.show', $order),
            ], 502);
        }

        return response()->json([
            'order_code' => $order->code,
            'order_url' => route('order.show', $order),
            'snap_token' => $payment->snap_token,
        ], $status);
    }

    private function notConfigured(): JsonResponse
    {
        return response()->json([
            'message' => 'Pembayaran online belum disiapkan. Silakan hubungi kasir.',
        ], 503);
    }
}
