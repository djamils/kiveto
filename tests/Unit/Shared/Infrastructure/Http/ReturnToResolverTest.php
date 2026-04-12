<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ReturnToResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AllowMockObjectsWithoutExpectations]
final class ReturnToResolverTest extends TestCase
{
    public function testWhitelistedRouteWithAllowedParams(): void
    {
        $resolver = new ReturnToResolver($this->urlGenerator());

        $url = $resolver->resolve(
            'clinic_scheduling_agenda|date=2026-04-11&view=week',
            'clinic_clients_list',
        );

        self::assertSame('/scheduling/agenda?date=2026-04-11&view=week', $url);
    }

    public function testUnknownParamsAreFiltered(): void
    {
        $resolver = new ReturnToResolver($this->urlGenerator());

        $url = $resolver->resolve(
            'clinic_clients_list|tab=animals&evil=xyz',
            'clinic_clients_list',
        );

        self::assertSame('/clients?tab=animals', $url);
    }

    public function testNonWhitelistedRouteFallsBackToDefault(): void
    {
        $resolver = new ReturnToResolver($this->urlGenerator());

        $url = $resolver->resolve(
            'admin_dashboard|secret=1',
            'clinic_clients_list',
        );

        self::assertSame('/clients', $url);
    }

    public function testMalformedInputFallsBackToDefault(): void
    {
        $resolver = new ReturnToResolver($this->urlGenerator());

        self::assertSame('/clients', $resolver->resolve('not-a-valid-thing', 'clinic_clients_list'));
        self::assertSame('/clients', $resolver->resolve('', 'clinic_clients_list'));
        self::assertSame('/clients', $resolver->resolve(null, 'clinic_clients_list'));
    }

    public function testRawJavascriptUriRejected(): void
    {
        $resolver = new ReturnToResolver($this->urlGenerator());

        self::assertSame(
            '/clients',
            $resolver->resolve('javascript:alert(1)', 'clinic_clients_list'),
        );
    }

    public function testProtocolRelativeUrlRejected(): void
    {
        $resolver = new ReturnToResolver($this->urlGenerator());

        self::assertSame(
            '/clients',
            $resolver->resolve('//evil.com/x', 'clinic_clients_list'),
        );
    }

    public function testParamsWithUrlEncodedAccentsRoundTrip(): void
    {
        $resolver = new ReturnToResolver($this->urlGenerator());

        $url = $resolver->resolve(
            'clinic_clients_list|search=' . rawurlencode("d'éva"),
            'clinic_clients_list',
        );

        self::assertStringContainsString('search=', $url);
        self::assertStringContainsString('d%27%C3%A9va', $url);
    }

    public function testBareRouteNameWithoutParamsResolves(): void
    {
        $resolver = new ReturnToResolver($this->urlGenerator());

        self::assertSame(
            '/clients',
            $resolver->resolve('clinic_clients_list', 'clinic_scheduling_agenda'),
        );
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->method('generate')
            ->willReturnCallback(static function (string $name, array $params = []): string {
                $base = match ($name) {
                    'clinic_clients_list'      => '/clients',
                    'clinic_scheduling_agenda' => '/scheduling/agenda',
                    default                    => '/' . $name,
                };

                return [] === $params ? $base : $base . '?' . http_build_query($params);
            })
        ;

        return $generator;
    }
}
