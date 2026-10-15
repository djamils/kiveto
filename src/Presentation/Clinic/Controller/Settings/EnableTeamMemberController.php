<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\EnableClinicMembership\EnableClinicMembership;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** « Réactiver l’accès »: the member can sign in to this clinic again. */
#[Route(path: '/settings/team/members/{membershipId}/enable', name: 'clinic_settings_team_enable', methods: ['POST'])]
final readonly class EnableTeamMemberController
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

        try {
            $this->commandBus->dispatch(new EnableClinicMembership(membershipId: $member->membershipId));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
