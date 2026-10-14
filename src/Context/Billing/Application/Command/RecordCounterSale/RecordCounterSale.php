<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordCounterSale;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Vente au comptoir »: articles sold at the desk without a visit, invoiced
 * and paid in one go.
 */
final readonly class RecordCounterSale implements CommandInterface
{
    /**
     * @param list<array{articleId: string, quantity: int}> $lines
     */
    public function __construct(
        public string $clinicId,
        public ?string $payerClientId,
        public array $lines,
        public string $method,
        public ?string $reference = null,
        public ?string $recordedByUserId = null,
    ) {
    }
}
