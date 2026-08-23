<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Settings;

use App\Context\Clinic\Application\Command\Staff\CreateClinicMembership\CreateClinicMembership;
use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Application\Port\UserSearchForOnboardingInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Presentation\Clinic\Security\ClinicPermissionGuard;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use App\System\IdentityAccess\Application\Command\RegisterUser\RegisterUser;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Someone joins the clinic.
 *
 * A practitioner gets a profile — a name in the agenda, a colour, credentials.
 * A manager or a receptionist gets a membership and nothing more, because there
 * is nothing more to give them.
 */
#[Route(path: '/settings/team/members', name: 'clinic_settings_team_add', methods: ['POST'])]
final class AddTeamMemberController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly CurrentClinicContextInterface $currentClinicContext,
        private readonly UserSearchForOnboardingInterface $users,
        private readonly ClinicPermissionGuard $guard,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $this->guard->require(ClinicPermissionGuard::MANAGE_STAFF);

        $clinicId = $this->currentClinicContext->getCurrentClinicId();
        \assert(null !== $clinicId);

        $email     = mb_strtolower(mb_trim($request->request->getString('email')));
        $firstName = mb_trim($request->request->getString('firstName'));
        $lastName  = mb_trim($request->request->getString('lastName'));
        $password  = $request->request->getString('password');

        if ('' === $email || '' === $firstName || '' === $lastName) {
            return $this->refuse('Le prénom, le nom et l’adresse e-mail sont nécessaires.');
        }

        $role       = ClinicMemberRole::tryFrom($request->request->getString('role'));
        $engagement = ClinicMembershipEngagement::tryFrom($request->request->getString('engagement'));

        if (null === $role || null === $engagement) {
            return $this->refuse('Le rôle ou le type de contrat choisi n’existe pas.');
        }

        $userId = $this->findUserByEmail($email);

        if (null === $userId) {
            if (mb_strlen($password) < 8) {
                return $this->refuse('Ce compte n’existe pas encore : donnez un mot de passe provisoire d’au moins 8 caractères.');
            }

            $userId = $this->commandBus->dispatch(new RegisterUser(
                email: $email,
                plainPassword: $password,
                type: UserType::CLINIC,
            ));

            \assert(\is_string($userId));
        }

        try {
            if (\in_array($role, [ClinicMemberRole::VETERINARY, ClinicMemberRole::VETERINARY_ASSISTANT], true)) {
                $this->commandBus->dispatch(new OnboardStaffMember(
                    clinicId: $clinicId->toString(),
                    userId: $userId,
                    role: $role,
                    engagement: $engagement,
                    firstName: $firstName,
                    lastName: $lastName,
                    displayName: mb_trim($request->request->getString('displayName')) ?: \sprintf('%s %s', $firstName, $lastName),
                    agendaColor: mb_trim($request->request->getString('agendaColor')) ?: '#4338ca',
                    sortOrder: $request->request->getInt('sortOrder'),
                    isVisibleInAgenda: $request->request->getBoolean('isVisibleInAgenda'),
                    phone: $this->field($request, 'phone'),
                    registrationNumber: $this->field($request, 'registrationNumber'),
                    professionalTitle: ProfessionalTitle::tryFrom($request->request->getString('professionalTitle')),
                ));
            } else {
                $this->commandBus->dispatch(new CreateClinicMembership(
                    clinicId: $clinicId->toString(),
                    userId: $userId,
                    role: $role,
                    engagement: $engagement,
                ));
            }
        } catch (\DomainException $exception) {
            return $this->refuse($exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->refuse($exception->getMessage());
        }

        $this->addFlash('success', \sprintf('%s %s a rejoint l’équipe.', $firstName, $lastName));

        return $this->redirectToRoute('clinic_settings_team');
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

    private function field(Request $request, string $name): ?string
    {
        $value = mb_trim($request->request->getString($name));

        return '' !== $value ? $value : null;
    }

    private function refuse(string $message): Response
    {
        $this->addFlash('error', $message);

        return $this->redirectToRoute('clinic_settings_team');
    }
}
