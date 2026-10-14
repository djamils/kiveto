<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Presentation\Clinic\ViewModel\Flux\FluxBoardViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The ambulatory flow board (« Flux ambulatoire »): today's bookings still to
 * arrive, the waiting room by triage, the patients taken in charge, the desk,
 * and who already left.
 */
#[Route('/admission/queue', name: 'clinic_admission_queue', methods: ['GET'])]
final class AdmissionQueueController extends AbstractController
{
    public function __construct(
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly FluxBoardViewBuilder $fluxBoardViewBuilder,
    ) {
    }

    public function __invoke(): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        return $this->render('clinic/admission/queue.html.twig', [
            'board' => $this->fluxBoardViewBuilder->build($currentClinicId->toString()),
        ]);
    }
}
