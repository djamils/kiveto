<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Messenger\Probe;

use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Tests\Integration\System\Messenger\TransactionalityProbeIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.command')]
final readonly class OutboxProbeCommandHandler
{
    public function __construct(
        private ClientRepositoryInterface $clients,
        private IntegrationEventPublisher $integrationEventPublisher,
    ) {
    }

    public function __invoke(OutboxProbeCommand $command): void
    {
        $client = Client::create(
            ClientId::fromString($command->clientId),
            ClinicId::fromString($command->clinicId),
            new ClientIdentity('Outbox', 'Probe'),
            [ContactMethod::email(EmailAddress::fromString('outbox.probe@example.test'), ContactLabel::OTHER, true)],
            new \DateTimeImmutable('2026-10-17 10:00:00'),
        );

        // Flushes the client row inside the command-bus transaction.
        $this->clients->save($client);

        $this->integrationEventPublisher->publish(new TransactionalityProbeIntegrationEvent());

        if (OutboxProbeCommand::FAIL_AFTER_PUBLISH === $command->failAt) {
            throw new \RuntimeException('Outbox probe failure after publish.');
        }
    }
}
