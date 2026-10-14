<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth;

use App\Context\Clinic\Application\Command\Staff\SetDefaultClinic\SetDefaultClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUser;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The star of the clinic switcher: picks the clinic opened at sign-in without
 * leaving the one the member is working in.
 */
#[Route(path: '', host: 'clinic.kiveto.local')]
final class SetDefaultClinicController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/select-clinic/default', name: 'clinic_select_clinic_default', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $token = new CsrfToken('clinic_select_clinic', (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            return $this->redirectToRoute('clinic_login');
        }

        $clinicId = trim((string) $request->request->get('clinic_id'));
        $clinics  = $this->queryBus->ask(new ListClinicsForUser($user->id()));
        \assert(\is_array($clinics));

        $target = null;

        foreach ($clinics as $clinic) {
            if ($clinic instanceof AccessibleClinic && $clinic->clinicId === $clinicId) {
                $target = $clinic;
            }
        }

        if (null === $target) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cette clinique.');
        } else {
            $this->commandBus->dispatch(new SetDefaultClinic(clinicId: $target->clinicId, userId: $user->id()));
            $this->addFlash('success', 'Clinique par défaut à la connexion : ' . $target->clinicName);
        }

        return $this->redirect($this->backTo($request));
    }

    /**
     * Back to the page the star was clicked on, as long as it is one of ours.
     */
    private function backTo(Request $request): string
    {
        $referer = (string) $request->headers->get('referer');

        if ('' !== $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()) {
            return $referer;
        }

        return $this->generateUrl('clinic_dashboard');
    }
}
