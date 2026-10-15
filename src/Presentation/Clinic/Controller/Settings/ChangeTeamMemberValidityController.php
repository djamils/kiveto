<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipValidityWindow\ChangeClinicMembershipValidityWindow;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * When a member's access opens and closes (« Arrivée », « Fin de l’accès »):
 * the access opens at the start of the first day and ends at midnight on the
 * last, in the clinic's time zone. Refused when it would leave the clinic
 * with nobody able to manage it.
 */
#[Route(path: '/settings/team/members/{membershipId}/validity', name: 'clinic_settings_team_validity', methods: ['POST'])]
final readonly class ChangeTeamMemberValidityController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private TeamEndpoint $endpoint,
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

        $from  = $this->endpoint->startOfDay(TeamEndpoint::text($request, 'from'));
        $until = TeamEndpoint::text($request, 'until');

        if (null === $from) {
            return $this->endpoint->refuse('La date d’arrivée est nécessaire.');
        }

        try {
            $this->commandBus->dispatch(new ChangeClinicMembershipValidityWindow(
                membershipId: $member->membershipId,
                validFrom: $from,
                validUntil: $this->endpoint->endOfDay($until),
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\RuntimeException $exception) {
            // The last administrator's access would close.
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
