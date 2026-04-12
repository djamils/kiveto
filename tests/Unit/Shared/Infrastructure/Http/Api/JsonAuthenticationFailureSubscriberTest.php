<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Http\Api;

use App\Shared\Infrastructure\Http\Api\JsonAuthenticationFailureSubscriber;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

#[AllowMockObjectsWithoutExpectations]
final class JsonAuthenticationFailureSubscriberTest extends TestCase
{
    public function testReturnsJsonUnauthorizedForApiClinicSubtree(): void
    {
        $event = $this->makeEvent('/api/clinic/clients/search', new AuthenticationException('nope'));

        (new JsonAuthenticationFailureSubscriber())->onKernelException($event);

        $response = $event->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame('{"error":"unauthorized"}', $response->getContent());
    }

    public function testReturnsJsonUnauthorizedForBareApiClinicPath(): void
    {
        $event = $this->makeEvent('/api/clinic', new AuthenticationException('nope'));

        (new JsonAuthenticationFailureSubscriber())->onKernelException($event);

        $response = $event->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testReturnsJsonForbiddenOnAccessDenied(): void
    {
        $event = $this->makeEvent('/api/clinic/clients/search', new AccessDeniedException('forbidden'));

        (new JsonAuthenticationFailureSubscriber())->onKernelException($event);

        $response = $event->getResponse();

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('{"error":"forbidden"}', $response->getContent());
    }

    public function testIgnoresNonApiPath(): void
    {
        $event = $this->makeEvent('/clients', new AuthenticationException('nope'));

        (new JsonAuthenticationFailureSubscriber())->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresUnrelatedException(): void
    {
        $event = $this->makeEvent('/api/clinic/clients/search', new \RuntimeException('boom'));

        (new JsonAuthenticationFailureSubscriber())->onKernelException($event);

        self::assertNull($event->getResponse());
    }

    private function makeEvent(string $path, \Throwable $exception): ExceptionEvent
    {
        $kernel  = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($path);

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
