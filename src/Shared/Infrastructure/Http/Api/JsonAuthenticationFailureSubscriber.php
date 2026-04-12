<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Api;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Translates Symfony auth/access exceptions on /api/clinic/* into JSON 401/403,
 * instead of the default HTML redirect to login.
 */
final class JsonAuthenticationFailureSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (1 !== preg_match('#^/api/clinic(/|$)#', $request->getPathInfo())) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof AuthenticationException) {
            $event->setResponse(new JsonResponse(
                ['error' => 'unauthorized'],
                Response::HTTP_UNAUTHORIZED,
            ));

            return;
        }

        if ($exception instanceof AccessDeniedException) {
            $event->setResponse(new JsonResponse(
                ['error' => 'forbidden'],
                Response::HTTP_FORBIDDEN,
            ));
        }
    }
}
