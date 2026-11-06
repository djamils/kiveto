<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Support;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\RecordSupportPage\RecordSupportPage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * D2 — while a support session runs on the clinic host: it ends when its
 * time is up, a read-only one refuses every change, pages and changes are
 * counted, and the holder's « Sessions actives » never list it.
 */
final readonly class SupportSessionListener
{
    /** The key under which IdentityAccess's UserSessionListener keeps its last check. */
    private const string SESSION_CHECKED_AT = '_identity_access.session_checked_at';

    public function __construct(
        private CommandBusInterface $commandBus,
        private SupportSessionCloser $closer,
        private ClockInterface $clock,
    ) {
    }

    /**
     * After the firewall (8) and the clinic selection (7), before the
     * session tracking (5).
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only a browser that already has a session (no session is opened for probes or first visits).
        if (!$event->isMainRequest() || !str_starts_with($request->getHost(), 'clinic.') || !$request->hasPreviousSession()) {
            return;
        }

        $session = $request->getSession();
        $state   = SupportSessionState::of($session);
        $route   = $request->attributes->get('_route');

        if (null === $state || 'clinic_support_end' === $route || 'clinic_support_claim' === $route) {
            return;
        }

        $now = $this->clock->now()->getTimestamp();

        if ($state->isOverAt($now)) {
            $event->setResponse($this->closer->close($request, $state));

            return;
        }

        // Not one of the holder's own sessions.
        $session->set(self::SESSION_CHECKED_AT, $now);

        $write = !$request->isMethodSafe();

        if ($write && $state->isReadOnly()) {
            $message = 'Session support en lecture seule : aucune modification possible.';
            $event->setResponse(str_contains((string) $request->headers->get('Accept'), 'json') || $request->isXmlHttpRequest()
                ? new JsonResponse(['error' => $message], Response::HTTP_FORBIDDEN)
                : new Response($message, Response::HTTP_FORBIDDEN));

            return;
        }

        // Background calls, assets and the profiler are not pages.
        if ((!$write && $request->isXmlHttpRequest()) || !\is_string($route) || str_starts_with($route, '_')) {
            return;
        }

        if (false === $this->commandBus->dispatch(new RecordSupportPage($state->sessionId(), $write))) {
            $event->setResponse($this->closer->close($request, $state));
        }
    }
}
