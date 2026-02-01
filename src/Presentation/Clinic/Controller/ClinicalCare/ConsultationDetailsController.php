<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ConsultationDetailsController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $clinicContext,
    ) {
    }

    #[Route('/clinic/consultations/{id}', name: 'clinic_consultation_details', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        // TODO: Implémenter la query GetConsultationDetails quand disponible
        // Pour le MVP, on affiche une page simple
        
        return $this->render('clinic/clinical_care/consultation_details.html.twig', [
            'consultationId' => $id,
            'pageTitle' => 'Consultation',
        ]);
    }
}
