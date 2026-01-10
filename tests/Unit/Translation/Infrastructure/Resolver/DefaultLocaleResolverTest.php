<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Resolver;

use App\Translation\Application\Port\AppScopeResolverInterface;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Infrastructure\Resolver\DefaultLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DefaultLocaleResolverTest extends TestCase
{
    public function testBackofficeForcedFr(): void
    {
        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::BACKOFFICE);

        $resolver = new DefaultLocaleResolver(new RequestStack(), $scopeResolver, 'en-GB');

        self::assertSame('fr-FR', $resolver->resolve()->toString());
    }

    public function testCandidateNormalizedAndMapped(): void
    {
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->attributes->set('_locale', 'en-GB');
        $stack->push($request);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = new DefaultLocaleResolver($stack, $scopeResolver, 'fr-FR');

        self::assertSame('en-GB', $resolver->resolve()->toString());
    }

    public function testAcceptLanguageFallback(): void
    {
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'fr,en;q=0.9');
        $stack->push($request);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = new DefaultLocaleResolver($stack, $scopeResolver, 'en-GB');

        self::assertSame('fr-FR', $resolver->resolve()->toString());
    }

    public function testAcceptLanguageFirstValueParsed(): void
    {
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en;q=0.9,fr;q=0.8');
        $stack->push($request);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = new DefaultLocaleResolver($stack, $scopeResolver, 'fr-FR');

        self::assertSame('en-GB', $resolver->resolve()->toString());
    }

    public function testNormalizeCandidateDefault(): void
    {
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->attributes->set('_locale', 'es-ES');
        $stack->push($request);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = new DefaultLocaleResolver($stack, $scopeResolver, 'fr-FR');

        self::assertSame('es-ES', $resolver->resolve()->toString());
    }

    public function testDefaultLocaleUsedWhenNoCandidate(): void
    {
        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = new DefaultLocaleResolver(new RequestStack(), $scopeResolver, 'en-GB');

        self::assertSame('en-GB', $resolver->resolve()->toString());
    }
}
