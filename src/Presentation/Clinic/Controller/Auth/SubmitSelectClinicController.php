<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Auth;

use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUser;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '', host: 'clinic.kiveto.local')]
final class SubmitSelectClinicController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/select-clinic', name: 'clinic_select_clinic_post', methods: ['POST'])]
    public function __invoke(Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            return $this->redirectToRoute('clinic_login');
        }

        $clinicId = trim((string) $request->request->get('clinic_id'));

        if ('' === $clinicId) {
            $this->addFlash('error', 'Veuillez sélectionner une clinique.');

            return $this->redirectToRoute('clinic_select_clinic');
        }

        try {
            $accessibleClinics = $this->queryBus->ask(new ListClinicsForUser($user->id()));
            \assert(\is_array($accessibleClinics));

            $isAccessible = false;
            foreach ($accessibleClinics as $clinic) {
                if ($clinic instanceof AccessibleClinic && $clinic->clinicId === $clinicId) {
                    $isAccessible = true;
                    break;
                }
            }

            if (!$isAccessible) {
                $this->addFlash('error', 'Vous n\'avez pas accès à cette clinique.');

                return $this->redirectToRoute('clinic_select_clinic');
            }

            $this->currentClinicContext->setCurrentClinicId(ClinicId::fromString($clinicId));

            return $this->redirectToRoute('clinic_dashboard');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());

            return $this->redirectToRoute('clinic_select_clinic');
        }
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken('clinic_select_clinic', (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
