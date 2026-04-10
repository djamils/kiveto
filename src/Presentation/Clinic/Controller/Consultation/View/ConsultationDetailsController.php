<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Consultation\View;

use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ConsultationDetailsController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route('/clinic/consultations/{id}', name: 'clinic_consultation_details', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $consultation = $this->queryBus->ask(new GetConsultationDetails($id));

        return $this->render('clinic/consultation/detail/index.html.twig', [
            'consultation' => $consultation,
            'pageTitle'    => 'Consultation',
        ]);
    }
}
