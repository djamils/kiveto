<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

enum ClosureReason: string
{
    case ConsultationCompleted    = 'consultation_completed';
    case ReleasedToOwner          = 'released_to_owner';
    case HandedToAuthority        = 'handed_to_authority';
    case HandedToAssociation      = 'handed_to_association';
    case TransferredToOtherClinic = 'transferred_to_other_clinic';
    case AnimalDeceased           = 'animal_deceased';
    case Euthanized               = 'euthanized';
    case AdoptedByStaff           = 'adopted_by_staff';
    case LeftAgainstAdvice        = 'left_against_advice';
    case AdministrativeError      = 'administrative_error';
}
