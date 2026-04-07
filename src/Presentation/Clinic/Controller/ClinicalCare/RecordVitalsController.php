<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\ClinicalCare;

use App\ClinicalCare\Application\Command\RecordVitals\RecordVitals;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class RecordVitalsController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    #[Route('/clinic/consultations/{id}/vitals', name: 'clinic_consultation_record_vitals', methods: ['POST'])]
    public function __invoke(string $id, Request $request): Response
    {
        $weightKg     = $request->request->get('weightKg');
        $temperatureC = $request->request->get('temperatureC');

        // Convertir empty string en null
        $weightKg     = !empty($weightKg) ? (float) $weightKg : null;
        $temperatureC = !empty($temperatureC) ? (float) $temperatureC : null;

        try {
            $this->commandBus->dispatch(
                new RecordVitals(
                    consultationId: $id,
                    weightKg: $weightKg,
                    temperatureC: $temperatureC,
                )
            );

            $this->addFlash('success', 'Constantes vitales enregistrées.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('clinic_consultation_details', ['id' => $id]);
    }
}
