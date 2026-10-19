<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

/**
 * VIES's answer for a VAT number. The name VIES returns is ignored: the
 * registry's official name is the reference.
 */
final readonly class VatVerificationResult
{
    public function __construct(public bool $isValid)
    {
    }
}
