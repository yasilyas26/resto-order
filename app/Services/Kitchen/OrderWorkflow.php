<?php

namespace App\Services\Kitchen;

use App\Enums\KitchenStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Menu;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Semua perubahan status dapur dan refund oleh karyawan lewat sini.
 *
 * Aturannya:
 * - hanya order yang sudah dibayar (dari webhook) yang boleh diproses; kitchen_status tidak pernah menyentuh payment_status
 * - tiap aksi mengunci baris order, jadi dua karyawan yang menekan tombol bersamaan diproses bergantian
 * - aksi yang diulang (mis. dua kali "Mulai Buat") tidak berbahaya
 */
class OrderWorkflow
{
    /** Queued -> Cooking. Dipanggil juga otomatis saat item pertama dicentang. */
    public function start(Order $order): Order
    {
        return $this->locked($order, function (Order $order) {
            $this->assertActive($order);

            if ($order->kitchen_status === KitchenStatus::Queued) {
                $order->kitchen_status = KitchenStatus::Cooking;
                $order->cooking_started_at = now();
                $order->save();
            }
        });
    }

    /** Selesai. Item yang masih belum dicentang ikut dianggap sudah dibawa. */
    public function complete(Order $order): Order
    {
        return $this->locked($order, function (Order $order) {
            $this->assertActive($order);

            $order->items()->where('is_unavailable', false)->whereNull('served_at')->update(['served_at' => now()]);

            $order->kitchen_status = KitchenStatus::Done;
            $order->completed_at = now();
            $order->save();
        });
    }

    /** Centang / batal centang "item sudah dibawa ke meja". */
    public function setServed(Order $order, OrderItem $item, bool $served): Order
    {
        return $this->locked($order, function (Order $order) use ($item, $served) {
            $this->assertActive($order);
            $item = $this->itemOf($order, $item);

            if ($item->is_unavailable) {
                throw new WorkflowException('Item yang tidak tersedia tidak perlu dicentang.');
            }

            $item->served_at = $served ? ($item->served_at ?? now()) : null;
            $item->save();

            // Begitu ada item yang diproses, order otomatis berstatus "Sedang dibuat".
            if ($served && $order->kitchen_status === KitchenStatus::Queued) {
                $order->kitchen_status = KitchenStatus::Cooking;
                $order->cooking_started_at = now();
                $order->save();
            }
        });
    }

    /**
     * Tandai item tidak tersedia (bahan habis): order jadi "Perlu refund" sebesar item itu, dan menunya
     * otomatis jadi habis supaya pengunjung lain tidak memesannya (PRD K-07). Bisa dibatalkan selama refund belum diberikan.
     */
    public function setUnavailable(Order $order, OrderItem $item, bool $unavailable): Order
    {
        return $this->locked($order, function (Order $order) use ($item, $unavailable) {
            $this->assertActive($order);
            $item = $this->itemOf($order, $item);

            if ($order->refund_status === RefundStatus::Refunded) {
                throw new WorkflowException('Refund untuk order ini sudah dikembalikan, jadi item tidak bisa diubah lagi.');
            }

            if ($item->is_unavailable === $unavailable) {
                return;
            }

            $item->is_unavailable = $unavailable;

            if ($unavailable) {
                $item->served_at = null;

                if ($item->menu_id) {
                    Menu::whereKey($item->menu_id)->update(['is_available' => false]);
                }
            }

            $item->save();

            // Nominal refund selalu dihitung ulang dari item yang ditandai, bukan ditambah-kurangi.
            $amount = (int) $order->items()->where('is_unavailable', true)->sum('line_total');
            $order->refund_amount = $amount;
            $order->refund_status = $amount > 0 ? RefundStatus::Needed : RefundStatus::None;
            $order->save();
        });
    }

    /** Kasir menandai uang refund sudah diserahkan. Tercatat siapa dan kapan (PRD K-08). */
    public function markRefunded(Order $order, User $by): Order
    {
        return $this->locked($order, function (Order $order) use ($by) {
            if ($order->refund_status !== RefundStatus::Needed) {
                throw new WorkflowException('Order ini tidak sedang menunggu refund.');
            }

            $order->refund_status = RefundStatus::Refunded;
            $order->refunded_by = $by->id;
            $order->refunded_at = now();
            $order->save();
        });
    }

    /** Menjalankan aksi di dalam transaksi, pada versi order terbaru yang barisnya dikunci. */
    private function locked(Order $order, Closure $action): Order
    {
        return DB::transaction(function () use ($order, $action) {
            $fresh = Order::lockForUpdate()->findOrFail($order->id);

            $action($fresh);

            return $fresh->refresh();
        });
    }

    private function assertActive(Order $order): void
    {
        if ($order->payment_status !== PaymentStatus::Paid) {
            throw new WorkflowException('Order ini belum dibayar, jadi belum bisa diproses.');
        }

        if ($order->kitchen_status === KitchenStatus::Done) {
            throw new WorkflowException('Order ini sudah selesai.');
        }
    }

    private function itemOf(Order $order, OrderItem $item): OrderItem
    {
        // Item harus milik order ini: mencegah mengubah item order lain lewat URL yang diutak-atik.
        return $order->items()->findOrFail($item->id);
    }
}
