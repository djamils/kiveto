<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\DeleteTranslation;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventMessageFactory;
use App\Shared\Domain\Time\ClockInterface;
use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationCatalogRepository;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationKey;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteTranslationHandler
{
    public function __construct(
        private TranslationCatalogRepository $catalogs,
        private CatalogCacheInterface $cache,
        private ClockInterface $clock,
        private EventBusInterface $eventBus,
        private DomainEventMessageFactory $eventMessageFactory,
    ) {
    }

    public function __invoke(DeleteTranslation $command): void
    {
        $catalogId = TranslationCatalogId::fromStrings($command->scope, $command->locale, $command->domain);
        $catalog   = $this->catalogs->find($catalogId) ?? TranslationCatalog::createEmpty($catalogId);

        $catalog->remove(
            TranslationKey::fromString($command->key),
            null !== $command->actorId ? ActorId::fromString($command->actorId) : null,
            $this->clock->now(),
        );

        $this->catalogs->save($catalog);
        $this->cache->delete($catalogId);

        $this->publishDomainEvents($catalog);
    }

    private function publishDomainEvents(TranslationCatalog $catalog): void
    {
        $messages = [];

        foreach ($catalog->pullDomainEvents() as $event) {
            $messages[] = $this->eventMessageFactory->wrap($event);
        }

        if ([] !== $messages) {
            $this->eventBus->publish(...$messages);
        }
    }
}
