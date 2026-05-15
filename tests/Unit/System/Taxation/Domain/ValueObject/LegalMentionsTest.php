<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Taxation\Domain\ValueObject;

use App\System\Taxation\Domain\ValueObject\LegalMention;
use App\System\Taxation\Domain\ValueObject\LegalMentions;
use PHPUnit\Framework\TestCase;

final class LegalMentionsTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $mentions = LegalMentions::empty();

        self::assertTrue($mentions->isEmpty());
        self::assertSame(0, $mentions->count());
        self::assertSame([], $mentions->toArray());
        self::assertNull($mentions->byCode('ANY'));
    }

    public function testFromListWithItems(): void
    {
        $m1       = new LegalMention('INTRACOM', 'TVA non applicable', 'fr_FR');
        $m2       = new LegalMention('EXPORT', 'Exportation hors UE', 'fr_FR');
        $mentions = LegalMentions::fromList($m1, $m2);

        self::assertFalse($mentions->isEmpty());
        self::assertSame(2, $mentions->count());
        self::assertCount(2, $mentions->toArray());
    }

    public function testByCodeReturnsMatchingMention(): void
    {
        $m        = new LegalMention('INTRACOM', 'TVA non applicable', 'fr_FR');
        $mentions = LegalMentions::fromList($m);

        self::assertSame($m, $mentions->byCode('INTRACOM'));
    }

    public function testByCodeReturnsNullForUnknownCode(): void
    {
        $mentions = LegalMentions::fromList(new LegalMention('INTRACOM', 'text', 'fr_FR'));

        self::assertNull($mentions->byCode('UNKNOWN'));
    }
}
