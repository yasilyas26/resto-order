<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Collection;

/**
 * Bentuk data order untuk papan karyawan. Memuat nama pemesan (PRD K-03) tapi tidak pernah kontaknya.
 */
class BoardPresenter
{
    /** Kartu papan. $rank = urutan order dari meja yang sama hari ini ("Order ke-2"). */
    public static function card(Order $order, ?int $rank = null): array
    {
        $order->loadMissing(['items.options', 'table']);

        $items = $order->items;
        $countable = $items->where('is_unavailable', false);

        return [
            'code' => $order->code,
            'queue' => $order->queueLabel(),
            'table' => $order->getRelationValue('table')?->number,
            'customer' => $order->customer_name,
            'orderType' => $order->order_type->label(),
            'sequence' => $rank,
            'kitchen' => $order->kitchen_status->value,
            'refund' => $order->refund_status->value,
            'refundAmount' => $order->refund_amount,
            'total' => $order->total,
            'paidAt' => $order->paid_at?->toIso8601String(),
            'paidAtLabel' => $order->paid_at?->translatedFormat('H:i'),
            'completedAtLabel' => $order->completed_at?->translatedFormat('H:i'),
            'servedCount' => $countable->whereNotNull('served_at')->count(),
            'servableCount' => $countable->count(),
            'items' => $items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->menu_name,
                'qty' => $item->qty,
                'options' => $item->options->pluck('option_name')->all(),
                'note' => $item->note,
                'station' => $item->category_name,
                'served' => $item->served_at !== null,
                'unavailable' => $item->is_unavailable,
                'lineTotal' => $item->line_total,
            ])->values()->all(),
        ];
    }

    /** Baris untuk tab Riwayat & Refund. */
    public static function historyRow(Order $order): array
    {
        $order->loadMissing(['items', 'table']);

        return [
            'code' => $order->code,
            'queue' => $order->queueLabel(),
            'table' => $order->getRelationValue('table')?->number,
            'customer' => $order->customer_name,
            'total' => $order->total,
            'paidAtLabel' => $order->paid_at?->translatedFormat('d M Y, H:i'),
            'completedAtLabel' => $order->completed_at?->translatedFormat('d M Y, H:i'),
            'kitchen' => $order->kitchen_status->value,
            'refund' => $order->refund_status->value,
            'refundAmount' => $order->refund_amount,
            'refundedBy' => $order->refunded_by ? $order->refundedBy?->name : null,
            'refundedAtLabel' => $order->refunded_at?->translatedFormat('d M Y, H:i'),
            'unavailableItems' => $order->items->where('is_unavailable', true)->map(
                fn (OrderItem $item) => "{$item->qty}x {$item->menu_name}"
            )->values()->all(),
            'itemCount' => $order->items->count(),
        ];
    }

    /** Urutan "Order ke-N" untuk satu order: berapa order lunas dari meja yang sama hari ini sampai order ini. */
    public static function rankOf(Order $order): ?int
    {
        if (! $order->paid_at) {
            return null;
        }

        return Order::query()
            ->where('table_id', $order->table_id)
            ->where('payment_status', PaymentStatus::Paid)
            ->whereDate('paid_at', $order->paid_at->toDateString())
            ->where(fn ($q) => $q
                ->where('paid_at', '<', $order->paid_at)
                ->orWhere(fn ($q) => $q->where('paid_at', $order->paid_at)->where('id', '<=', $order->id)))
            ->count();
    }

    /**
     * Urutan "Order ke-N" untuk banyak order sekaligus (satu query, bukan satu per kartu).
     *
     * @param  Collection<int, Order>  $orders
     * @return array<int, int> id order => urutan
     */
    public static function ranksFor(Collection $orders): array
    {
        $days = $orders->filter(fn (Order $o) => $o->paid_at)->map(fn (Order $o) => $o->paid_at->toDateString())->unique();

        if ($days->isEmpty()) {
            return [];
        }

        $paid = Order::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where(function ($query) use ($days) {
                foreach ($days as $day) {
                    $query->orWhereDate('paid_at', $day);
                }
            })
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get(['id', 'table_id', 'paid_at']);

        // foreach biasa, bukan closure: arrow function menangkap variabel by value, jadi isinya tidak akan sampai ke luar.
        $ranks = [];
        $nextNumber = [];

        foreach ($paid as $order) {
            $key = $order->table_id.'|'.$order->paid_at->toDateString();
            $nextNumber[$key] = ($nextNumber[$key] ?? 0) + 1;
            $ranks[$order->id] = $nextNumber[$key];
        }

        return $ranks;
    }
}
