<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\SwitchConsoleMember;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\ChangeConsoleRole\ChangeConsoleRoleHandler;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SwitchConsoleMemberHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private BackofficeProfileStoreInterface $profiles,
        private UserSessionStoreInterface $sessions,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \DomainException an unknown member, oneself, or the last active administrator
     */
    public function __invoke(SwitchConsoleMember $command): int
    {
        $member = $this->profiles->find($command->userId) ?? throw new \DomainException('Membre de l’équipe introuvable.');
        $user   = $this->users->findByIdForUpdate(UserId::fromString($command->userId)) ?? throw new \DomainException('Membre de l’équipe introuvable.');

        if ($command->enabled) {
            $user->enableConsoleMember();
            $this->users->save($user);

            return 0;
        }

        if ($command->userId === $command->actorId) {
            throw new \DomainException('Tu ne peux pas te désactiver toi-même.');
        }

        if ($member->isAdmin() && 'ACTIVE' === $member->status && 1 >= ChangeConsoleRoleHandler::activeAdmins($this->profiles->team())) {
            throw new \DomainException('La console doit garder au moins un administrateur actif.');
        }

        $user->disableConsoleMember();
        $this->users->save($user);

        return $this->sessions->revokeAll($command->userId, null, $this->clock->now());
    }
}
