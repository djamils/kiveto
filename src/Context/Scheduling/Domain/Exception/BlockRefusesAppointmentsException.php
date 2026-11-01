<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Exception;

use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;

/**
 * The slot falls in a planning block whose type takes no appointment
 * (leave, training, admin).
 */
final class BlockRefusesAppointmentsException extends \DomainException
{
    public static function ofType(PlanningBlockType $type): self
    {
        return new self('Cannot schedule appointment in a ' . $type->value . ' block.');
    }
}
