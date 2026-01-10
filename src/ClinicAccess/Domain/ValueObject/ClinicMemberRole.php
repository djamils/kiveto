<?php

declare(strict_types=1);

namespace App\ClinicAccess\Domain\ValueObject;

enum ClinicMemberRole: string
{
    case CLINIC_ADMIN         = 'CLINIC_ADMIN';
    case VETERINARY           = 'VETERINARY';
    case ASSISTANT_VETERINARY = 'ASSISTANT_VETERINARY';
}
