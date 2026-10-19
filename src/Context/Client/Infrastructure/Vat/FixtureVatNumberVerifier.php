<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Vat;

use App\Context\Client\Application\Port\VatNumberVerifierInterface;
use App\Context\Client\Application\Port\VatVerificationResult;
use App\Context\Client\Infrastructure\Registry\FixtureCompanyRegistry;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

/**
 * In-memory VIES for tests, CI and dev (CLIENT_VAT_VERIFIER=fixture): the demo
 * numbers are valid, the VAT number of the registry's outage SIREN is an
 * outage, any other number is invalid.
 */
final class FixtureVatNumberVerifier implements VatNumberVerifierInterface
{
    public const string SERVICE = 'vies';

    // FR76392107454 (Haras du Mont Ventoux) is left out on purpose: the demo's rejected number.
    private const array VALID = ['FR31523456788', 'FR14441569738'];

    public function verify(string $vatNumber): VatVerificationResult
    {
        if (str_ends_with($vatNumber, FixtureCompanyRegistry::UNAVAILABLE_SIREN)) {
            throw ExternalServiceUnavailable::refused(self::SERVICE, 'MS_UNAVAILABLE');
        }

        return new VatVerificationResult(\in_array($vatNumber, self::VALID, true));
    }
}
