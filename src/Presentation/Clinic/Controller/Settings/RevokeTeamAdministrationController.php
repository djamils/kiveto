<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipAdministration\ChangeClinicMembershipAdministration;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The member no longer manages the clinic. Refused for one's own hat, and when
 * it would leave the clinic with nobody able to open its settings.
 */
#[Route(path: '/settings/team/members/{membershipId}/administration/revoke', name: 'clinic_settings_team_administration_revoke', methods: ['POST'])]
final readonly class RevokeTeamAdministrationController
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

        if ($this->endpoint->isViewer($member)) {
            return $this->endpoint->refuse('Vous ne pouvez pas retirer votre propre gestion de la clinique.');
        }

        try {
            $this->commandBus->dispatch(new ChangeClinicMembershipAdministration(
                clinicId: $this->endpoint->clinicId(),
                membershipId: $member->membershipId,
                isClinicAdministrator: false,
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\RuntimeException $exception) {
            // Nobody would be left to manage the clinic.
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
