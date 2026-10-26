<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * The visit an invoice charges, as its messages name it: « la consultation
 * de Minou du 16 octobre 2026 ».
 */
final readonly class VisitSummary
{
    public function __construct(
        public ?string $animalName,
        public \DateTimeImmutable $openedAt,
    ) {
    }
}
