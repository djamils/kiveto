<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum ContactMethodType: string
{
    case PHONE = 'phone';
    case EMAIL = 'email';
}
