<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Application\Query;

use App\Translation\Application\Query\SearchTranslations\SearchTranslations;
use App\Translation\Application\Query\SearchTranslations\SearchTranslationsHandler;
use App\Translation\Application\Query\SearchTranslations\TranslationSearchResult;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use PHPUnit\Framework\TestCase;

final class SearchTranslationsHandlerTest extends TestCase
{
    public function testMapsResult(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::once())
            ->method('search')
            ->willReturn([
                'items' => [
                    [
                        'scope'       => 'clinic',
                        'locale'      => 'fr-FR',
                        'domain'      => 'messages',
                        'key'         => 'hello',
                        'value'       => 'Bonjour',
                        'description' => 'desc',
                        'createdAt'   => new \DateTimeImmutable('2024-01-01T10:00:00Z'),
                        'createdBy'   => 'actor1',
                        'updatedAt'   => new \DateTimeImmutable('2024-01-02T10:00:00Z'),
                        'updatedBy'   => 'actor2',
                    ],
                ],
                'total' => 1,
            ])
        ;

        $handler = new SearchTranslationsHandler($repo);

        /** @var TranslationSearchResult $result */
        $result = $handler(new SearchTranslations(scope: 'clinic', locale: 'fr-FR', domain: 'messages'));

        self::assertSame(1, $result->total);
        self::assertCount(1, $result->items);
        $item = $result->items[0];
        self::assertSame('clinic', $item->scope);
        self::assertSame('fr-FR', $item->locale);
        self::assertSame('messages', $item->domain);
        self::assertSame('hello', $item->key);
        self::assertSame('Bonjour', $item->value);
        self::assertSame('desc', $item->description);
        self::assertSame('actor1', $item->createdBy);
        self::assertSame('actor2', $item->updatedBy);
    }
}
