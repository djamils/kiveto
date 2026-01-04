<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\DeleteTranslation;

use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\DependencyInjection\DomainEventPublisherAware;
use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationCatalogRepository;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationKey;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteTranslationHandler
{
    use DomainEventPublisherAware;

    public function __construct(
        private readonly TranslationCatalogRepository $catalogs,
        private readonly CatalogCacheInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(DeleteTranslation $command): void
    {
        $now = $this->clock->now();

        $catalogId = TranslationCatalogId::fromStrings($command->scope, $command->locale, $command->domain);
        $catalog   = $this->catalogs->find($catalogId) ?? TranslationCatalog::createEmpty($catalogId);

        $catalog->remove(
            TranslationKey::fromString($command->key),
            null !== $command->actorId ? ActorId::fromString($command->actorId) : null,
            $now,
        );

        $this->catalogs->save($catalog);
        $this->cache->delete($catalogId);

        $this->eventPublisher->publishFrom($catalog, $now);
    }
}
