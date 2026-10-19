<?php

namespace Tests\Support;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;

/** Pengganti Midtrans untuk tes: tidak ada jaringan, dan semua panggilan tercatat. */
class FakeGateway implements PaymentGateway
{
    public bool $configured = true;

    public bool $failTokens = false;

    public bool $failStatus = false;

    public bool $failCancel = false;

    /** @var list<string> */
    public array $cancelled = [];

    /** @var list<array<string, mixed>> */
    public array $tokenRequests = [];

    /** Status "dari Midtrans" per midtrans_order_id, untuk fetchStatus(). @var array<string, array<string, mixed>> */
    public array $statuses = [];

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function createSnapToken(string $midtransOrderId, int $grossAmount, string $customerName, int $expiryMinutes, ?array $enabledPayments = null): string
    {
        if ($this->failTokens) {
            throw new PaymentGatewayException('Midtrans sedang error.');
        }

        $this->tokenRequests[] = compact('midtransOrderId', 'grossAmount', 'customerName', 'expiryMinutes', 'enabledPayments');

        return 'snap-token-'.count($this->tokenRequests).'-'.$midtransOrderId;
    }

    /** @var list<array<string, mixed>> */
    public array $charges = [];

    public bool $failCharge = false;

    /** Balasan tambahan untuk charge, digabung ke balasan dasar (mis. va_numbers). @var array<string, mixed> */
    public array $chargeReply = [];

    public function charge(string $midtransOrderId, int $grossAmount, string $customerName, array $spec, int $expiryMinutes): array
    {
        if ($this->failCharge) {
            throw new PaymentGatewayException('Midtrans menolak: Payment channel is not activated.');
        }

        $this->charges[] = compact('midtransOrderId', 'grossAmount', 'customerName', 'spec', 'expiryMinutes');

        return $this->chargeReply + [
            'status_code' => '201', 'order_id' => $midtransOrderId, 'gross_amount' => number_format($grossAmount, 2, '.', ''),
            'payment_type' => $spec['payment_type'], 'transaction_status' => 'pending', 'transaction_id' => 'tx-'.$midtransOrderId,
        ];
    }

    public function cancel(string $midtransOrderId): void
    {
        if ($this->failCancel) {
            throw new PaymentGatewayException('Midtrans menolak pembatalan.');
        }

        $this->cancelled[] = $midtransOrderId;
    }

    public function fetchStatus(string $midtransOrderId): ?array
    {
        if ($this->failStatus) {
            throw new PaymentGatewayException('Midtrans sedang error.');
        }

        return $this->statuses[$midtransOrderId] ?? null;
    }
}
