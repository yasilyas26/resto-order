<?php

namespace App\Models;

use App\Enums\KitchenStatus;
use App\Enums\OrderStage;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Support\PaymentLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Order tidak punya soft delete dan tidak pernah dihapus: ini catatan keuangan.
 * Order yang salah ditangani lewat status, bukan dihapus.
 */
class Order extends Model
{
    // Kolom status (payment/kitchen/refund) sengaja tidak mass-assignable:
    // masing-masing hanya boleh diubah lewat jalur resminya (webhook, papan karyawan).
    protected $fillable = [
        'code',
        'table_id',
        'user_id',
        'customer_name',
        'customer_contact',
        'order_type',
        'subtotal',
        'total',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'order_type' => OrderType::class,
            'payment_status' => PaymentStatus::class,
            'kitchen_status' => KitchenStatus::class,
            'refund_status' => RefundStatus::class,
            'subtotal' => 'integer',
            'total' => 'integer',
            'refund_amount' => 'integer',
            'paid_at' => 'datetime',
            'cooking_started_at' => 'datetime',
            'queue_date' => 'date',
            'queue_number' => 'integer',
            'completed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Nomor antrean untuk dipanggil, mis. "A-007" (Makan di Tempat) atau "B-012" (Bawa Pulang). Null sebelum lunas. */
    public function queueLabel(): ?string
    {
        if ($this->queue_number === null) {
            return null;
        }

        return sprintf('%s-%03d', $this->order_type === OrderType::Takeaway ? 'B' : 'A', $this->queue_number);
    }

    /** URL publik memakai kode acak (/order/ORD-7F3K9Q), bukan id berurutan yang gampang ditebak. */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Karyawan (kasir) yang menyerahkan uang refund. */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Kode publik 6 karakter acak tanpa huruf/angka yang mudah tertukar. */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = "ORD-{$suffix}";
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }

    public function stage(): OrderStage
    {
        return match (true) {
            $this->payment_status === PaymentStatus::Paid => match ($this->kitchen_status) {
                KitchenStatus::Queued => OrderStage::Paid,
                KitchenStatus::Cooking => OrderStage::Cooking,
                KitchenStatus::Done => OrderStage::Done,
            },
            $this->payment_status === PaymentStatus::Failed => OrderStage::Failed,
            $this->payment_status === PaymentStatus::Expired => OrderStage::Expired,
            // Masih pending di database tapi batas bayar sudah lewat (webhook "expire" belum sempat datang).
            $this->expires_at !== null && $this->expires_at->isPast() => OrderStage::Expired,
            default => OrderStage::Waiting,
        };
    }

    /** Pengunjung boleh mencoba bayar selama order belum lunas. */
    public function canBePaid(): bool
    {
        return ! $this->isPaid();
    }

    /** Status untuk halaman status dan polling. Tidak memuat data pelanggan. */
    public function toStatus(?string $message = null): array
    {
        $stage = $this->stage();

        return [
            'stage' => $stage->value,
            'label' => $stage->label(),
            'message' => $message ?? $stage->message(),
            'step' => $stage->step(),
            'paid' => $this->isPaid(),
            'queue' => $this->queueLabel(),
            'payable' => $this->canBePaid(),
            'refund' => $this->refund_status->value,
            'refundAmount' => $this->refund_amount,
            'paidAt' => $this->paid_at ? $this->paid_at->translatedFormat('H:i').' WIB' : null,
            'paymentLabel' => PaymentLabel::for($this->payments->whereNotNull('payment_type')->last()?->payment_type),
        ];
    }

    /** Ringkasan untuk daftar riwayat. Tidak memuat data kontak pelanggan. */
    public function toSummary(): array
    {
        $this->loadMissing(['items.options', 'table']);
        $stage = $this->stage();

        return [
            'code' => $this->code,
            'queue' => $this->queueLabel(),
            'url' => route('order.show', $this),
            // Bukan $this->table: di dalam model itu adalah properti bawaan Eloquent (nama tabel "orders").
            'table' => $this->getRelationValue('table')?->number,
            'orderType' => $this->order_type->label(),
            'createdAt' => $this->created_at->translatedFormat('d M Y, H:i').' WIB',
            'stage' => $stage->value,
            'stageLabel' => $stage->label(),
            'active' => $stage->isActive(),
            'total' => $this->total,
            'items' => $this->items->map(fn (OrderItem $item) => [
                'name' => $item->menu_name,
                'qty' => $item->qty,
                'options' => $item->options->pluck('option_name')->join(', '),
                'note' => $item->note,
            ])->all(),
        ];
    }
}
