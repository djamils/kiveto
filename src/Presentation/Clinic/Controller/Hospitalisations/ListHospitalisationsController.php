<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Hospitalisations;

use App\Context\Clinic\Application\Query\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\GetClinic\GetClinic;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListHospitalisationsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    #[Route(path: '/hospitalisations', name: 'clinic_hospitalisations', methods: ['GET'])]
    public function __invoke(): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $clinic = $this->queryBus->ask(new GetClinic($currentClinicId->toString()));
        \assert($clinic instanceof ClinicDto);

        return $this->render('clinic/hospitalisations/index.html.twig', [
            'currentClinicId'   => $currentClinicId->toString(),
            'currentClinicName' => $clinic->name,
        ]);
    }
}
