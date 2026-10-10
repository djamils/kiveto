<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum AuthorizedPersonRelationship: string
{
    case SPOUSE              = 'spouse';
    case PARENT              = 'parent';
    case CHILD               = 'child';
    case SIBLING             = 'sibling';
    case HOUSEHOLD_MEMBER    = 'household_member';
    case FRIEND_OR_NEIGHBOUR = 'friend_or_neighbour';
    case EMPLOYEE            = 'employee';
    case BOARDING_OR_FOSTER  = 'boarding_or_foster';
    case OTHER               = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SPOUSE              => 'Conjoint',
            self::PARENT              => 'Parent',
            self::CHILD               => 'Enfant',
            self::SIBLING             => 'Frère ou sœur',
            self::HOUSEHOLD_MEMBER    => 'Autre membre du foyer',
            self::FRIEND_OR_NEIGHBOUR => 'Ami ou voisin',
            self::EMPLOYEE            => 'Salarié',
            self::BOARDING_OR_FOSTER  => 'Pension ou famille d’accueil',
            self::OTHER               => 'Autre',
        };
    }
}
