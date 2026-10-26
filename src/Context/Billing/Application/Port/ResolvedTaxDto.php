<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Domain\ValueObject\OperationCategory;

final readonly class ResolvedTaxDto
{
    /**
     * @param list<string> $legalMentions
     */
    public function __construct(
        public string $ratePercent,
        public OperationCategory $nature,
        public array $legalMentions = [],
    ) {
    }
}
