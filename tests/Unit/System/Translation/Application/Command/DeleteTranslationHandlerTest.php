<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Translation\Application\Command;

use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Translation\Application\Command\DeleteTranslation\DeleteTranslation;
use App\System\Translation\Application\Command\DeleteTranslation\DeleteTranslationHandler;
use App\System\Translation\Application\Port\CatalogCacheInterface;
use App\System\Translation\Domain\Repository\TranslationCatalogRepository;
use App\System\Translation\Domain\TranslationCatalog;
use App\System\Translation\Domain\ValueObject\TranslationCatalogId;
use App\System\Translation\Domain\ValueObject\TranslationKey;
use App\System\Translation\Domain\ValueObject\TranslationText;
use PHPUnit\Framework\TestCase;

final class DeleteTranslationHandlerTest extends TestCase
{
    public function testDeleteRemovesEntryInvalidatesAndPublishes(): void
    {
        $catalogId = TranslationCatalogId::fromStrings('portal', 'en-GB', 'messages');
        $catalog   = TranslationCatalog::createEmpty($catalogId);
        $now       = new \DateTimeImmutable('2024-01-01T10:00:00Z');
        $catalog->upsert(
            TranslationKey::fromString('cta'),
            TranslationText::fromString('Click'),
            $now,
            null,
            null,
        );

        $repo = $this->createMock(TranslationCatalogRepository::class);
        $repo->expects(self::once())
            ->method('find')
            ->with($catalogId)
            ->willReturn($catalog)
        ;
        $repo->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(TranslationCatalog::class))
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::once())
            ->method('delete')
            ->with($catalogId)
        ;

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-02T10:00:00Z'));

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())
            ->method('publish')
            ->with([], self::isInstanceOf(DomainEventInterface::class))
        ;

        $handler = new DeleteTranslationHandler(
            $repo,
            $cache,
            $clock,
            new DomainEventPublisher($eventBus),
        );

        $handler(new DeleteTranslation('portal', 'en-GB', 'messages', 'cta', 'actor-x'));
    }
}
