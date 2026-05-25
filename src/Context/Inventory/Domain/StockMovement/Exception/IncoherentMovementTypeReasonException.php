<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockMovement\Exception;

use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;

final class IncoherentMovementTypeReasonException extends \DomainException
{
    public function __construct(MovementType $type, MovementReason $reason)
    {
        parent::__construct(\sprintf(
            'Incoherent movement: reason "%s" cannot be used with type "%s".',
            $reason->value,
            $type->value
        ));
    }
}
