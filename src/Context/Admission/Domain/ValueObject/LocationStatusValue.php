<?php

declare(strict_types=1);

namespace App\Context\Admission\Domain\ValueObject;

enum LocationStatusValue: string
{
    case InWaitingRoom      = 'in_waiting_room';
    case InConsultationRoom = 'in_consultation_room';
    case InHospitalization  = 'in_hospitalization';
    case InSurgery          = 'in_surgery';
    case AtReception        = 'at_reception';
}
