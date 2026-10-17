<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\CreateClient;

use App\Context\Client\Domain\Exception\InvalidClientTaxProfileException;
use App\Context\Client\Domain\ValueObject\ClientTaxProfile;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;

/**
 * Turns the typed tax profile into the domain value object, for the creation
 * and the update commands alike. Invalid input surfaces as
 * InvalidClientTaxProfileException, InvalidSirenException or
 * InvalidFrenchVatNumberException.
 */
final readonly class ClientTaxProfileFactory
{
    public static function fromDto(ClientTaxProfileDto $dto): ClientTaxProfile
    {
        $status = ClientTaxStatus::from($dto->status);

        if (ClientTaxStatus::INDIVIDUAL === $status) {
            // SIREN and VAT number do not exist for an individual.
            return ClientTaxProfile::individual($dto->legalName);
        }

        $sirenInput = trim((string) $dto->siren);

        if ('' === $sirenInput) {
            throw new InvalidClientTaxProfileException(InvalidClientTaxProfileException::SIREN_REQUIRED);
        }

        $siren    = Siren::fromInput($sirenInput);
        $vatInput = trim((string) $dto->vatNumber);

        return ClientTaxProfile::frenchBusiness(
            (string) $dto->legalName,
            $siren,
            '' === $vatInput ? null : FrenchVatNumber::fromInput($vatInput, $siren),
        );
    }
}
