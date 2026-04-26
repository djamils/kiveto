<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Admission;

use App\Context\Admission\Application\Query\ListAdmissionsInWaitingRoom\ListAdmissionsInWaitingRoom;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admission/queue', name: 'clinic_admission_queue', methods: ['GET'])]
final class AdmissionQueueController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $currentClinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $currentClinicId);

        $entries = $this->queryBus->ask(new ListAdmissionsInWaitingRoom(
            clinicId: $currentClinicId->toString(),
        ));

        \assert(\is_array($entries));

        return $this->render('clinic/admission/queue.html.twig', [
            'entries' => $entries,
        ]);
    }
}
