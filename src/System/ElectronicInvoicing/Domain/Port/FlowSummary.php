<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

/**
 * A flow the platform holds for an organisation, as listed by a search.
 */
final readonly class FlowSummary
{
    public function __construct(
        public string $flowId,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
