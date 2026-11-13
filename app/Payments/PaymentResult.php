<?php

namespace App\Payments;

final readonly class PaymentResult
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public function __construct(
        public string $status,
        public ?string $orderNumber,
        public ?string $reference = null,
        public ?string $paymentId = null,
        public ?float $amount = null,
        public array $payload = [],
        public ?string $eventId = null,
        public ?string $eventType = null,
    ) {}
}
