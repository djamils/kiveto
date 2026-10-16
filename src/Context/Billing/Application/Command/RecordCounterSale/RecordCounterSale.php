<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordCounterSale;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Vente au comptoir »: articles sold at the desk without a visit, invoiced
 * and paid in one go.
 *
 * The sale names its client: one on file (`payerClientId`), or a newcomer
 * whose record is created with it (`newClient`) — unless the client refuses
 * to give their contact details (`contactDetailsRefused`).
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
        public ?CounterSaleNewClient $newClient = null,
        public bool $contactDetailsRefused = false,
    ) {
    }
}
