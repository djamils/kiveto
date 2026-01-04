<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Application\Command;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventMessage;
use App\Shared\Application\Event\DomainEventMessageFactory;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Translation\Application\Command\UpsertTranslation\UpsertTranslation;
use App\Translation\Application\Command\UpsertTranslation\UpsertTranslationHandler;
use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationCatalogRepository;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use PHPUnit\Framework\TestCase;

final class UpsertTranslationHandlerTest extends TestCase
{
    public function testUpsertPersistsInvalidatesAndPublishesEvent(): void
    {
        $catalogId = TranslationCatalogId::fromStrings('clinic', 'fr_FR', 'messages');

        $repo = $this->createMock(TranslationCatalogRepository::class);
        $repo->expects(self::once())
            ->method('find')
            ->with($catalogId)
            ->willReturn(null)
        ;
        $repo->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (TranslationCatalog $catalog): bool {
                return $catalog->hasKey(
                    \App\Translation\Domain\ValueObject\TranslationKey::fromString('hello'),
                );
            }))
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::once())
            ->method('delete')
            ->with($catalogId)
        ;

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01T12:00:00Z'));

        $handler = new UpsertTranslationHandler($repo, $cache, $clock);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with(self::isInstanceOf(DomainEventMessage::class))
        ;

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn('test-uuid');
        $messageFactory = new DomainEventMessageFactory($uuidGenerator);
        $eventPublisher = new DomainEventPublisher($eventBus, $messageFactory);
        $handler->setDomainEventPublisher($eventPublisher);

        $handler(new UpsertTranslation('clinic', 'fr_FR', 'messages', 'hello', 'Hello', null, 'actor-1'));
    }
}
