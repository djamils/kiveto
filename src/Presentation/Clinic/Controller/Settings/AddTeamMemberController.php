<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Application\Port\UserSearchForOnboardingInterface;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\GetUserMembershipInClinic;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\MembershipDetails;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Presentation\Clinic\ViewModel\Team\TeamEndpoint;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Command\RegisterUser\RegisterUser;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Inviter un membre »: someone joins the team.
 *
 * Kiveto sends no e-mail yet, so a person without an account gets one with a
 * provisional password, handed over by whoever invites them; a person who
 * already has an account (another clinic of the group) simply joins. Everyone
 * gets a profile — a name and a colour — and a veterinarian given a number is
 * registered with the Ordre on the way.
 */
#[Route(path: '/settings/team/members', name: 'clinic_settings_team_add', methods: ['POST'])]
final readonly class AddTeamMemberController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private UserSearchForOnboardingInterface $users,
        private TeamEndpoint $endpoint,
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
        $userId             = $this->findUserByEmail($email);

        try {
            if (null === $userId) {
                $password = $request->request->getString('password');

                if (mb_strlen($password) < 8) {
                    return $this->endpoint->refuse('Cette personne n’a pas encore de compte Kiveto : donnez-lui un mot de passe provisoire d’au moins 8 caractères.');
                }

                $userId = $this->commandBus->dispatch(new RegisterUser(
                    email: $email,
                    plainPassword: $password,
                    type: UserType::CLINIC,
                ));
                \assert(\is_string($userId));
            }

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
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        } catch (\RuntimeException $exception) {
            // Already a member, or a clinic that cannot be found.
            return $this->endpoint->refuse($exception);
        }

        $membership = $this->queryBus->ask(new GetUserMembershipInClinic($userId, $clinicId));
        \assert($membership instanceof MembershipDetails);

        return $this->endpoint->ok(['id' => $membership->membershipId]);
    }

    private function findUserByEmail(string $email): ?string
    {
        foreach ($this->users->searchByEmail($email, 5) as $candidate) {
            if (mb_strtolower($candidate->email) === $email) {
                return $candidate->userId;
            }
        }

        return null;
    }
}
