<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Refuses a clinic route to a member whose role lacks the permission the route
 * requires (ClinicRoutePermissions), before its controller runs. A route of an
 * area without a rule is refused too. The refusal speaks the language of the
 * request, as the endpoints of the application do: a JSON 403 with the
 * FORBIDDEN code for the scripts, a flash and the dashboard for a form, the
 * 403 page for a page.
 */
final readonly class ClinicPermissionListener implements EventSubscriberInterface
{
    private const string REFUSAL = 'Votre rôle ne permet pas cette action.';

    public function __construct(
        private ClinicRoutePermissions $routes,
        private ClinicPermissionGuard $guard,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => ['onKernelController', 0],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request    = $event->getRequest();
        $controller = $request->attributes->get('_controller');

        if (!\is_string($controller) || !$this->routes->covers($controller)) {
            return;
        }

        try {
            $permission = $this->routes->permissionFor($controller, $request->getMethod());
        } catch (\LogicException) {
            $permission = false;
        }

        if (null === $permission || (false !== $permission && $this->guard->has($permission))) {
            return;
        }

        $refusal = $this->refusal($request);
        $event->setController(static fn (): Response => $refusal);
    }

    private function refusal(Request $request): Response
    {
        if ($request->isXmlHttpRequest() || 'json' === $request->getPreferredFormat()) {
            return new JsonResponse([
                'success'   => false,
                'errors'    => ['global' => [self::REFUSAL]],
                'errorCode' => 'FORBIDDEN',
                'message'   => self::REFUSAL,
            ], Response::HTTP_FORBIDDEN);
        }

        if (\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            throw new AccessDeniedException(self::REFUSAL);
        }

        $session = $request->hasSession() ? $request->getSession() : null;

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', self::REFUSAL);
        }

        return new RedirectResponse($this->urls->generate('clinic_dashboard'));
    }
}
