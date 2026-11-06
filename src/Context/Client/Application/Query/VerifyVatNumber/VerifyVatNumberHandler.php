<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\VerifyVatNumber;

use App\Context\Client\Application\Port\VatNumberVerifierInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VerifyVatNumberHandler
{
    public function __construct(
        private VatNumberVerifierInterface $verifier,
    ) {
    }

    public function __invoke(VerifyVatNumber $query): VatNumberCheck
    {
        $number = strtoupper((string) preg_replace('/\s+/', '', $query->vatNumber));

        if (1 !== preg_match('/^[A-Z]{2}[0-9A-Z]{2,13}$/', $number)) {
            return VatNumberCheck::INVALID;
        }

        try {
            return $this->verifier->verify($number)->isValid ? VatNumberCheck::VALID : VatNumberCheck::INVALID;
        } catch (ExternalServiceUnavailable) {
            return VatNumberCheck::UNAVAILABLE;
        }
    }
}
