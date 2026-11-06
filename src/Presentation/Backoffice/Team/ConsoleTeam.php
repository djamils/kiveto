<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Team;

use App\Presentation\Backoffice\Audit\ConsoleAuditTrail;
use App\Presentation\Backoffice\Security\CurrentAdmin;
use App\Presentation\Backoffice\ViewModel\ConsoleDirectory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\Audit\Domain\ValueObject\AuditCategory;
use App\System\IdentityAccess\Application\Command\ChangeConsoleRole\ChangeConsoleRole;
use App\System\IdentityAccess\Application\Command\InviteConsoleMember\ConsoleInvitation;
use App\System\IdentityAccess\Application\Command\InviteConsoleMember\InviteConsoleMember;
use App\System\IdentityAccess\Application\Command\SwitchConsoleMember\SwitchConsoleMember;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Application\Query\ListConsoleTeam\ListConsoleTeam;
use App\System\IdentityAccess\Domain\ValueObject\ConsoleRole;

/**
 * « Équipe »: the members of the Kiveto team in the console, their roles,
 * invited, switched off and on; each change audited.
 */
final readonly class ConsoleTeam
{
    public const array ROLES = ['admin' => 'Administrateur', 'support' => 'Support'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
        private ConsoleDirectory $directory,
        private ConsoleAuditTrail $auditTrail,
        private CurrentAdmin $currentAdmin,
    ) {
    }

    /**
     * @return list<array{member: BackofficeProfileView, lastLoginAt: ?\DateTimeImmutable, you: bool}>
     */
    public function members(): array
    {
        $members = $this->team();
        $logins  = $this->directory->accounts(array_map(static fn (BackofficeProfileView $m): string => $m->userId, $members));
        $you     = $this->currentAdmin->get()->id;

        return array_map(static fn (BackofficeProfileView $m): array => [
            'member'      => $m,
            'lastLoginAt' => ($logins[$m->userId] ?? null)?->lastLoginAt,
            'you'         => $m->userId === $you,
        ], $members);
    }

    public function find(string $userId): ?BackofficeProfileView
    {
        foreach ($this->team() as $member) {
            if ($member->userId === $userId) {
                return $member;
            }
        }

        return null;
    }

    /**
     * @throws \DomainException a wrong address or role, an account already in use
     */
    public function invite(string $email, string $name, string $role): ConsoleInvitation
    {
        $invitation = $this->commandBus->dispatch(new InviteConsoleMember($email, $name, $role));
        \assert($invitation instanceof ConsoleInvitation);

        $this->auditTrail->record(
            'team.member_invited',
            AuditCategory::ACCESS,
            \sprintf('%s invité dans l’équipe Kiveto (%s)', $invitation->email, self::ROLES[$role] ?? $role),
            targetUserId: $invitation->userId,
            details: ['Rôle' => self::ROLES[$role] ?? $role, 'Lien valable jusqu’au' => $invitation->expiresAt->format('d/m/Y H:i') . ' UTC'],
        );

        return $invitation;
    }

    /**
     * @throws \DomainException an unknown role, the last active administrator
     */
    public function changeRole(BackofficeProfileView $member, string $role): bool
    {
        $changed = true === $this->commandBus->dispatch(new ChangeConsoleRole($member->userId, $role));

        if ($changed) {
            $this->auditTrail->record(
                'team.role_changed',
                AuditCategory::ACCESS,
                \sprintf('Rôle de %s : %s', $member->displayName, ConsoleRole::from($role)->label()),
                targetUserId: $member->userId,
            );
        }

        return $changed;
    }

    /**
     * @throws \DomainException oneself, the last active administrator
     */
    public function switch(BackofficeProfileView $member, bool $enabled): void
    {
        $this->commandBus->dispatch(new SwitchConsoleMember($member->userId, $enabled, $this->currentAdmin->get()->id));
        $this->auditTrail->record(
            $enabled ? 'team.member_enabled' : 'team.member_disabled',
            AuditCategory::ACCESS,
            \sprintf('%s %s de la console', $member->displayName, $enabled ? 'réactivé' : 'désactivé'),
            targetUserId: $member->userId,
        );
    }

    /**
     * @return list<BackofficeProfileView>
     */
    private function team(): array
    {
        $answer = $this->queryBus->ask(new ListConsoleTeam());

        return \is_array($answer) ? array_values(array_filter($answer, static fn (mixed $m): bool => $m instanceof BackofficeProfileView)) : [];
    }
}
