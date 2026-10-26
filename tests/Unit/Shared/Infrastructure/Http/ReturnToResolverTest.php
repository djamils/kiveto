<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Http\ReturnToResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

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

    public function testCheckoutTargetResolvesWithItsAdmission(): void
    {
        $resolver = new ReturnToResolver($this->router());

        self::assertSame('/checkout/adm-1', $resolver->resolveOrNull('clinic_checkout|admissionId=adm-1&evil=1'));
        self::assertSame('clinic_checkout', $resolver->routeOf('clinic_checkout|admissionId=adm-1'));
    }

    public function testInvoiceTargetsResolve(): void
    {
        $resolver = new ReturnToResolver($this->router());

        self::assertSame(
            '/invoices/0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b?per=0',
            $resolver->resolveOrNull('clinic_invoice_details|invoiceId=0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b&per=0'),
        );
        self::assertSame('/invoices?q=Benali', $resolver->resolveOrNull('clinic_invoices|q=Benali'));
        self::assertSame('/invoices', $resolver->resolveOrNull('clinic_invoices'));
    }

    public function testTargetMissingItsRouteParameterIsNoTarget(): void
    {
        $resolver = new ReturnToResolver($this->router());

        self::assertNull($resolver->resolveOrNull('clinic_checkout'));
        self::assertNull($resolver->resolveOrNull('clinic_checkout|evil=1'));
        self::assertNull($resolver->resolveOrNull('clinic_invoice_details|invoiceId=not-a-uuid'));
        self::assertNull($resolver->routeOf('clinic_checkout'));
        self::assertSame('/clients', $resolver->resolve('clinic_checkout', 'clinic_clients_list'));
    }

    #[DataProvider('provideForeignTargetsAreNeverFollowedCases')]
    public function testForeignTargetsAreNeverFollowed(string $raw): void
    {
        $resolver = new ReturnToResolver($this->router());

        self::assertNull($resolver->resolveOrNull($raw));
        self::assertNull($resolver->routeOf($raw));
        self::assertSame('/clients', $resolver->resolve($raw, 'clinic_clients_list'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForeignTargetsAreNeverFollowedCases(): iterable
    {
        yield 'absolute URL' => ['https://evil.example'];
        yield 'protocol-relative URL' => ['//evil.example'];
        yield 'backslash URL' => ['/\\evil.example'];
        yield 'URL as a route name' => ['https://evil.example|admissionId=1'];
        yield 'unlisted route' => ['clinic_settings_team|x=1'];
        yield 'unlisted bare route' => ['clinic_settings_team'];
    }

    public function testBlankTargetIsNoTarget(): void
    {
        $resolver = new ReturnToResolver($this->router());

        self::assertNull($resolver->resolveOrNull(null));
        self::assertNull($resolver->resolveOrNull('  '));
    }

    private function router(): UrlGeneratorInterface
    {
        $routes = new RouteCollection();
        $routes->add('clinic_clients_list', new Route('/clients'));
        $routes->add('clinic_checkout', new Route('/checkout/{admissionId}'));
        $routes->add('clinic_invoices', new Route('/invoices'));
        $routes->add('clinic_invoice_details', new Route('/invoices/{invoiceId}', requirements: ['invoiceId' => Requirement::UUID]));
        $routes->add('clinic_settings_team', new Route('/settings/team'));

        return new UrlGenerator($routes, new RequestContext());
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
