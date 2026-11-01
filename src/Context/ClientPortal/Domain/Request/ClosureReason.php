<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/** Why a request was closed without answer. */
enum ClosureReason: string
{
    case LINK_REVOKED           = 'link_revoked';
    case CLIENT_ARCHIVED        = 'client_archived';
    case ACCOUNT_DELETED        = 'account_deleted';
    case PRESCRIPTION_CANCELLED = 'prescription_cancelled';
    case PET_ARCHIVED           = 'pet_archived';
    case PET_DECEASED           = 'pet_deceased';
    // Questionnaires only: their appointment no longer takes place
    case APPOINTMENT_CANCELLED = 'appointment_cancelled';
    case APPOINTMENT_DECLINED  = 'appointment_declined';
}
