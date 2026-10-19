<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\RecordTaxProfileVerification;

use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\TaxProfile\CompanyCheck;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\TaxProfileVerification;
use App\Context\Client\Domain\TaxProfile\VatCheck;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A concurrent commit on the client fails the flush (optimistic lock): the
 * exception propagates so that the verify message is retried on fresh data.
 */
#[AsMessageHandler]
final readonly class RecordTaxProfileVerificationHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RecordTaxProfileVerification $command): void
    {
        $client = $this->clientRepository->find(ClinicId::fromString($command->clinicId), ClientId::fromString($command->clientId));

        if (null === $client) {
            return;
        }

        $changed = $client->recordTaxProfileVerification(
            $command->checkedSiren,
            $command->checkedVatNumber,
            TaxProfileVerification::of(
                CompanyCheck::checked(
                    CompanyCheckOutcome::from($command->companyOutcome),
                    $command->registryName,
                    new \DateTimeImmutable($command->companyCheckedAt),
                ),
                VatCheck::checked(VatCheckOutcome::from($command->vatOutcome), new \DateTimeImmutable($command->vatCheckedAt)),
            ),
        );

        // A late answer changes nothing: nothing to save.
        if (!$changed) {
            return;
        }

        $this->clientRepository->save($client);
        $this->domainEventPublisher->publish($client);
    }
}
