<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\User;

use App\Context\Clinic\Application\Command\Staff\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\Context\Clinic\Application\Command\Staff\DisableClinicMembership\DisableClinicMembership;
use App\Context\Clinic\Application\Command\Staff\EnableClinicMembership\EnableClinicMembership;
use App\Context\Clinic\Application\Exception\CannotChangeRoleWhileVeterinaryCredentialsExist;
use App\Context\Clinic\Application\Exception\LastClinicAdministratorException;
use App\Context\Clinic\Application\Query\Console\ConsoleMemberRow;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Clinic\ConsoleStaffInviter;
use App\Presentation\Backoffice\Clinic\InvitationRefusedException;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\ChangeInvitedUserEmail\ChangeInvitedUserEmail;
use App\System\IdentityAccess\Application\Command\DisableClinicAccount\DisableClinicAccount;
use App\System\IdentityAccess\Application\Command\EnableClinicAccount\EnableClinicAccount;
use App\System\IdentityAccess\Application\Command\RequestPasswordReset\RequestPasswordReset;
use App\System\IdentityAccess\Application\Command\RevokeInvitation\RevokeInvitation;
use App\System\IdentityAccess\Application\Command\RevokeUserSessions\RevokeUserSessions;
use App\System\IdentityAccess\Application\Query\GetAccountSummaries\AccountSummary;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What the console does to a clinic account (E2–E4), each step audited.
 * Refusals come back as UserActionRefusedException, in French.
 */
final readonly class ConsoleUserManager
{
    public const array DISABLE_REASONS = [
        'left'      => 'Départ de la clinique',
        'request'   => 'Demande de la clinique',
        'security'  => 'Incident de sécurité',
        'duplicate' => 'Compte en double',
        'other'     => 'Autre',
    ];

    public function __construct(
        private CommandBusInterface $commandBus,
        private ConsoleDirectory $directory,
        private ConsoleStaffInviter $inviter,
        private ConsoleAuditTrail $auditTrail,
        #[Autowire(service: 'messenger.bus.command')]
        private MessageBusInterface $asyncCommands,
    ) {
    }

    /**
     * The clinic account behind a sheet; null for any other account.
     */
    public function account(string $userId): ?AccountSummary
    {
        $account = $this->directory->accounts([$userId])[$userId] ?? null;

        return null !== $account && 'CLINIC' === $account->type ? $account : null;
    }

    /**
     * One of the person's memberships; null when it is not theirs.
     */
    public function membership(string $userId, string $membershipId): ?ConsoleMemberRow
    {
        foreach ($this->directory->members(userId: $userId) as $member) {
            if ($member->membershipId === $membershipId) {
                return $member;
            }
        }

        return null;
    }

    public function disable(AccountSummary $account, string $reason): int
    {
        $revoked = $this->commandBus->dispatch(new DisableClinicAccount($account->id));
        \assert(\is_int($revoked));

        $this->audit($account, 'user.disabled', 'Compte désactivé · ' . mb_strtolower(self::DISABLE_REASONS[$reason] ?? 'Autre'), [
            'Motif'              => self::DISABLE_REASONS[$reason] ?? 'Autre',
            'Sessions révoquées' => (string) $revoked,
        ]);

        return $revoked;
    }

    public function enable(AccountSummary $account): void
    {
        $this->commandBus->dispatch(new EnableClinicAccount($account->id));
        $this->audit($account, 'user.enabled', 'Compte réactivé');
    }

    /**
     * @throws UserActionRefusedException
     */
    public function changeRole(AccountSummary $account, ConsoleMemberRow $member, ClinicMemberRole $role): void
    {
        if ($role->value === $member->role) {
            return;
        }

        try {
            $this->commandBus->dispatch(new ChangeClinicMembershipRole($member->membershipId, $role));
        } catch (CannotChangeRoleWhileVeterinaryCredentialsExist) {
            throw new UserActionRefusedException('Ce vétérinaire a un numéro d’ordre enregistré : il garde le rôle Vétérinaire.');
        }

        $this->audit($account, 'user.role_changed', \sprintf('Rôle de %s : %s → %s', $member->printedName(), ConsoleDirectory::roleLabel($member->role), ConsoleDirectory::roleLabel($role->value)), clinic: $member);
    }

    /**
     * « Retirer »: the person leaves this clinic (the membership is kept, disabled).
     *
     * @throws UserActionRefusedException
     */
    public function remove(AccountSummary $account, ConsoleMemberRow $member): void
    {
        try {
            $this->commandBus->dispatch(new DisableClinicMembership($member->membershipId));
        } catch (LastClinicAdministratorException) {
            throw new UserActionRefusedException(\sprintf('%s est le dernier administrateur de %s : donne d’abord ce rôle à quelqu’un d’autre.', $member->printedName(), $member->clinicName));
        }

        $this->commandBus->dispatch(new RevokeInvitation($account->id, $member->clinicId));
        $this->audit($account, 'user.clinic_removed', \sprintf('Retiré de %s', $member->clinicName), clinic: $member);
    }

    public function restore(AccountSummary $account, ConsoleMemberRow $member): void
    {
        $this->commandBus->dispatch(new EnableClinicMembership($member->membershipId));
        $this->audit($account, 'user.clinic_restored', \sprintf('Accès à %s rétabli', $member->clinicName), clinic: $member);
    }

    /**
     * « Ajouter à une clinique »: the way the clinic would invite them; an
     * account still waiting gets that clinic's invitation e-mail.
     *
     * @throws UserActionRefusedException
     */
    public function addToClinic(AccountSummary $account, string $clinicId, ClinicMemberRole $role, bool $administrator): bool
    {
        if (!Uuid::isValid($clinicId) || null === $this->directory->clinic($clinicId)) {
            throw new UserActionRefusedException('Choisis une clinique.');
        }

        $named = array_values(array_filter($this->directory->members(userId: $account->id), static fn (ConsoleMemberRow $m): bool => '' !== $m->firstName && '' !== $m->lastName));

        if ([] === $named) {
            throw new UserActionRefusedException('Ce compte n’a pas encore de nom : invite la personne depuis la fiche de la clinique.');
        }

        try {
            return $this->inviter->invite($clinicId, $named[0]->firstName, $named[0]->lastName, (string) $account->email, $role, $administrator);
        } catch (InvitationRefusedException $exception) {
            throw new UserActionRefusedException($exception->getMessage());
        }
    }

    /**
     * « Renvoyer l'invitation »: a new link from each clinic the person is in.
     */
    public function resendInvitations(AccountSummary $account): int
    {
        $sent = 0;
        foreach ($this->live($account) as $member) {
            $sent += $this->inviter->resend($member->clinicId, [['userId' => $account->id, 'firstName' => $member->firstName, 'role' => $member->role]]);
        }

        return $sent;
    }

    /**
     * « Modifier l'e-mail », then the invitations go to the new address.
     *
     * @throws UserActionRefusedException
     */
    public function changeEmail(AccountSummary $account, string $email): int
    {
        try {
            $this->commandBus->dispatch(new ChangeInvitedUserEmail($account->id, $email));
        } catch (\InvalidArgumentException $exception) {
            throw new UserActionRefusedException($exception->getMessage());
        } catch (\DomainException $exception) {
            throw new UserActionRefusedException($exception->getMessage());
        }

        $this->audit($account, 'user.email_changed', 'E-mail de l’invitation corrigé', ['Avant' => (string) $account->email, 'Après' => mb_strtolower(trim($email))]);

        return $this->resendInvitations($account);
    }

    /**
     * « Annuler l'invitation »: every link stops working and the account is
     * turned off; the person stays in the clinics' histories.
     */
    public function cancelInvitation(AccountSummary $account): void
    {
        foreach ($this->directory->members(userId: $account->id) as $member) {
            $this->commandBus->dispatch(new RevokeInvitation($account->id, $member->clinicId));
        }

        $this->commandBus->dispatch(new DisableClinicAccount($account->id));
        $this->audit($account, 'user.invitation_cancelled', 'Invitation annulée');
    }

    public function sendPasswordLink(AccountSummary $account): void
    {
        $this->asyncCommands->dispatch(new RequestPasswordReset((string) $account->email));
        $this->audit($account, 'user.password_link_sent', 'Lien de réinitialisation du mot de passe envoyé');
    }

    /**
     * One session (`sessionId`), or all of them.
     */
    public function revokeSessions(AccountSummary $account, ?string $sessionId): int
    {
        $revoked = $this->commandBus->dispatch(new RevokeUserSessions($account->id, $sessionId));
        \assert(\is_int($revoked));

        $this->audit($account, 'user.sessions_revoked', null !== $sessionId ? 'Session révoquée' : 'Toutes les sessions révoquées', ['Sessions révoquées' => (string) $revoked]);

        return $revoked;
    }

    /**
     * @return list<ConsoleMemberRow>
     */
    private function live(AccountSummary $account): array
    {
        return array_values(array_filter($this->directory->members(userId: $account->id), static fn (ConsoleMemberRow $m): bool => !$m->isDisabled()));
    }

    /**
     * @param array<string, string> $details
     */
    private function audit(AccountSummary $account, string $action, string $summary, array $details = [], ?ConsoleMemberRow $clinic = null): void
    {
        $this->auditTrail->record(
            $action,
            AuditCategory::USERS,
            $summary,
            clinicId: $clinic?->clinicId,
            clinicName: $clinic?->clinicName,
            targetUserId: $account->id,
            details: ['Compte' => (string) ($account->email ?? $account->id)] + $details,
        );
    }
}
