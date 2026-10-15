<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipWorkTime\ChangeClinicMembershipWorkTime;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** How much of a full-time week a member works (« Temps de travail »). */
#[Route(path: '/settings/team/members/{membershipId}/work-time', name: 'clinic_settings_team_work_time', methods: ['POST'])]
final readonly class ChangeTeamMemberWorkTimeController
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
            $this->commandBus->dispatch(new ChangeClinicMembershipWorkTime(
                membershipId: $member->membershipId,
                workTimePercent: $request->request->getInt('workTime'),
            ));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
