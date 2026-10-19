<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\PaymentLabel;
use Illuminate\Http\Request;

/** Riwayat Transaksi: semua order (lunas atau tidak), hanya dilihat. Order tidak pernah diubah atau dihapus dari sini. */
class TransactionController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $orders = Order::with(['items.options', 'table', 'payments', 'refundedBy'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', $like)
                ->orWhere('customer_name', 'like', $like)
                ->orWhereHas('table', fn ($t) => $t->where('number', 'like', $like))))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $rows = $orders->getCollection()->map(fn (Order $order) => $this->row($order));

        return view('admin.transactions.index', ['orders' => $orders, 'rows' => $rows, 'search' => $search]);
    }

    /** @return array<string, mixed> */
    private function row(Order $order): array
    {
        $payment = $order->payments->whereNotNull('payment_type')->last();

        [$payLabel, $payTone] = match ($order->payment_status) {
            PaymentStatus::Paid => ['Lunas', 'green'],
            PaymentStatus::Pending => $order->expires_at?->isPast() ? ['Kedaluwarsa', 'gray'] : ['Menunggu', 'amber'],
            PaymentStatus::Expired => ['Kedaluwarsa', 'gray'],
            PaymentStatus::Failed => ['Gagal', 'red'],
        };

        $stage = $order->stage();
        $table = $order->getRelationValue('table');

        return [
            'code' => $order->code,
            'time' => $order->created_at->translatedFormat('d M, H:i'),
            'table' => $table ? "Meja {$table->number}" : '—',
            'type' => $order->order_type->label(),
            'customer' => $order->customer_name,
            'summary' => $order->items->map(fn ($i) => "{$i->qty}x {$i->menu_name}")->join(', '),
            'total' => $order->total,
            'method' => PaymentLabel::for($payment?->payment_type) ?? '—',
            'payLabel' => $payLabel,
            'payTone' => $payTone,
            'stageLabel' => $order->isPaid() ? $stage->label() : '—',
            'queue' => $order->queueLabel(),
            'refund' => $order->refund_status === RefundStatus::None ? null : [
                'status' => $order->refund_status->value,
                'amount' => $order->refund_amount,
                'by' => $order->refundedBy?->name,
            ],
            'items' => $order->items->map(fn ($i) => [
                'name' => $i->menu_name,
                'qty' => $i->qty,
                'options' => $i->options->pluck('option_name')->join(', '),
                'note' => $i->note,
                'unavailable' => (bool) $i->is_unavailable,
                'total' => $i->line_total,
            ])->all(),
        ];
    }
}
