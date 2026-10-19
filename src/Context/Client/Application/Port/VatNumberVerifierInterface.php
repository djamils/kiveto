<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

/**
 * The European VAT number check (VIES).
 *
 * An outage is never an "invalid" answer: when VIES or the member state's
 * service cannot answer, an ExternalServiceUnavailable is thrown.
 */
interface VatNumberVerifierInterface
{
    /**
     * @param string $vatNumber full number with its country prefix, e.g. FR31523456788
     *
     * @throws ExternalServiceUnavailable
     */
    public function verify(string $vatNumber): VatVerificationResult;
}
