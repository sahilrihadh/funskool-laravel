<?php

namespace App\Payments;

final readonly class PaymentCheckout
{
    public function __construct(
        public string $redirectUrl,
        public string $reference,
        public array $payload = [],
    ) {}
}
