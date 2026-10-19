<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\RequestTaxProfileVerification;

use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Domain\Exception\ClientNotFoundException;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\TaxProfile\VerificationRequestOutcome;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Marks the request and queues the verification in the same transaction
 * (implicit outbox), unless one is still awaited.
 */
#[AsMessageHandler]
final readonly class RequestTaxProfileVerificationHandler
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private ExternalApiCommandBusInterface $externalApiCommandBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws ClientNotFoundException for an unknown client or one of another clinic
     */
    public function __invoke(RequestTaxProfileVerification $command): VerificationRequestOutcome
    {
        $client = $this->clientRepository->get(ClinicId::fromString($command->clinicId), ClientId::fromString($command->clientId));

        $outcome = $client->requestTaxProfileVerification($this->clock->now());

        if (VerificationRequestOutcome::QUEUED !== $outcome) {
            return $outcome;
        }

        $this->clientRepository->save($client);
        $this->externalApiCommandBus->send(new VerifyClientTaxProfile($command->clinicId, $command->clientId));

        return $outcome;
    }
}
