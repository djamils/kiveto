<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifySecondFactor;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Service\BackupCodes;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VerifySecondFactorHandler
{
    public function __construct(
        private SecondFactorRepositoryInterface $factors,
        private BackupCodes $backupCodes,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VerifySecondFactor $command): SecondFactorCheck
    {
        $factor = $this->factors->findByUserIdForUpdate(UserId::fromString($command->userId));

        if (null === $factor) {
            return SecondFactorCheck::NOT_ENABLED;
        }

        $now  = $this->clock->now();
        $code = trim($command->code);

        $check = match (true) {
            1 === preg_match('/^\d[\d ]{4,}\d$/', $code) => $factor->verifyCode($code, $now) ? SecondFactorCheck::APP_CODE : SecondFactorCheck::INVALID,
            '' !== $code                                 => $factor->useBackupCode($this->backupCodes->hash($code), $now) ? SecondFactorCheck::BACKUP_CODE : SecondFactorCheck::INVALID,
            default                                      => SecondFactorCheck::INVALID,
        };

        if ($check->isAccepted()) {
            $this->factors->save($factor);
        }

        return $check;
    }
}
