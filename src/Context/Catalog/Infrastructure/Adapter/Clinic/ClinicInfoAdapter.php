<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Adapter\Clinic;

use App\Context\Catalog\Application\Port\ClinicInfoProviderInterface;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;

final readonly class ClinicInfoAdapter implements ClinicInfoProviderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function getCountryCode(ClinicId $clinicId): CountryCode
    {
        return CountryCode::fromString($this->getDto($clinicId)->countryCode);
    }

    public function getCurrencyCode(ClinicId $clinicId): CurrencyCode
    {
        return CurrencyCode::fromString($this->getDto($clinicId)->currencyCode);
    }

    private function getDto(ClinicId $clinicId): ClinicDto
    {
        $dto = $this->queryBus->ask(new GetClinic($clinicId->toString()));

        if (!$dto instanceof ClinicDto) {
            throw new \RuntimeException(\sprintf('Clinic "%s" not found.', $clinicId->toString()));
        }

        return $dto;
    }
}
