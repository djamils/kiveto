<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Jurisdiction\France;

final class SireNumberValidator
{
    public function isValid(string $value): bool
    {
        return 1 === preg_match('/^\d{15}$/', $value);
    }
}
