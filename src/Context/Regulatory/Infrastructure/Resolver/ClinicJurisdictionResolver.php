<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Resolver;

use App\Context\Regulatory\Domain\JurisdictionResolverInterface;
use Doctrine\DBAL\Connection;

final readonly class ClinicJurisdictionResolver implements JurisdictionResolverInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function resolveForClinic(string $clinicId): string
    {
        $countryCode = $this->connection->fetchOne(
            'SELECT country_code FROM clinic__clinics WHERE id = :id',
            ['id' => $clinicId],
        );

        if (!\is_string($countryCode) || '' === $countryCode) {
            throw new \RuntimeException(\sprintf('Clinic "%s" not found or has no country_code.', $clinicId));
        }

        return $countryCode;
    }
}
