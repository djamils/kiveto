<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Staff\ValueObject;

enum ClinicMemberRole: string
{
    case MANAGER              = 'MANAGER';
    case VETERINARY           = 'VETERINARY';
    case VETERINARY_ASSISTANT = 'VETERINARY_ASSISTANT';
    case RECEPTIONIST         = 'RECEPTIONIST';
}
