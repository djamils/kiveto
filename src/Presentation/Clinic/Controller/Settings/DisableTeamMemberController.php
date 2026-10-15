<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\DisableClinicMembership\DisableClinicMembership;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Désactiver l’accès »: the member can no longer sign in to this clinic.
 *
 * The membership is kept either way: what the member did stays signed with
 * their name. One cannot switch off one's own access from here.
 */
#[Route(path: '/settings/team/members/{membershipId}/disable', name: 'clinic_settings_team_disable', methods: ['POST'])]
final readonly class DisableTeamMemberController
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
            return $this->endpoint->refuse('Vous ne pouvez pas désactiver votre propre accès.');
        }

        try {
            $this->commandBus->dispatch(new DisableClinicMembership(membershipId: $member->membershipId));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\RuntimeException $exception) {
            // Nobody would be left to manage the clinic.
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
