<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Vat;

use App\Context\Client\Application\Port\VatVerificationResult;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Infrastructure\ExternalApi\ExternalApiResponse;

/**
 * Reads a VIES REST answer: `valid`, or an error code in `userError` or
 * `errorWrappers[].error`. An outage code is never an "invalid" answer.
 */
final readonly class ViesErrorMapper
{
    private const array OUTAGE_CODES = [
        'MS_UNAVAILABLE',
        'SERVICE_UNAVAILABLE',
        'TIMEOUT',
        'GLOBAL_MAX_CONCURRENT_REQ',
        'GLOBAL_MAX_CONCURRENT_REQ_TIME',
        'MS_MAX_CONCURRENT_REQ',
        'MS_MAX_CONCURRENT_REQ_TIME',
    ];

    // The number cannot exist (bad format for the member state).
    private const string INVALID_INPUT = 'INVALID_INPUT';

    /**
     * @throws ExternalServiceUnavailable
     */
    public function map(ExternalApiResponse $response): VatVerificationResult
    {
        $data = $response->data ?? [];
        $code = $this->errorCode($data);

        if (null !== $code && \in_array($code, self::OUTAGE_CODES, true)) {
            throw ExternalServiceUnavailable::refused(ViesVatNumberVerifier::SERVICE, $code);
        }

        if (self::INVALID_INPUT === $code) {
            return new VatVerificationResult(false);
        }

        if ($response->isSuccessful() && \is_bool($data['valid'] ?? null)) {
            return new VatVerificationResult($data['valid']);
        }

        throw ExternalServiceUnavailable::refused(
            ViesVatNumberVerifier::SERVICE,
            $code ?? ($response->isSuccessful() ? 'unreadable answer' : \sprintf('HTTP %d', $response->status)),
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
