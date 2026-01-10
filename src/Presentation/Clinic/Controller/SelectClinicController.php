<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use App\Clinic\Domain\ValueObject\ClinicId;
use App\ClinicAccess\Application\Query\ListClinicsForUser\ListClinicsForUser;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\SelectedClinicContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '', host: 'clinic.kiveto.local')]
final class SelectClinicController extends AbstractController
{
    private const string CSRF_ID = 'clinic_select_clinic';

    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly SelectedClinicContext $selectedClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/select-clinic', name: 'clinic_select_clinic', methods: ['GET'])]
    public function selectClinic(): Response
    {
        $user = $this->getUser();

        if (null === $user) {
            return $this->redirectToRoute('clinic_login');
        }

        // Récupérer l'ID user depuis le SecurityUser (adapter selon votre implémentation)
        $userId = $user->getUserIdentifier(); // ou méthode custom getId()

        $accessibleClinics = $this->queryBus->ask(new ListClinicsForUser($userId));

        if (0 === \count($accessibleClinics)) {
            $this->addFlash('error', 'Vous n\'avez accès à aucune clinique active. Contactez un administrateur.');

            return $this->render('clinic/no-clinic-access.html.twig');
        }

        if (1 === \count($accessibleClinics)) {
            // Une seule clinique : auto-sélection
            $clinic = $accessibleClinics[0];
            $this->selectedClinicContext->setSelectedClinicId(ClinicId::fromString($clinic->clinicId));

            return $this->redirectToRoute('clinic_dashboard');
        }

        // Plusieurs cliniques : afficher la page de sélection
        return $this->render('clinic/select-clinic.html.twig', [
            'clinics'    => $accessibleClinics,
            'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
        ]);
    }

    #[Route(path: '/select-clinic', name: 'clinic_select_clinic_post', methods: ['POST'])]
    public function selectClinicPost(Request $request): RedirectResponse
    {
        $this->assertCsrf($request);

        $clinicId = trim((string) $request->request->get('clinic_id'));

        if ('' === $clinicId) {
            $this->addFlash('error', 'Veuillez sélectionner une clinique.');

            return $this->redirectToRoute('clinic_select_clinic');
        }

        try {
            $this->selectedClinicContext->setSelectedClinicId(ClinicId::fromString($clinicId));

            return $this->redirectToRoute('clinic_dashboard');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());

            return $this->redirectToRoute('clinic_select_clinic');
        }
    }

    #[Route(path: '/dashboard', name: 'clinic_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        if (!$this->selectedClinicContext->hasSelectedClinic()) {
            return $this->redirectToRoute('clinic_select_clinic');
        }

        $selectedClinicId = $this->selectedClinicContext->getSelectedClinicId();

        return $this->render('clinic/dashboard.html.twig', [
            'selectedClinicId' => $selectedClinicId->toString(),
        ]);
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken(self::CSRF_ID, (string) $request->request->get('_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
