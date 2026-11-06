<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Security;

use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUser;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
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
        'clinic_lock',
        // Public account pages: reached without a session.
        'clinic_forgot_password',
        'clinic_forgot_password_submit',
        'clinic_reset_password',
        'clinic_reset_password_submit',
        'clinic_invitation',
        'clinic_invitation_submit',
        // A support session opens (D2) and ends (D3) on its own.
        'clinic_support_claim',
        'clinic_support_end',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CurrentClinicContextInterface $currentClinicContext,
        private UrlGeneratorInterface $urlGenerator,
        private QueryBusInterface $queryBus,
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

        $clinicId = $this->currentClinicContext->getCurrentClinicId();

        if (null === $clinicId) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('clinic_select_clinic')));

            return;
        }

        // A membership disabled, or out of its validity window, since the
        // clinic was chosen ends the access at the next request: the clinic is
        // forgotten and the member picks again among those still open to them
        // (none: « no access »). ListClinicsForUser is cached for the request,
        // and the shell asks it anyway.
        $user = $token->getUser();

        if ($user instanceof SecurityUser && !$this->isStillOpenTo($user->id(), $clinicId->toString())) {
            $this->currentClinicContext->clearCurrentClinic();
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('clinic_select_clinic')));
        }
    }

    private function isStillOpenTo(string $userId, string $clinicId): bool
    {
        $clinics = $this->queryBus->ask(new ListClinicsForUser($userId));

        foreach (\is_array($clinics) ? $clinics : [] as $clinic) {
            if ($clinic instanceof AccessibleClinic && $clinic->clinicId === $clinicId) {
                return true;
            }
        }

        return false;
    }
}
