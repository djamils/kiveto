<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\UpdateStaffProfilePhone\UpdateStaffProfilePhone;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** The number a member is reached at, from the « Profil » section. */
#[Route(path: '/settings/team/members/{membershipId}/phone', name: 'clinic_settings_team_phone', methods: ['POST'])]
final readonly class ChangeTeamMemberPhoneController
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

        $profileId = $this->endpoint->profileId($membershipId);

        if (null === $profileId) {
            return $this->endpoint->refuse('Ce membre n’a pas de profil : son téléphone ne se modifie pas ici.');
        }

        try {
            $this->commandBus->dispatch(new UpdateStaffProfilePhone(
                profileId: $profileId,
                phone: TeamEndpoint::phone(TeamEndpoint::text($request, 'phone')),
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
