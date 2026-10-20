<?php

namespace App\Http\Controllers\Visitor;

use App\Http\Controllers\Controller;
use App\Enums\OrderStage;
use App\Models\Order;
use App\Models\Table;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentStatusSync;
use App\Support\PaymentInstructions;
use App\Support\PaymentLabel;
use App\Support\PaymentMethods;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman status order. Terbuka untuk siapa pun yang tahu kode ordernya (PRD: kode acak, tanpa login),
 * jadi hanya menampilkan item dan status, tidak pernah nama atau kontak pelanggan.
 */
class OrderStatusController extends Controller
{
    /**
     * Tujuan Midtrans setelah pembayaran yang keluar dari popup (aplikasi e-wallet, 3DS, dll.).
     * Midtrans menambahkan ?order_id=ORD-XXXX-2&transaction_status=... ke alamat ini; order_id itu
     * milik Midtrans (kode order + nomor percobaan), jadi dicocokkan lewat tabel payments.
     */
    public function finish(Request $request): RedirectResponse
    {
        $midtransId = (string) $request->query('order_id', '');

        $order = $midtransId === ''
            ? null
            : Order::whereHas('payments', fn ($q) => $q->where('midtrans_order_id', $midtransId))->first();

        return $order
            ? redirect()->route('order.result', $order)
            : redirect()->route('home');
    }

    /** Halaman "Pilih Metode Pembayaran". Order yang sudah lunas langsung dialihkan ke halaman hasil. */
    public function checkout(Request $request, Order $order): View|RedirectResponse
    {
        if (! $order->canBePaid()) {
            return redirect()->route('order.result', $order);
        }

        $order->load(['items.options', 'items.menu', 'table', 'payments']);

        // Sudah ada pembayaran berjalan (nomor VA / QR sudah dibuat): tombol Kembali di browser tidak boleh
        // mengantar pelanggan ke sini, jadi dilempar balik ke halaman pembayaran. Mengganti metode hanya lewat
        // tombol "Ganti Metode Pembayaran" (?ganti=1).
        if (! $request->boolean('ganti') && PaymentInstructions::for($order) !== null) {
            return redirect()->route('order.result', $order);
        }

        return view('visitor.payment-method', [
            'order' => $order,
            'groups' => PaymentMethods::groups(),
            // Metode terakhir yang dipilih diingat; kalau belum pernah memilih, QRIS yang paling umum.
            'selected' => $order->payments->sortByDesc('id')->pluck('method')->filter()->first() ?? 'qris',
            'selectedGroup' => PaymentMethods::groupOf($order->payments->sortByDesc('id')->pluck('method')->filter()->first() ?? 'qris'),
            'expiresAt' => $order->stage() === OrderStage::Waiting ? $order->expires_at?->toIso8601String() : null,
            'serverTime' => now()->toIso8601String(),
        ]);
    }

    /**
     * Halaman hasil pembayaran: berhasil, gagal, kedaluwarsa, atau masih menunggu (memeriksa ulang otomatis).
     * Isinya mengikuti status order di server, bukan parameter di URL, jadi tidak bisa dipalsukan.
     */
    public function result(Order $order): View
    {
        $order->load(['items.options', 'table', 'payments']);
        $stage = $order->stage();

        return view('visitor.payment-result', [
            'order' => $order,
            'status' => $order->toStatus(),
            'state' => match ($stage) {
                OrderStage::Paid, OrderStage::Cooking, OrderStage::Done => 'success',
                OrderStage::Failed => 'failed',
                OrderStage::Expired => 'expired',
                OrderStage::Waiting => 'waiting',
            },
            'paymentLabel' => PaymentLabel::for($order->payments->whereNotNull('payment_type')->last()?->payment_type),
            'canOrderMore' => Table::fromSession() !== null,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['items.options', 'table', 'payments']);

        return view('visitor.order-status', [
            'order' => $order,
            'status' => $order->toStatus(),
            'paymentLabel' => PaymentLabel::for($order->payments->whereNotNull('payment_type')->last()?->payment_type),
            // "Pesan tambahan" hanya masuk akal kalau pengunjung masih punya meja aktif.
            'canOrderMore' => Table::fromSession() !== null,
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
