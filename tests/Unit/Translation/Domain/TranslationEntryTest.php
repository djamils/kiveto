<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain;

use App\Translation\Domain\TranslationEntry;
use App\Translation\Domain\ValueObject\ActorId;
use App\Translation\Domain\ValueObject\TranslationKey;
use App\Translation\Domain\ValueObject\TranslationText;
use PHPUnit\Framework\TestCase;

final class TranslationEntryTest extends TestCase
{
    public function testReplaceTextKeepsCreationAndUpdatesMetadata(): void
    {
        $key         = TranslationKey::fromString('foo.bar');
        $original    = TranslationText::fromString('Hello');
        $updated     = TranslationText::fromString('Bonjour');
        $createdBy   = ActorId::fromString('018d3dcf-0000-7000-8000-000000000001');
        $updatedBy   = ActorId::fromString('018d3dcf-0000-7000-8000-000000000002');
        $createdAt   = new \DateTimeImmutable('2024-01-01T10:00:00Z');
        $updatedAt   = new \DateTimeImmutable('2024-01-02T11:00:00Z');
        $description = 'First version';

        $entry = new TranslationEntry(
            $key,
            $original,
            $createdAt,
            $createdAt,
            $createdBy,
            $createdBy,
            $description,
        );

        $newEntry = $entry->replaceText($updated, $updatedAt, $updatedBy, 'Second version');

        self::assertSame($createdAt, $newEntry->createdAt());
        self::assertSame($createdBy, $newEntry->createdBy());
        self::assertSame($updatedAt, $newEntry->updatedAt());
        self::assertSame($updatedBy, $newEntry->updatedBy());
        self::assertSame('Second version', $newEntry->description());
        self::assertSame('Bonjour', $newEntry->text()->toString());
    }

    public function testInitialValues(): void
    {
        $key       = TranslationKey::fromString('foo.baz');
        $text      = TranslationText::fromString('Hi');
        $createdAt = new \DateTimeImmutable('2024-02-01T08:00:00Z');
        $actor     = ActorId::fromString('018d3dcf-0000-7000-8000-000000000099');

        $entry = new TranslationEntry(
            $key,
            $text,
            $createdAt,
            $createdAt,
            $actor,
            $actor,
            'desc',
        );

        self::assertSame($key, $entry->key());
        self::assertSame($text, $entry->text());
        self::assertSame($createdAt, $entry->createdAt());
        self::assertSame($createdAt, $entry->updatedAt());
        self::assertSame($actor, $entry->createdBy());
        self::assertSame($actor, $entry->updatedBy());
        self::assertSame('desc', $entry->description());
    }
}
