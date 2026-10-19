<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\VerifyClientTaxProfile;

use App\Context\Client\Application\Command\RecordTaxProfileVerification\RecordTaxProfileVerification;
use App\Context\Client\Application\Port\ClientReadRepositoryInterface;
use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Application\Port\VatNumberVerifierInterface;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientStatus;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs on the external worker, outside any transaction: calls the registry
 * and VIES (each outage becomes an "unavailable" outcome, retried by the
 * nightly run), then records the answers through a short transactional command.
 */
#[AsMessageHandler(bus: 'messenger.bus.external_api')]
final readonly class VerifyClientTaxProfileHandler
{
    public function __construct(
        private ClientReadRepositoryInterface $clients,
        private CompanyRegistryInterface $registry,
        private VatNumberVerifierInterface $vatVerifier,
        private CommandBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VerifyClientTaxProfile $command): void
    {
        $client = $this->clients->findById(ClinicId::fromString($command->clinicId), ClientId::fromString($command->clientId));

        $verifiable = null !== $client
            && ClientStatus::ACTIVE->value === $client->status
            && ClientTaxStatus::FRENCH_BUSINESS->value === $client->taxStatus;

        if (!$verifiable || null === $client->siren) {
            return;
        }

        [$companyOutcome, $registryName] = $this->checkCompany($client->siren);
        $companyCheckedAt                = $this->clock->now();
        $vatOutcome                      = $this->checkVat($client->vatNumber);
        $vatCheckedAt                    = $this->clock->now();

        $this->commandBus->dispatch(new RecordTaxProfileVerification(
            clinicId: $command->clinicId,
            clientId: $command->clientId,
            checkedSiren: $client->siren,
            checkedVatNumber: $client->vatNumber,
            companyOutcome: $companyOutcome->value,
            registryName: $registryName,
            companyCheckedAt: $companyCheckedAt->format(\DATE_ATOM),
            vatOutcome: $vatOutcome->value,
            vatCheckedAt: $vatCheckedAt->format(\DATE_ATOM),
        ));
    }

    /**
     * @return array{CompanyCheckOutcome, string|null}
     */
    private function checkCompany(string $siren): array
    {
        try {
            $record = $this->registry->findBySiren($siren);
        } catch (ExternalServiceUnavailable) {
            return [CompanyCheckOutcome::UNAVAILABLE, null];
        }

        return [$this->companyOutcome($record), $record?->legalName];
    }

    private function companyOutcome(?CompanyRecord $record): CompanyCheckOutcome
    {
        return match (true) {
            null === $record        => CompanyCheckOutcome::NOT_FOUND,
            $record->isConfidential => CompanyCheckOutcome::CONFIDENTIAL,
            !$record->isActive      => CompanyCheckOutcome::CEASED,
            default                 => CompanyCheckOutcome::ACTIVE,
        };
    }

    private function checkVat(?string $vatNumber): VatCheckOutcome
    {
        if (null === $vatNumber) {
            return VatCheckOutcome::NOT_APPLICABLE;
        }

        try {
            return $this->vatVerifier->verify($vatNumber)->isValid ? VatCheckOutcome::VALID : VatCheckOutcome::INVALID;
        } catch (ExternalServiceUnavailable) {
            return VatCheckOutcome::UNAVAILABLE;
        }
    }
}
