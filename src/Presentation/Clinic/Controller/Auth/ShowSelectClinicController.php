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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route(path: '', host: 'clinic.kiveto.local')]
final class ShowSelectClinicController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(path: '/select-clinic', name: 'clinic_select_clinic', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            return $this->redirectToRoute('clinic_login');
        }

        $accessibleClinics = $this->queryBus->ask(new ListClinicsForUser($user->id()));
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
            'csrf_token' => $this->csrfTokenManager->getToken('clinic_select_clinic')->getValue(),
        ]);
    }
}
