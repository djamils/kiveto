<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\UpdateStaffProfileAgendaPreferences\UpdateStaffProfileAgendaPreferences;
use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * How a member appears in the agenda and the planning: their colour, and
 * whether they have a column of their own.
 */
#[Route(path: '/settings/team/members/{membershipId}/agenda', name: 'clinic_settings_team_agenda', methods: ['POST'])]
final readonly class ChangeTeamMemberAgendaController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private TeamEndpoint $endpoint,
        private StaffProfileReadRepositoryInterface $profiles,
    ) {
    }

    public function __invoke(Request $request, string $membershipId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $member = $this->endpoint->member($membershipId);

        if (null === $member) {
            return $this->endpoint->unknown();
        }

        $profile = $this->profiles->findByMembershipId($membershipId);

        if (null === $profile) {
            return $this->endpoint->refuse('Ce membre n’a pas de profil : sa couleur ne se modifie pas ici.');
        }

        try {
            $this->commandBus->dispatch(new UpdateStaffProfileAgendaPreferences(
                profileId: $profile->profileId,
                agendaColor: TeamEndpoint::text($request, 'color') ?? $profile->agendaColor,
                sortOrder: $profile->sortOrder,
                isVisibleInAgenda: $request->request->has('visible') ? $request->request->getBoolean('visible') : $profile->isVisibleInAgenda,
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
