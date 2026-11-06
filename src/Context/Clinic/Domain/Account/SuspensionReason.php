<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Account;

enum SuspensionReason: string
{
    case CLINIC_REQUEST = 'clinic_request';

    case UNPAID = 'unpaid';

    case END_OF_PILOT = 'end_of_pilot';

    case END_OF_DEMO = 'end_of_demo';

    case SECURITY = 'security';

    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CLINIC_REQUEST => 'Demande de la clinique',
            self::UNPAID         => 'Impayé',
            self::END_OF_PILOT   => 'Fin du pilote',
            self::END_OF_DEMO    => 'Fin de démo',
            self::SECURITY       => 'Incident de sécurité',
            self::OTHER          => 'Autre',
        };
    }
}
