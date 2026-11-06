<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegenerateBackupCodes;

use App\System\IdentityAccess\Application\Service\BackupCodes;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegenerateBackupCodesHandler
{
    public function __construct(
        private SecondFactorRepositoryInterface $factors,
        private BackupCodes $backupCodes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(RegenerateBackupCodes $command): array
    {
        $factor = $this->factors->findByUserIdForUpdate(UserId::fromString($command->userId))
            ?? throw new \LogicException('This account has no second factor.');

        $codes = $this->backupCodes->fresh();
        $factor->replaceBackupCodes($codes['hashes']);
        $this->factors->save($factor);

        return $codes['plain'];
    }
}
