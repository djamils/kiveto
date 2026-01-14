<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Security;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class RequireClinicSelectionSubscriber implements EventSubscriberInterface
{
    private const array WHITELIST_ROUTES = [
        'clinic_login',
        'clinic_select_clinic',
        'clinic_select_clinic_post',
        'clinic_no_access',
        'clinic_logout',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CurrentClinicContextInterface $currentClinicContext,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Run right after the firewall (priority 8) so the token is available.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getHost(), 'clinic.')) {
            return;
        }

        $routeName = $request->attributes->get('_route');

        // Router didn't match yet or internal routes (profiler, etc.)
        if (!\is_string($routeName) || '' === $routeName || str_starts_with($routeName, '_')) {
            return;
        }

        if (\in_array($routeName, self::WHITELIST_ROUTES, true)) {
            return;
        }

        $token = $this->tokenStorage->getToken();

        // No session token available: user is not authenticated.
        if (null === $token || '' === trim($token->getUserIdentifier())) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('clinic_login')));

            return;
        }

        if (!$this->currentClinicContext->hasCurrentClinic()) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('clinic_select_clinic')));
        }
    }
}
