<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\GetUserMembershipInClinic;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\MembershipDetails;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Presentation\Clinic\ViewModel\Team\TeamInvitationSender;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\InvitedUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUser;
use App\System\IdentityAccess\Domain\Exception\AccountDisabledException;
use App\System\IdentityAccess\Domain\Exception\EmailUsedByAnotherAccountException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Inviter un membre »: someone joins the team.
 *
 * A person without an account gets a PENDING one and, once their membership
 * exists, an e-mail with a 72-hour link to choose their password — nobody
 * types a password for them. A person who already uses Kiveto (another
 * clinic of the group) simply joins, without e-mail. Everyone gets a profile
 * — a name and a colour — and a veterinarian given a number is registered
 * with the Ordre on the way.
 */
#[Route(path: '/settings/team/members', name: 'clinic_settings_team_add', methods: ['POST'])]
final readonly class AddTeamMemberController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private TeamEndpoint $endpoint,
        private TeamInvitationSender $invitations,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $email     = mb_strtolower(TeamEndpoint::text($request, 'email') ?? '');
        $firstName = TeamEndpoint::text($request, 'firstName');
        $lastName  = TeamEndpoint::text($request, 'lastName');

        if ('' === $email || null === $firstName || null === $lastName) {
            return $this->endpoint->refuse('Le prénom, le nom et l’adresse e-mail sont nécessaires.');
        }

        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $this->endpoint->refuse('Cette adresse e-mail n’est pas valide.');
        }

        $role       = ClinicMemberRole::tryFrom($request->request->getString('role'));
        $engagement = ClinicMembershipEngagement::tryFrom($request->request->getString('engagement'));

        if (null === $role || null === $engagement) {
            return $this->endpoint->refuse('Le rôle ou le type de contrat choisi n’existe pas.');
        }

        $registrationNumber = $role->canHoldVeterinaryCredentials() ? TeamEndpoint::text($request, 'registrationNumber') : null;
        $validUntil         = $this->endpoint->endOfDay(TeamEndpoint::text($request, 'validUntil'));
        $clinicId           = $this->endpoint->clinicId();

        try {
            $registration = $this->commandBus->dispatch(new RegisterInvitedUser($email));
            \assert($registration instanceof InvitedUserRegistration);
            $userId = $registration->userId;

            $this->commandBus->dispatch(new OnboardStaffMember(
                clinicId: $clinicId,
                userId: $userId,
                role: $role,
                engagement: $engagement,
                firstName: $firstName,
                lastName: $lastName,
                displayName: mb_substr(\sprintf('%s %s', $firstName, $lastName), 0, 60),
                agendaColor: TeamEndpoint::text($request, 'color') ?? '#64748b',
                sortOrder: 0,
                isVisibleInAgenda: \in_array($role, [ClinicMemberRole::VETERINARY, ClinicMemberRole::VETERINARY_ASSISTANT], true),
                validUntil: $validUntil,
                registrationNumber: $registrationNumber,
                professionalTitle: null !== $registrationNumber ? ProfessionalTitle::DR : null,
                isClinicAdministrator: $request->request->getBoolean('isClinicAdministrator'),
            ));
        } catch (EmailUsedByAnotherAccountException $exception) {
            return $this->endpoint->refuse($exception->getMessage());
        } catch (AccountDisabledException $exception) {
            return $this->endpoint->refuse($exception->getMessage());
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\RuntimeException $exception) {
            // Already a member, or a clinic that cannot be found.
            return $this->endpoint->refuse($exception);
        }

        // Only once the membership exists: a refused onboarding never sends a link.
        $invited = !$registration->alreadyActive && $this->invitations->send($clinicId, $userId, $firstName, $role);

        $membership = $this->queryBus->ask(new GetUserMembershipInClinic($userId, $clinicId));
        \assert($membership instanceof MembershipDetails);

        return $this->endpoint->ok(['id' => $membership->membershipId, 'invited' => $invited]);
    }
}
