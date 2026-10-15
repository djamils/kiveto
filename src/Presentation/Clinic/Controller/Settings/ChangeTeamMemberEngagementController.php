<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipEngagement\ChangeClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** The contract a member works under, from the « Contrat » section. */
#[Route(path: '/settings/team/members/{membershipId}/engagement', name: 'clinic_settings_team_engagement', methods: ['POST'])]
final readonly class ChangeTeamMemberEngagementController
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

        $engagement = ClinicMembershipEngagement::tryFrom($request->request->getString('engagement'));

        if (null === $engagement) {
            return $this->endpoint->refuse('Ce type de contrat n’existe pas.');
        }

        try {
            $this->commandBus->dispatch(new ChangeClinicMembershipEngagement(membershipId: $member->membershipId, engagement: $engagement));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
