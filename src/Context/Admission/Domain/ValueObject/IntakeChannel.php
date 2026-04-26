<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

enum IntakeChannel: string
{
    case AppointmentBooked       = 'appointment_booked';
    case WalkIn                  = 'walk_in';
    case Emergency               = 'emergency';
    case EmergencyByThirdParty   = 'emergency_by_third_party';
    case EmergencyByAuthority    = 'emergency_by_authority';
    case EmergencyByAssociation  = 'emergency_by_association';
    case EmergencyByMunicipality = 'emergency_by_municipality';
    case TransferFromOtherClinic = 'transfer_from_other_clinic';
}
