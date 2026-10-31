<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api\Portal\Problem;

use App\Presentation\Api\Portal\Problem\InvalidCursorListener;
use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use App\Shared\Application\Pagination\InvalidCursorException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class InvalidCursorListenerTest extends TestCase
{
    public function testAnUnreadableCursorOfThePortalBecomesCursorInvalid(): void
    {
        $event = $this->event('/api/portal/v1/clinics/c1/documents', new InvalidCursorException());

        new InvalidCursorListener()($event);

        $problem = $event->getThrowable();
        self::assertInstanceOf(ApiProblem::class, $problem);
        self::assertSame([PortalProblemMap::CURSOR_INVALID, 400], [$problem->getType(), $problem->getStatus()]);
    }

    public function testAnInvalidCursorOutsideThePortalIsLeftAlone(): void
    {
        $exception = new InvalidCursorException();
        $event     = $this->event('/api/clinic/v1/documents', $exception);

        new InvalidCursorListener()($event);

        self::assertSame($exception, $event->getThrowable());
    }

    public function testAnotherExceptionOfThePortalIsLeftAlone(): void
    {
        $exception = new \RuntimeException('boom');
        $event     = $this->event('/api/portal/v1/clinics/c1/documents', $exception);

        new InvalidCursorListener()($event);

        self::assertSame($exception, $event->getThrowable());
    }

    private function event(string $path, \Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create($path),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
