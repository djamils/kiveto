<?php

declare(strict_types=1);

namespace App\Context\Billing\Infrastructure\Adapter\Clinic;

use App\Context\Billing\Application\Port\SellerIdentityProviderInterface;
use App\Context\Billing\Domain\ValueObject\SellerIdentity;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\ClinicSellerIdentityDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSellerIdentity\GetClinicSellerIdentity;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\InvalidFrenchVatNumberException;
use App\Shared\Domain\ValueObject\InvalidSirenException;
use App\Shared\Domain\ValueObject\Siren;

/**
 * The clinic as a seller, read from the Clinic context's letterhead and
 * billing settings. A clinic it does not know comes back empty.
 */
final readonly class QueryBusSellerIdentityProvider implements SellerIdentityProviderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function forClinic(string $clinicId): SellerIdentity
    {
        $dto = $this->queryBus->ask(new GetClinicSellerIdentity($clinicId));

        if (!$dto instanceof ClinicSellerIdentityDto) {
            return new SellerIdentity('', null, null, [], null, null, 'FR', null, null, null, null, true);
        }

        [$siren, $vat] = $this->identifiers($dto->siren, $dto->vatNumber);

        return new SellerIdentity(
            legalName: $dto->legalName,
            siren: $siren,
            vatNumber: $vat,
            streetLines: $dto->streetLines,
            postalCode: $dto->postalCode,
            city: $dto->city,
            countryCode: $dto->countryCode,
            legalSentence: $dto->legalSentence,
            professionalPaymentTermsDays: $dto->professionalPaymentTermsDays,
            iban: $dto->iban,
            bic: $dto->bic,
            numberingIsUnique: $dto->numberingIsUnique,
        );
    }

    /**
     * @return array{?Siren, ?FrenchVatNumber}
     */
    private function identifiers(?string $siren, ?string $vat): array
    {
        try {
            $siren = null !== $siren ? Siren::fromInput($siren) : null;
        } catch (InvalidSirenException) {
            return [null, null];
        }

        try {
            return [$siren, null !== $siren && null !== $vat ? FrenchVatNumber::fromInput($vat, $siren) : null];
        } catch (InvalidFrenchVatNumberException) {
            return [$siren, null];
        }
    }
}
