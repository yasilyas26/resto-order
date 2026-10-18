<?php

namespace App\Http\Controllers\Visitor;

use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Table;
use App\Services\Ordering\InvalidCartException;
use App\Services\Ordering\PlaceOrder;
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
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_contact' => ['nullable', 'string', 'max:100'],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'items.*.options' => ['nullable', 'array', 'max:30'],
            'items.*.options.*' => ['integer'],
        ], [
            'customer_name.required' => 'Nama pemesan wajib diisi.',
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
