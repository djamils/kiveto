<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Scheduling\Planning;

use App\Presentation\Clinic\ViewModel\Planning\PlanningViewBuilder;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The staff planning (day per practitioner / week / month), drawn client-side
 * from the payload of PlanningViewBuilder; other periods come from
 * PlanningRangeController.
 */
#[Route(path: '/scheduling/planning', name: 'clinic_scheduling_planning', methods: ['GET'])]
final class PlanningController extends AbstractController
{
    public function __construct(
        private readonly PlanningViewBuilder $planning,
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

        return $this->render('clinic/scheduling/planning/index.html.twig', [
            'planning' => $this->planning->page(
                $clinicId->toString(),
                $user->id(),
                \is_string($date) ? $date : null,
                \is_string($view) ? $view : null,
            ),
        ]);
    }
}
