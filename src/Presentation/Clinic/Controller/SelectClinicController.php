<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\AccessControl\Application\Query\ListClinicsForUser\ListClinicsForUser;
use App\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Clinic\Application\Query\GetClinic\GetClinic;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
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
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/select-clinic', name: 'clinic_select_clinic', methods: ['GET'])]
    public function selectClinic(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            return $this->redirectToRoute('clinic_login');
        }

        $userId = $user->id();

        $accessibleClinics = $this->queryBus->ask(new ListClinicsForUser($userId));
        \assert(\is_array($accessibleClinics));

        if (0 === \count($accessibleClinics)) {
            $this->addFlash('error', 'Vous n\'avez accès à aucune clinique active. Contactez un administrateur.');

            return $this->render('clinic/no-clinic-access.html.twig');
        }

        if (1 === \count($accessibleClinics)) {
            $clinic = $accessibleClinics[0];
            \assert($clinic instanceof AccessibleClinic);
            $this->currentClinicContext->setCurrentClinicId(ClinicId::fromString($clinic->clinicId));

            return $this->redirectToRoute('clinic_dashboard');
        }

        return $this->render('clinic/select-clinic.html.twig', [
            'clinics'    => $accessibleClinics,
            'csrf_token' => $this->csrfTokenManager->getToken(self::CSRF_ID)->getValue(),
        ]);
    }

    #[Route(path: '/select-clinic', name: 'clinic_select_clinic_post', methods: ['POST'])]
    public function selectClinicPost(Request $request): RedirectResponse
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
            // Security: verify clinic_id is in user's accessible clinics
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

    #[Route(path: '/dashboard', name: 'clinic_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        if (!$this->currentClinicContext->hasCurrentClinic()) {
            return $this->redirectToRoute('clinic_select_clinic');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        return $this->render('clinic/dashboard_layout15.html.twig', [
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
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
