<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Dashboard;

use App\Presentation\Clinic\ViewModel\Dashboard\DashboardViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '', host: 'clinic.kiveto.local')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly DashboardViewBuilder $dashboard,
    ) {
    }

    #[Route(path: '/dashboard', name: 'clinic_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        if (!$this->currentClinicContext->hasCurrentClinic()) {
            return $this->redirectToRoute('clinic_select_clinic');
        }

        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $user = $this->getUser();

        return $this->render('clinic/home/index.html.twig', [
            'dashboard' => $this->dashboard->build(
                $currentClinicId->toString(),
                $user instanceof SecurityUser ? $user->id() : null,
            ),
        ]);
    }
}
