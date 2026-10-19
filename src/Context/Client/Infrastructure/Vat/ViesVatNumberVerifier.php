<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Vat;

use App\Context\Client\Application\Port\VatNumberVerifierInterface;
use App\Context\Client\Application\Port\VatVerificationResult;
use App\Shared\Infrastructure\ExternalApi\ExternalApiUserAgent;
use App\Shared\Infrastructure\ExternalApi\RetryAfterAwareClient;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * The European Commission's VIES REST service, through the `vies.client`
 * scoped client, paced by the worker budget (VIES refuses bursts).
 */
final readonly class ViesVatNumberVerifier implements VatNumberVerifierInterface
{
    public const string SERVICE = 'vies';

    public function __construct(
        private RetryAfterAwareClient $client,
        private ViesErrorMapper $errors,
        private RateLimiterFactoryInterface $limiter,
        ExternalApiUserAgent $userAgent,
    ) {
        // Built only on demand (CLIENT_VAT_VERIFIER=vies): fails here without a contact.
        $userAgent->withContact(self::SERVICE);
    }

    public function verify(string $vatNumber): VatVerificationResult
    {
        $this->limiter->create('global')->reserve()->wait();

        $response = $this->client->request('POST', 'check-vat-number', ['json' => [
            'countryCode' => substr($vatNumber, 0, 2),
            'vatNumber'   => substr($vatNumber, 2),
        ]]);

        return $this->errors->map($response);
    }
}
