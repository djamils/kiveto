<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\VerifyVatNumber;

enum VatNumberCheck: string
{
    case VALID = 'valid';

    case INVALID = 'invalid';

    /** VIES did not answer: nothing is known. */
    case UNAVAILABLE = 'unavailable';
}
