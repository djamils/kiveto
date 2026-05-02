<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Translation\Infrastructure\Resolver;

use App\System\Translation\Application\Port\AppScopeResolverInterface;
use App\System\Translation\Domain\ValueObject\AppScope;
use App\System\Translation\Infrastructure\Resolver\DefaultLocaleResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DefaultLocaleResolverTest extends TestCase
{
    private const array SHORT_MAP = ['fr' => 'fr-FR', 'en' => 'en-GB'];

    public function testBackofficeReturnsBakcofficeLocale(): void
    {
        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::BACKOFFICE);

        $resolver = $this->makeResolver(new RequestStack(), $scopeResolver, backofficeLocale: 'fr-FR');

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

        $resolver = $this->makeResolver($stack, $scopeResolver);

        self::assertSame('en-GB', $resolver->resolve()->toString());
    }

    public function testShortLocaleMapExpands(): void
    {
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'fr,en;q=0.9');
        $stack->push($request);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = $this->makeResolver($stack, $scopeResolver);

        self::assertSame('fr-FR', $resolver->resolve()->toString());
    }

    public function testShortLocaleMapReadsFromConfig(): void
    {
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->headers->set('Accept-Language', 'en;q=0.9,fr;q=0.8');
        $stack->push($request);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = $this->makeResolver($stack, $scopeResolver);

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

        $resolver = $this->makeResolver($stack, $scopeResolver);

        self::assertSame('es-ES', $resolver->resolve()->toString());
    }

    public function testDefaultLocaleUsedWhenNoCandidate(): void
    {
        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = $this->makeResolver(new RequestStack(), $scopeResolver, defaultLocale: 'en-GB');

        self::assertSame('en-GB', $resolver->resolve()->toString());
    }

    public function testChangingShortMapChangesOutput(): void
    {
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->attributes->set('_locale', 'en');
        $stack->push($request);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $resolver = new DefaultLocaleResolver($stack, $scopeResolver, 'en-US', 'fr-FR', ['en' => 'en-US']);

        self::assertSame('en-US', $resolver->resolve()->toString());
    }

    private function makeResolver(
        RequestStack $stack,
        AppScopeResolverInterface $scope,
        string $defaultLocale = 'en-GB',
        string $backofficeLocale = 'fr-FR',
    ): DefaultLocaleResolver {
        return new DefaultLocaleResolver($stack, $scope, $defaultLocale, $backofficeLocale, self::SHORT_MAP);
    }
}
