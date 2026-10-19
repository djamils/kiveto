<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Verification;

use App\Context\Client\Application\Command\VerifyClientTaxProfile\VerifyClientTaxProfile;
use App\Context\Client\Domain\Event\ClientTaxIdentifiersChanged;
use App\Context\Client\Domain\ValueObject\ClientTaxStatus;
use App\Shared\Application\Bus\ExternalApiCommandBusInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * New identifiers of a French business are verified in the background. The
 * aggregate already marked the request; the message is written in the
 * business transaction (implicit outbox).
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class OnClientTaxIdentifiersChangedVerify
{
    public function __construct(
        private ExternalApiCommandBusInterface $externalApiCommandBus,
    ) {
    }

    public function __invoke(ClientTaxIdentifiersChanged $event): void
    {
        $payload = $event->payload();
        \assert(\is_string($payload['clientId']));
        \assert(\is_string($payload['clinicId']));

        if (ClientTaxStatus::FRENCH_BUSINESS->value !== $payload['taxStatus'] || !\is_string($payload['siren'])) {
            return;
        }

        $this->externalApiCommandBus->send(new VerifyClientTaxProfile($payload['clinicId'], $payload['clientId']));
    }
}
