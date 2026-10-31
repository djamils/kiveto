<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Problem;

use App\Shared\Application\Pagination\InvalidCursorException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A cursor the queries cannot read (forged, truncated, another shape)
 * becomes `400 cursor_invalid`, rendered by API Platform like every other
 * Portal problem. Runs before API Platform's own exception listener.
 */
final readonly class InvalidCursorListener
{
    private const string PATH_PREFIX = '/api/portal/v1/';

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 64)]
    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof InvalidCursorException || !str_starts_with($event->getRequest()->getPathInfo(), self::PATH_PREFIX)) {
            return;
        }

        $event->setThrowable(PortalProblemMap::problem(PortalProblemMap::CURSOR_INVALID));
    }
}
