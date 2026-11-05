<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Infrastructure\Bus\Messenger\QueryAnswerCache;
use PHPUnit\Framework\TestCase;

final class QueryAnswerCacheTest extends TestCase
{
    private int $asked = 0;

    public function testAnAnswerIsKeptForTheRequest(): void
    {
        $cache = new QueryAnswerCache();

        self::assertSame(1, $cache->answer('q', $this->ask(...)));
        self::assertSame(1, $cache->answer('q', $this->ask(...)));
        self::assertSame(2, $cache->answer('other', $this->ask(...)));
        self::assertSame(2, $this->asked);
    }

    public function testANullAnswerIsKeptToo(): void
    {
        $cache = new QueryAnswerCache();
        $asked = 0;

        $cache->answer('q', static function () use (&$asked): mixed {
            ++$asked;

            return null;
        });
        self::assertNull($cache->answer('q', $this->ask(...)));
        self::assertSame([1, 0], [$asked, $this->asked]);
    }

    public function testNothingIsKeptNorServedWhileACommandRuns(): void
    {
        $cache = new QueryAnswerCache();
        $cache->answer('q', $this->ask(...));

        $cache->commandStarted();
        self::assertSame(2, $cache->answer('q', $this->ask(...)));
        self::assertSame(3, $cache->answer('q', $this->ask(...)));

        // A command dispatched by a handler: still inside the outer one.
        $cache->commandStarted();
        $cache->commandFinished();
        self::assertSame(4, $cache->answer('q', $this->ask(...)));

        // Once the last command is over, nothing read before is served.
        $cache->commandFinished();
        self::assertSame(5, $cache->answer('q', $this->ask(...)));
        self::assertSame(5, $cache->answer('q', $this->ask(...)));
    }

    public function testAKeptAnswerIsPeekedWithoutAsking(): void
    {
        $cache = new QueryAnswerCache();

        self::assertNull($cache->kept('q'), 'nothing asked yet');
        $cache->answer('q', $this->ask(...));
        self::assertSame(1, $cache->kept('q'));

        $cache->commandStarted();
        self::assertNull($cache->kept('q'), 'never while a command runs');
        $cache->commandFinished();
        self::assertNull($cache->kept('q'), 'forgotten once it ran');
        self::assertSame(1, $this->asked);
    }

    public function testTheResetForgetsEverything(): void
    {
        $cache = new QueryAnswerCache();
        $cache->answer('q', $this->ask(...));
        $cache->commandStarted();

        $cache->reset();

        self::assertSame(2, $cache->answer('q', $this->ask(...)));
        self::assertSame(2, $cache->answer('q', $this->ask(...)), 'no command runs any more');

        // A finish without a start (a reset in between) does not go below zero.
        $cache->commandFinished();
        self::assertSame(3, $cache->answer('q', $this->ask(...)));
        self::assertSame(3, $cache->answer('q', $this->ask(...)));
    }

    private function ask(): int
    {
        return ++$this->asked;
    }
}
