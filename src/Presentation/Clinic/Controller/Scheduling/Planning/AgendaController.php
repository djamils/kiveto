<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Planning;

use App\Presentation\Clinic\ViewModel\Agenda\AgendaViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The agenda (week / day per practitioner), drawn client-side from the
 * payload of AgendaViewBuilder; later periods come from AgendaRangeController.
 */
#[Route('/scheduling/agenda', name: 'clinic_scheduling_agenda', methods: ['GET'])]
final class AgendaController extends AbstractController
{
    public function __construct(
        private readonly AgendaViewBuilder $agenda,
        private readonly CurrentClinicContextInterface $currentClinicContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $user = $this->getUser();
        \assert($user instanceof SecurityUser);

        $date = $request->query->get('date');
        $view = $request->query->get('view');

        return $this->render('clinic/scheduling/agenda/index.html.twig', [
            'agenda' => $this->agenda->page(
                $clinicId->toString(),
                $user->id(),
                \is_string($date) ? $date : null,
                \is_string($view) ? $view : null,
            ),
            'openNew' => '1' === $request->query->get('new'),
            // The directory hands over who the appointment is for: the patient
            // search of the modal opens already filled in.
            'newPatient' => trim((string) $request->query->get('patient', '')),
        ]);
    }
}
