<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ChangeConsoleRole;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Query\GetBackofficeProfile\BackofficeProfileView;
use App\System\IdentityAccess\Domain\ValueObject\ConsoleRole;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeConsoleRoleHandler
{
    public function __construct(
        private BackofficeProfileStoreInterface $profiles,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException an unknown member or role, or the last active administrator made support
     */
    public function __invoke(ChangeConsoleRole $command): bool
    {
        $role   = ConsoleRole::tryFrom($command->role) ?? throw new \DomainException('Choisis un rôle : administrateur ou support.');
        $member = $this->profiles->find($command->userId) ?? throw new \DomainException('Membre de l’équipe introuvable.');

        if ($member->role === $role->value) {
            return false;
        }

        if (ConsoleRole::SUPPORT === $role && $member->isAdmin() && 'ACTIVE' === $member->status && 1 >= self::activeAdmins($this->profiles->team())) {
            throw new \DomainException('La console doit garder au moins un administrateur actif.');
        }

        $this->profiles->saveRole($command->userId, $role, $this->clock->now());

        return true;
    }

    /**
     * @param list<BackofficeProfileView> $team
     */
    public static function activeAdmins(array $team): int
    {
        return \count(array_filter($team, static fn (BackofficeProfileView $m): bool => $m->isAdmin() && 'ACTIVE' === $m->status));
    }
}
