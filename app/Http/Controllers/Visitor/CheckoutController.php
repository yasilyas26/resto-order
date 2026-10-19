<?php

namespace App\Http\Controllers\Visitor;

use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Table;
use App\Services\Ordering\InvalidCartException;
use App\Services\Ordering\PlaceOrder;
use App\Services\Payments\ChangePaymentMethod;
use App\Services\Payments\PaymentAttempts;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Support\PaymentMethods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    /**
     * Membuat order dari keranjang (belum membuka pembayaran: itu terjadi di halaman Pilih Metode).
     * Dipanggil lewat fetch() dari halaman keranjang, jadi balasannya JSON.
     */
    public function store(
        Request $request,
        PaymentGateway $gateway,
        PlaceOrder $placeOrder,
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
            // Kode order sebelumnya dari perangkat ini: kalau belum dibayar, isinya diperbarui (bukan dibuat ganda).
            'draft' => ['nullable', 'string', 'max:20'],
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
            $order = $placeOrder->execute($table, $request->user(), $data, $this->draftOrder($data['draft'] ?? null, $table));
        } catch (InvalidCartException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['items' => $e->errors]], 422);
        }

        // Pembayaran sengaja belum dibuka: pelanggan memilih metode dulu di halaman berikutnya, dan batas waktu
        // bayar baru mulai berjalan saat dia menekan Bayar di sana.
        return response()->json([
            'order_code' => $order->code,
            'order_url' => route('order.show', $order),
            'checkout_url' => route('order.checkout', $order),
        ], 201);
    }

    /** Order draft yang boleh diperbarui: meja sama, belum lunas, dan pembayarannya belum pernah dimulai. */
    private function draftOrder(?string $code, Table $table): ?Order
    {
        if (blank($code)) {
            return null;
        }

        return Order::where('code', $code)
            ->where('table_id', $table->id)
            ->where('payment_status', PaymentStatus::Pending)
            ->whereDoesntHave('payments')
            ->first();
    }

    /** Membuka pembayaran lagi untuk order yang belum lunas (popup ditutup, kedaluwarsa, atau gagal). */
    public function pay(Request $request, Order $order, PaymentGateway $gateway, PaymentAttempts $attempts, ChangePaymentMethod $change): JsonResponse
    {
        $data = $request->validate(['method' => ['nullable', Rule::in(PaymentMethods::keys())]]);

        if ($order->isPaid()) {
            return response()->json(['message' => 'Pesanan ini sudah dibayar.', 'result_url' => route('order.result', $order)], 409);
        }

        if (! $gateway->isConfigured()) {
            return $this->notConfigured();
        }

        // Metode dipilih di halaman "Pilih Metode": lewat ChangePaymentMethod supaya percobaan lama
        // yang metodenya beda dibatalkan dengan aman (dan tidak pernah terbayar dua kali).
        if (! empty($data['method'])) {
            return $this->changeMethod($order, $gateway, $change, $data['method']);
        }

        return $this->paymentResponse($order, $attempts, 200);
    }

    /** Ganti metode bayar: transaksi lama dibatalkan, lalu percobaan baru dibuat (lihat ChangePaymentMethod). */
    public function changeMethod(Order $order, PaymentGateway $gateway, ChangePaymentMethod $change, ?string $method = null): JsonResponse
    {
        if ($order->isPaid()) {
            return response()->json(['message' => 'Pesanan ini sudah dibayar.'], 409);
        }

        if (! $gateway->isConfigured()) {
            return $this->notConfigured();
        }

        try {
            $payment = $change->execute($order, $method);
        } catch (PaymentGatewayException) {
            return response()->json(['message' => $method !== null
                ? 'Metode pembayaran ini belum bisa dipakai saat ini. Coba metode lain, atau ulangi sebentar lagi.'
                : 'Metode pembayaran belum bisa diganti. Tunggu sebentar lalu coba lagi.'], 502);
        }

        // Ternyata transaksi lama sudah dibayar: tidak ada percobaan baru, supaya tidak terbayar dua kali.
        if ($payment === null) {
            return response()->json(['message' => 'Pembayaranmu sudah kami terima. Pesanan ini tidak perlu dibayar lagi.'], 409);
        }

        return response()->json([
            'order_code' => $order->code,
            'order_url' => route('order.show', $order),
            'checkout_url' => route('order.checkout', $order),
            'result_url' => route('order.result', $order),
            // snap: buka popup dengan snap_token (kartu). charge: transaksi sudah dibuat, tinggal tampilkan cara bayarnya di halaman hasil.
            'mode' => $payment->snap_token ? 'snap' : 'charge',
            'expires_at' => $order->fresh()->expires_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
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
            'checkout_url' => route('order.checkout', $order),
            'result_url' => route('order.result', $order),
            // snap: buka popup dengan snap_token (kartu). charge: transaksi sudah dibuat, tinggal tampilkan cara bayarnya di halaman hasil.
            'mode' => $payment->snap_token ? 'snap' : 'charge',
            'expires_at' => $order->fresh()->expires_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
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
