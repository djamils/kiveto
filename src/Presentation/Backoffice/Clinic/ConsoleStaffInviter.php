<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Clinic;

use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\ClinicSettingsView;
use App\Context\Clinic\Application\Query\Clinic\GetClinicSettings\GetClinicSettings;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\InvitedUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUser;
use App\System\IdentityAccess\Application\Command\SendInvitation\SendInvitation;
use App\System\IdentityAccess\Domain\Exception\AccountDisabledException;

/**
 * Invites a person to a clinic from the console, the way the clinic's own
 * team page does: the account (PENDING when new), the membership and its
 * profile, then the e-mail signed « L'équipe Kiveto ». Audited.
 */
final readonly class ConsoleStaffInviter
{
    private const string AGENDA_COLOR = '#64748b';

    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private ConsoleAuditTrail $auditTrail,
    ) {
    }

    /**
     * @throws InvitationRefusedException
     *
     * @return bool whether an e-mail left (an already active account just joins)
     */
    public function invite(string $clinicId, string $firstName, string $lastName, string $email, ClinicMemberRole $role, bool $administrator): bool
    {
        $firstName = trim($firstName);
        $lastName  = trim($lastName);
        $email     = mb_strtolower(trim($email));

        if ('' === $firstName || '' === $lastName || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new InvitationRefusedException('Le prénom, le nom et une adresse e-mail valide sont nécessaires.');
        }

        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        \assert($settings instanceof ClinicSettingsView);

        try {
            $registration = $this->commandBus->dispatch(new RegisterInvitedUser($email));
            \assert($registration instanceof InvitedUserRegistration);

            $this->commandBus->dispatch(new OnboardStaffMember(
                clinicId: $clinicId,
                userId: $registration->userId,
                role: $role,
                engagement: ClinicMembershipEngagement::EMPLOYEE,
                firstName: $firstName,
                lastName: $lastName,
                displayName: mb_substr($firstName . ' ' . $lastName, 0, 60),
                agendaColor: self::AGENDA_COLOR,
                sortOrder: 0,
                isVisibleInAgenda: \in_array($role, [ClinicMemberRole::VETERINARY, ClinicMemberRole::VETERINARY_ASSISTANT], true),
                professionalTitle: ClinicMemberRole::VETERINARY === $role ? ProfessionalTitle::DR : null,
                isClinicAdministrator: $administrator,
            ));
        } catch (AccountDisabledException $exception) {
            throw new InvitationRefusedException($exception->getMessage());
        } catch (\RuntimeException $exception) {
            throw new InvitationRefusedException($exception->getMessage());
        } catch (\LogicException $exception) {
            throw new InvitationRefusedException($exception->getMessage());
        }

        $sent = !$registration->alreadyActive && null !== $this->commandBus->dispatch(new SendInvitation(
            userId: $registration->userId,
            clinicId: $clinicId,
            inviterPrintedName: 'L’équipe Kiveto',
            clinicName: $settings->name,
            clinicCity: $settings->letterhead['city'] ?? '',
            inviteeFirstName: $firstName,
            roleLabel: ConsoleDirectory::roleLabel($role->value),
        ));

        $this->auditTrail->record(
            'user.invited',
            AuditCategory::USERS,
            \sprintf('Invitation envoyée à %s %s (%s)', $firstName, $lastName, ConsoleDirectory::roleLabel($role->value)),
            clinicId: $clinicId,
            clinicName: $settings->name,
            targetUserId: $registration->userId,
            details: ['E-mail' => $email, 'Administrateur' => $administrator ? 'Oui' : 'Non'] + ($sent ? [] : ['Remarque' => 'Compte déjà actif : ajouté sans e-mail']),
        );

        return $sent;
    }

    /**
     * « Relancer »: a new link for each member still waiting.
     *
     * @param list<array{userId: string, firstName: string, role: string}> $waiting
     */
    public function resend(string $clinicId, array $waiting): int
    {
        if ([] === $waiting) {
            return 0;
        }

        $settings = $this->queryBus->ask(new GetClinicSettings($clinicId));
        \assert($settings instanceof ClinicSettingsView);

        $sent = 0;
        foreach ($waiting as $member) {
            $token = $this->commandBus->dispatch(new SendInvitation(
                userId: $member['userId'],
                clinicId: $clinicId,
                inviterPrintedName: 'L’équipe Kiveto',
                clinicName: $settings->name,
                clinicCity: $settings->letterhead['city'] ?? '',
                inviteeFirstName: $member['firstName'],
                roleLabel: ConsoleDirectory::roleLabel($member['role']),
            ));
            $sent += null !== $token ? 1 : 0;
        }

        $this->auditTrail->record(
            'user.invitation_resent',
            AuditCategory::USERS,
            \sprintf('%d invitation%s relancée%s', $sent, $sent > 1 ? 's' : '', $sent > 1 ? 's' : ''),
            clinicId: $clinicId,
            clinicName: $settings->name,
        );

        return $sent;
    }
}
