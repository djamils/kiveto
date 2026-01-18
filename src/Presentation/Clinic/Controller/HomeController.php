<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller;

use App\Clinic\Application\Query\GetClinic\GetClinic;
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
            \assert($clinic instanceof \App\Clinic\Application\Query\GetClinic\ClinicDto);
            $clinicName = $clinic->name;
        }

        return $this->render('clinic/index.html.twig', [
            'clinicName' => $clinicName,
            'menuItems'  => [
                [
                    'label' => 'Tableau de bord',
                    'route' => 'clinic_dashboard',
                    'icon'  => 'ki-chart',
                ],
                [
                    'label' => 'Clients',
                    'route' => 'clinic_clients_list',
                    'icon'  => 'ki-profile-circle',
                ],
                [
                    'label' => 'Animaux',
                    'route' => null,
                    'icon'  => 'ki-security-user',
                    'badge' => 'Bientôt',
                ],
                [
                    'label' => 'Rendez-vous',
                    'route' => null,
                    'icon'  => 'ki-calendar',
                    'badge' => 'Bientôt',
                ],
            ],
            'translations' => [
                'hello'                => 'hello', // already defined fr-FR
                'clinic.home.title'    => 'clinic.home.title',
                'clinic.home.subtitle' => 'clinic.home.subtitle',
                'clinic.home.cta'      => 'clinic.home.cta',
            ],
        ]);
    }

    #[Route('/dashboard-layout14', name: 'clinic_dashboard_layout14')]
    public function dashboardLayout14(): Response
    {
        return $this->render('clinic/dashboard-layout14.html.twig');
    }
}
