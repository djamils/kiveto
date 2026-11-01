<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/** The requests the clinic answers (the questionnaire is not one of them). */
enum PortalRequestKind: string
{
    case PET_CHANGE           = 'pet_change';
    case PRESCRIPTION_RENEWAL = 'prescription_renewal';

    /** « Votre demande de {label} pour … » */
    public function label(): string
    {
        return match ($this) {
            self::PET_CHANGE           => 'modification',
            self::PRESCRIPTION_RENEWAL => 'renouvellement d\'ordonnance',
        };
    }
}
