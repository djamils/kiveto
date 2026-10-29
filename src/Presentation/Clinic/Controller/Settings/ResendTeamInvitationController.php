<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Presentation\Clinic\ViewModel\Team\TeamInvitationSender;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Renvoyer »: a new invitation link for a member who has not activated
 * their account; the previous link of this clinic stops working. A member of
 * another clinic is not found (404); a disabled or ended access is refused —
 * reopen it first.
 */
#[Route(path: '/settings/team/members/{membershipId}/invitation', name: 'clinic_settings_team_resend', methods: ['POST'])]
final readonly class ResendTeamInvitationController
{
    public function __construct(
        private TeamEndpoint $endpoint,
        private TeamInvitationSender $invitations,
        private StaffProfileReadRepositoryInterface $profiles,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request, string $membershipId): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $member = $this->endpoint->member($membershipId);

        if (null === $member) {
            $unknown = $this->endpoint->unknown();
            $unknown->setStatusCode(JsonResponse::HTTP_NOT_FOUND);

            return $unknown;
        }

        if (ClinicMembershipStatus::DISABLED === $member->status || (null !== $member->validUntil && $member->validUntil < $this->clock->now())) {
            return $this->endpoint->refuse('L’accès de ce membre est désactivé ou terminé : rouvrez-le avant de renvoyer l’invitation.');
        }

        $firstName = $this->profiles->findByMembershipId($member->membershipId)->firstName ?? '';

        if (!$this->invitations->send($this->endpoint->clinicId(), $member->userId, $firstName, $member->role)) {
            return $this->endpoint->refuse('Ce membre a déjà activé son compte.');
        }

        return $this->endpoint->ok();
    }
}
