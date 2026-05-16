<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\ManuallyMarkMarketingAuthorizationWithdrawn;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Domain\Exception\UnknownMarketingAuthorizationException;
use App\System\PharmaceuticalRegistry\Domain\Repository\MarketingAuthorizationRepositoryInterface;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\MarketingAuthorizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ManuallyMarkMarketingAuthorizationWithdrawnHandler
{
    public function __construct(
        private MarketingAuthorizationRepositoryInterface $authorizationRepository,
        private DomainEventPublisher $domainEventPublisher,
        private IntegrationEventPublisher $integrationEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ManuallyMarkMarketingAuthorizationWithdrawn $command): void
    {
        $id = MarketingAuthorizationId::fromString($command->marketingAuthorizationId);
        $ma = $this->authorizationRepository->findById($id);

        if (null === $ma) {
            throw new UnknownMarketingAuthorizationException($command->marketingAuthorizationId);
        }

        $ma->withdraw($this->clock->now());
        $this->authorizationRepository->save($ma);

        $this->domainEventPublisher->publish($ma);

        $integrationEvents = $ma->pullIntegrationEvents();

        if ([] !== $integrationEvents) {
            $this->integrationEventPublisher->publish(...$integrationEvents);
        }
    }
}
