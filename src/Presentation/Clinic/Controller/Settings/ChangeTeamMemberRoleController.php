<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** The job a member holds: veterinarian, ASV, reception or manager. */
#[Route(path: '/settings/team/members/{membershipId}/role', name: 'clinic_settings_team_role', methods: ['POST'])]
final readonly class ChangeTeamMemberRoleController
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

        $role = ClinicMemberRole::tryFrom($request->request->getString('role'));

        if (null === $role) {
            return $this->endpoint->refuse('Ce rôle n’existe pas.');
        }

        try {
            $this->commandBus->dispatch(new ChangeClinicMembershipRole(membershipId: $member->membershipId, role: $role));
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\RuntimeException $exception) {
            // Credentials still registered for a role that cannot hold them.
            return $this->endpoint->refuse($exception);
        }

        return $this->endpoint->ok();
    }
}
