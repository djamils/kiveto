<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain;

use App\Translation\Domain\Event\TranslationDeleted;
use App\Translation\Domain\Event\TranslationUpserted;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationDomain;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;
use PHPUnit\Framework\TestCase;

final class TranslationCatalogTest extends TestCase
{
    public function testUpsertCreatesAndUpdatesEntries(): void
    {
        $id = new TranslationCatalogId(
            AppScope::CLINIC,
            Locale::fromString('fr_FR'),
            TranslationDomain::fromString('messages')
        );

        $catalog = TranslationCatalog::createEmpty($id);
        $now     = new \DateTimeImmutable('2024-01-01T12:00:00Z');
        $later   = new \DateTimeImmutable('2024-01-02T12:00:00Z');
        $actor   = ActorId::fromString('018d3dcf-0000-7000-8000-000000000001');
        $key     = TranslationKey::fromString('hello');
        $text    = TranslationText::fromString('Hello');
        $newText = TranslationText::fromString('Bonjour');

        $catalog->upsert($key, $text, $now, $actor, 'Greeting');

        $entries = $catalog->entries();
        self::assertCount(1, $entries);
        self::assertSame('Greeting', $entries[0]->description());
        self::assertSame($actor, $entries[0]->createdBy());
        self::assertSame($now, $entries[0]->createdAt());

        $events = $catalog->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TranslationUpserted::class, $events[0]);

        $catalog->upsert($key, $newText, $later, null, 'Salut');

        $entries = $catalog->entries();
        self::assertCount(1, $entries);
        self::assertSame('Salut', $entries[0]->description());
        self::assertSame($now, $entries[0]->createdAt());
        self::assertSame($actor, $entries[0]->createdBy());
        self::assertSame($later, $entries[0]->updatedAt());
        self::assertNull($entries[0]->updatedBy());

        $events = $catalog->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TranslationUpserted::class, $events[0]);
    }

    public function testRemoveIsIdempotentAndRecordsEvent(): void
    {
        $id = new TranslationCatalogId(
            AppScope::PORTAL,
            Locale::fromString('en_GB'),
            TranslationDomain::fromString('messages')
        );

        $catalog = TranslationCatalog::createEmpty($id);
        $now     = new \DateTimeImmutable('2024-01-03T12:00:00Z');
        $actor   = ActorId::fromString('018d3dcf-0000-7000-8000-000000000010');
        $key     = TranslationKey::fromString('cta');

        $catalog->remove($key, $actor, $now);
        self::assertCount(0, $catalog->entries());
        self::assertCount(0, $catalog->pullDomainEvents());

        $catalog->upsert($key, TranslationText::fromString('Click'), $now, $actor, null);
        $events = $catalog->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TranslationUpserted::class, $events[0]);

        $catalog->remove($key, $actor, $now);
        self::assertCount(0, $catalog->entries());

        $events = $catalog->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(TranslationDeleted::class, $events[0]);
    }

    public function testReconstituteAndHelpers(): void
    {
        $id = new TranslationCatalogId(
            AppScope::SHARED,
            Locale::fromString('en_GB'),
            TranslationDomain::fromString('common')
        );

        $entry = new \App\Translation\Domain\TranslationEntry(
            TranslationKey::fromString('foo'),
            TranslationText::fromString('bar'),
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            new \DateTimeImmutable('2024-01-01T10:00:00Z'),
            null,
            null,
            null,
        );

        $catalog = TranslationCatalog::reconstitute($id, [$entry]);

        self::assertSame($id, $catalog->id());
        self::assertTrue($catalog->hasKey(TranslationKey::fromString('foo')));
        self::assertFalse($catalog->hasKey(TranslationKey::fromString('bar')));
        self::assertSame(['foo' => 'bar'], $catalog->toKeyValue());

        $catalog->remove(TranslationKey::fromString('foo'), null, new \DateTimeImmutable('2024-01-02T10:00:00Z'));
        $removed = $catalog->removedKeys();
        self::assertCount(1, $removed);
        self::assertSame('foo', $removed[0]->toString());
    }
}
