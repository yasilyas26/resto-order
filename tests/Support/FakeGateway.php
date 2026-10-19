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

    public function createSnapToken(string $midtransOrderId, int $grossAmount, string $customerName, int $expiryMinutes): string
    {
        if ($this->failTokens) {
            throw new PaymentGatewayException('Midtrans sedang error.');
        }

        $this->tokenRequests[] = compact('midtransOrderId', 'grossAmount', 'customerName', 'expiryMinutes');

        return 'snap-token-'.count($this->tokenRequests).'-'.$midtransOrderId;
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
