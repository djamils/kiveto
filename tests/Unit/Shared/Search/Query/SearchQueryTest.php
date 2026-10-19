<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Search\Query;

use App\Shared\Search\Query\SearchQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchQuery::class)]
final class SearchQueryTest extends TestCase
{
    public function testTheTermIsNormalized(): void
    {
        self::assertSame('jean pierre', new SearchQuery(' Jean-Pierre ', 'clinic', 'user')->normalizedTerm());
    }

    public function testATermOfTwoCharactersIsExecutable(): void
    {
        self::assertTrue(new SearchQuery('ab', 'clinic', 'user')->isExecutable());
        self::assertFalse(new SearchQuery(' a ', 'clinic', 'user')->isExecutable());
    }

    public function testWithLimitKeepsEverythingElse(): void
    {
        $query  = new SearchQuery('rex', 'clinic', 'user');
        $capped = $query->withLimit(21);

        self::assertSame(10, $query->limit);
        self::assertSame(21, $capped->limit);
        self::assertSame('rex', $capped->term);
        self::assertSame('clinic', $capped->clinicId);
        self::assertSame('user', $capped->userId);
    }
}
