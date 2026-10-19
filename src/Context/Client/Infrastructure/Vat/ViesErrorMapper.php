<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Vat;

use App\Context\Client\Application\Port\VatVerificationResult;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Infrastructure\ExternalApi\ExternalApiResponse;

/**
 * Reads a VIES REST answer: `valid`, or an error code in `userError` or
 * `errorWrappers[].error`. A number is read as invalid only when VIES says
 * `valid: false` with no error code: any code (outage, IP_BLOCKED, an unknown
 * one…) is an outage, so that a client is never wrongly accused.
 */
final readonly class ViesErrorMapper
{
    /**
     * @throws ExternalServiceUnavailable
     */
    public function map(ExternalApiResponse $response): VatVerificationResult
    {
        $data = $response->data ?? [];
        $code = $this->errorCode($data);

        if (null !== $code) {
            throw ExternalServiceUnavailable::refused(ViesVatNumberVerifier::SERVICE, $code);
        }

        if ($response->isSuccessful() && \is_bool($data['valid'] ?? null)) {
            return new VatVerificationResult($data['valid']);
        }

        throw ExternalServiceUnavailable::refused(
            ViesVatNumberVerifier::SERVICE,
            $response->isSuccessful() ? 'unreadable answer' : \sprintf('HTTP %d', $response->status),
        );
    }

    /**
     * @param array<mixed> $data
     */
    private function errorCode(array $data): ?string
    {
        $userError = $data['userError'] ?? null;

        if (\is_string($userError) && !\in_array($userError, ['VALID', 'INVALID'], true)) {
            return $userError;
        }

        foreach (\is_array($data['errorWrappers'] ?? null) ? $data['errorWrappers'] : [] as $wrapper) {
            if (\is_array($wrapper) && \is_string($wrapper['error'] ?? null)) {
                return $wrapper['error'];
            }
        }

        return null;
    }
}
