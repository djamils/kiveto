<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

/** What an owner may ask the clinic to change on a pet's file. */
enum ChangeField: string
{
    case NAME          = 'name';
    case WEIGHT        = 'weight';
    case STERILIZATION = 'sterilization';
    case CHIP_NUMBER   = 'chip_number';
    case OTHER         = 'other';
}
