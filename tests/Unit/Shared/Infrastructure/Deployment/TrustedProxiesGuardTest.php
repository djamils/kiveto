<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Deployment;

use App\Shared\Infrastructure\Deployment\Deployment;
use App\Shared\Infrastructure\Deployment\TrustedProxiesGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class TrustedProxiesGuardTest extends TestCase
{
    public function testItRunsBeforeTheRouter(): void
    {
        self::assertSame([KernelEvents::REQUEST => ['onKernelRequest', 256]], TrustedProxiesGuard::getSubscribedEvents());
    }

    public function testWorkstationsNeedNoProxy(): void
    {
        $this->expectNotToPerformAssertions();

        (new TrustedProxiesGuard(Deployment::DEV, ''))->onKernelRequest($this->event());
    }

    /**
     * @return iterable<string, array{Deployment, string}>
     */
    public static function provideANamedProxyIsServedCases(): iterable
    {
        yield 'the direct peer, as .env.prod sets it' => [Deployment::PRODUCTION, 'REMOTE_ADDR'];
        yield 'a fixed range' => [Deployment::STAGING, '172.18.0.0/16'];
        yield 'several values' => [Deployment::DEMO, ' 172.18.0.0/16 , REMOTE_ADDR '];
    }

    #[DataProvider('provideANamedProxyIsServedCases')]
    public function testANamedProxyIsServed(Deployment $deployment, string $trustedProxies): void
    {
        $this->expectNotToPerformAssertions();

        (new TrustedProxiesGuard($deployment, $trustedProxies))->onKernelRequest($this->event());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAPublicDeploymentWithoutProxyIsRefusedCases(): iterable
    {
        yield 'empty' => [''];
        yield 'blank entries' => [' , '];
    }

    #[DataProvider('provideAPublicDeploymentWithoutProxyIsRefusedCases')]
    public function testAPublicDeploymentWithoutProxyIsRefused(string $trustedProxies): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SYMFONY_TRUSTED_PROXIES must name the reverse proxy on the production deployment.');

        (new TrustedProxiesGuard(Deployment::PRODUCTION, $trustedProxies))->onKernelRequest($this->event());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTrustingEveryAddressIsRefusedCases(): iterable
    {
        yield 'IPv4' => ['REMOTE_ADDR,0.0.0.0/0'];
        yield 'IPv6' => ['::/0'];
    }

    #[DataProvider('provideTrustingEveryAddressIsRefusedCases')]
    public function testTrustingEveryAddressIsRefused(string $trustedProxies): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('SYMFONY_TRUSTED_PROXIES must not trust every address on the staging deployment.');

        (new TrustedProxiesGuard(Deployment::STAGING, $trustedProxies))->onKernelRequest($this->event());
    }

    public function testSubRequestsAreNotCheckedAgain(): void
    {
        $this->expectNotToPerformAssertions();

        (new TrustedProxiesGuard(Deployment::PRODUCTION, ''))->onKernelRequest($this->event(HttpKernelInterface::SUB_REQUEST));
    }

    private function event(int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), $type);
    }
}
