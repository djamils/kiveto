<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\Api;

use App\Shared\Infrastructure\Http\Api\IpRateLimitListener;
use App\Tests\Fixtures\RateLimit\StubRateLimiterFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[AllowMockObjectsWithoutExpectations]
final class IpRateLimitListenerTest extends TestCase
{
    public function testAcceptedRequestPassesThrough(): void
    {
        $stub = new StubRateLimiterFactory();
        $stub->programKey('1.2.3.4', [[true, 1], [true, 1]]);

        $listener = new IpRateLimitListener($stub);

        $event = $this->makeRequestEvent('/api/clinic/clients/search', '1.2.3.4');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertSame([1], $stub->getCallLog('1.2.3.4'));
    }

    public function testRejectedRequestReturns429WithRetryAfter(): void
    {
        $stub = new StubRateLimiterFactory();
        $stub->programKey('1.2.3.4', [[false, 60]]);

        $listener = new IpRateLimitListener($stub);

        $event = $this->makeRequestEvent('/api/clinic/clients/search', '1.2.3.4');
        $listener->onKernelRequest($event);

        $response = $event->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame('{"error":"too_many_requests"}', $response->getContent());
        self::assertTrue($response->headers->has('Retry-After'));
    }

    public function testDifferentIpsHaveSeparateBuckets(): void
    {
        $stub = new StubRateLimiterFactory();
        $stub->programKey('1.2.3.4', [[false, 5]]);
        $stub->programKey('5.6.7.8', [[true, 1]]);

        $listener = new IpRateLimitListener($stub);

        $first = $this->makeRequestEvent('/api/clinic/clients/search', '1.2.3.4');
        $listener->onKernelRequest($first);
        self::assertNotNull($first->getResponse());

        $second = $this->makeRequestEvent('/api/clinic/clients/search', '5.6.7.8');
        $listener->onKernelRequest($second);
        self::assertNull($second->getResponse());

        self::assertSame([1], $stub->getCallLog('1.2.3.4'));
        self::assertSame([1], $stub->getCallLog('5.6.7.8'));
    }

    public function testIgnoresNonApiPath(): void
    {
        $stub = new StubRateLimiterFactory();

        $listener = new IpRateLimitListener($stub);

        $event = $this->makeRequestEvent('/clients', '1.2.3.4');
        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertSame([], $stub->getAllCallLogs());
    }

    public function testIgnoresSubRequest(): void
    {
        $stub     = new StubRateLimiterFactory();
        $listener = new IpRateLimitListener($stub);

        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/clinic/clients/search', server: ['REMOTE_ADDR' => '1.2.3.4']);
        $event   = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $listener->onKernelRequest($event);

        self::assertNull($event->getResponse());
        self::assertSame([], $stub->getAllCallLogs());
    }

    private function makeRequestEvent(string $path, string $ip): RequestEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($path, server: ['REMOTE_ADDR' => $ip]);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
