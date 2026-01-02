<?php

declare(strict_types=1);

namespace App\Translation\Application\Command\BulkUpsertTranslations;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventMessageFactory;
use App\Shared\Domain\Time\ClockInterface;
use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationCatalogRepository;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BulkUpsertTranslationsHandler
{
    public function __construct(
        private TranslationCatalogRepository $catalogs,
        private CatalogCacheInterface $cache,
        private ClockInterface $clock,
        private EventBusInterface $eventBus,
        private DomainEventMessageFactory $eventMessageFactory,
    ) {
    }

    public function __invoke(BulkUpsertTranslations $command): void
    {
        $grouped = $this->groupByCatalog($command->entries);
        $actorId = null !== $command->actorId ? ActorId::fromString($command->actorId) : null;

        foreach ($grouped as $catalogId => $entries) {
            $catalogIdVo = $this->parseCatalogId($catalogId);
            $catalog     = $this->catalogs->find($catalogIdVo) ?? TranslationCatalog::createEmpty($catalogIdVo);

            foreach ($entries as $entry) {
                $catalog->upsert(
                    TranslationKey::fromString($entry['key']),
                    TranslationText::fromString($entry['value']),
                    $this->clock->now(),
                    $actorId,
                    $entry['description'] ?? null,
                );
            }

            $this->catalogs->save($catalog);
            $this->cache->delete($catalogIdVo);
            $this->publishDomainEvents($catalog);
        }
    }

    /**
     * @param list<array{scope: string, locale: string, domain: string, key: string, value: string, description?: string|null}> $entries
     *
     * @return array<string, list<array{scope: string, locale: string, domain: string, key: string, value: string, description?: string|null}>>
     */
    private function groupByCatalog(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $id = \sprintf('%s|%s|%s', $entry['scope'], $entry['locale'], $entry['domain']);
            $grouped[$id] ??= [];
            $grouped[$id][] = $entry;
        }

        return $grouped;
    }

    private function parseCatalogId(string $serialized): TranslationCatalogId
    {
        [$scope, $locale, $domain] = explode('|', $serialized, 3);

        return TranslationCatalogId::fromStrings($scope, $locale, $domain);
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
