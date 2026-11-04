<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Translation\Infrastructure\Resolver;

use App\System\Translation\Domain\ValueObject\AppScope;
use App\System\Translation\Infrastructure\Resolver\HostnameAppScopeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class HostnameAppScopeResolverTest extends TestCase
{
    /** @return iterable<string, array{string, string, AppScope}> */
    public static function provideResolvesTheApplicationFromTheHostCases(): iterable
    {
        yield 'clinic in dev' => ['kiveto.local', 'https://clinic.kiveto.local/', AppScope::CLINIC];
        yield 'portal in dev, with a port' => ['kiveto.local', 'http://portal.kiveto.local:81/', AppScope::PORTAL];
        yield 'backoffice in dev' => ['kiveto.local', 'https://backoffice.kiveto.local/', AppScope::BACKOFFICE];
        yield 'portal on STAGING' => ['staging.kiveto.app', 'https://portal.staging.kiveto.app/', AppScope::PORTAL];
        yield 'clinic in production' => ['kiveto.app', 'https://clinic.kiveto.app/', AppScope::CLINIC];
        yield 'host of another deployment' => ['kiveto.app', 'https://clinic.staging.kiveto.app/', AppScope::SHARED];
        yield 'unknown application' => ['kiveto.local', 'https://unknown.kiveto.local/', AppScope::SHARED];
        yield 'unknown domain' => ['kiveto.local', 'https://clinic.kiveto.test/', AppScope::SHARED];
        yield 'bare domain' => ['kiveto.local', 'https://kiveto.local/', AppScope::SHARED];
        yield 'single label' => ['kiveto.local', 'http://localhost/', AppScope::SHARED];
    }

    #[DataProvider('provideResolvesTheApplicationFromTheHostCases')]
    public function testResolvesTheApplicationFromTheHost(string $domain, string $url, AppScope $expected): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create($url));

        self::assertSame($expected, new HostnameAppScopeResolver($stack, $domain)->resolve());
    }

    public function testDefaultsToSharedOutsideARequest(): void
    {
        self::assertSame(AppScope::SHARED, new HostnameAppScopeResolver(new RequestStack(), 'kiveto.local')->resolve());
    }
}
