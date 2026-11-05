<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger\Middleware;

use App\Shared\Infrastructure\Bus\Messenger\Middleware\ForgetQueryAnswersMiddleware;
use App\Shared\Infrastructure\Bus\Messenger\QueryAnswerCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

final class ForgetQueryAnswersMiddlewareTest extends TestCase
{
    public function testTheCommandRunsWithoutKeptAnswersAndForgetsThem(): void
    {
        $cache = new QueryAnswerCache();
        $cache->answer('q', static fn (): string => 'before');

        $seen     = [];
        $envelope = new Envelope(new \stdClass());
        $handled  = new ForgetQueryAnswersMiddleware($cache)->handle($envelope, self::stack(static function () use ($cache, &$seen): void {
            // Inside the command: read afresh, and not kept.
            $seen[] = $cache->answer('q', static fn (): string => 'inside');
            $seen[] = $cache->answer('q', static fn (): string => 'inside again');
        }));

        self::assertSame($envelope, $handled);
        self::assertSame(['inside', 'inside again'], $seen);
        self::assertSame('after', $cache->answer('q', static fn (): string => 'after'));
    }

    public function testAFailedCommandForgetsThemToo(): void
    {
        $cache = new QueryAnswerCache();
        $cache->answer('q', static fn (): string => 'before');

        try {
            new ForgetQueryAnswersMiddleware($cache)->handle(new Envelope(new \stdClass()), self::stack(static fn (): never => throw new \RuntimeException('failed')));
            self::fail('The failure goes through.');
        } catch (\RuntimeException $e) {
            self::assertSame('failed', $e->getMessage());
        }

        self::assertSame('after', $cache->answer('q', static fn (): string => 'after'));
        self::assertSame('after', $cache->answer('q', static fn (): string => 'kept again'), 'no command runs any more');
    }

    private static function stack(\Closure $handler): StackInterface
    {
        return new StackMiddleware(new class($handler) implements MiddlewareInterface {
            public function __construct(private \Closure $handler)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                ($this->handler)();

                return $envelope;
            }
        });
    }
}
