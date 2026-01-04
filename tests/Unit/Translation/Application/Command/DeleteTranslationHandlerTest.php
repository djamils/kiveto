<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Application\Command;

use App\Shared\Domain\Time\ClockInterface;
use App\Translation\Application\Command\DeleteTranslation\DeleteTranslation;
use App\Translation\Application\Command\DeleteTranslation\DeleteTranslationHandler;
use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationCatalogRepository;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;
use PHPUnit\Framework\TestCase;

final class DeleteTranslationHandlerTest extends TestCase
{
    public function testDeleteRemovesEntryInvalidatesAndPublishes(): void
    {
        $catalogId = TranslationCatalogId::fromStrings('portal', 'en_GB', 'messages');
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

        $handler = new DeleteTranslationHandler($repo, $cache, $clock);

        $handler(new DeleteTranslation('portal', 'en_GB', 'messages', 'cta', 'actor-x'));
    }
}
