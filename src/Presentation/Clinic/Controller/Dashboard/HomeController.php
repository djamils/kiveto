<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Dashboard;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    #[Route('/', name: 'clinic_home')]
    public function index(): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        $clinicName      = null;

        if (null !== $currentClinicId) {
            $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
            \assert($clinic instanceof \App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto);
            $clinicName = $clinic->name;
        }

        return $this->redirectToRoute('clinic_dashboard');
    }

    #[Route('/dashboard-layout14', name: 'clinic_dashboard_layout14')]
    public function dashboardLayout14(): Response
    {
        return $this->redirectToRoute('clinic_dashboard');
    }
}
