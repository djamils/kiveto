<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

/**
 * What became of the body once the death is declared.
 */
enum BodyDisposal: string
{
    case INDIVIDUAL_CREMATION = 'individual_cremation';
    case COLLECTIVE_CREMATION = 'collective_cremation';
    case RETURNED_TO_OWNER    = 'returned_to_owner';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL_CREMATION => 'Crémation individuelle',
            self::COLLECTIVE_CREMATION => 'Crémation collective',
            self::RETURNED_TO_OWNER    => 'Restitué au propriétaire',
        };
    }
}
