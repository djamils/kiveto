<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\MatchManualDelivery;

use App\Shared\Application\Bus\CommandInterface;

final readonly class MatchManualDelivery implements CommandInterface
{
    public function __construct(
        public string $unmatchedDeliveryId,
        public string $purchaseOrderId,
        public string $clinicId,
        public ?string $matchedBy,
    ) {
    }
}
