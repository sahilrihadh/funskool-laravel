<?php

namespace App\Payments;

final readonly class RefundResult
{
    public function __construct(
        public bool $accepted,
        public ?string $reference,
        public string $status,
        public array $payload = [],
        public ?string $error = null,
    ) {}
}
