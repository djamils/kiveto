<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Resolver;

use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Infrastructure\Resolver\HostnameAppScopeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class HostnameAppScopeResolverTest extends TestCase
{
    public function testResolvesKnownHost(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://clinic.kiveto.local/'));

        $resolver = new HostnameAppScopeResolver($stack);

        self::assertSame(AppScope::CLINIC, $resolver->resolve());
    }

    public function testDefaultsToShared(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('https://unknown.kiveto.com/'));

        $resolver = new HostnameAppScopeResolver($stack);

        self::assertSame(AppScope::SHARED, $resolver->resolve());
    }
}
