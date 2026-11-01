<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RequestAccountDeletion;

use App\Context\ClientPortal\Application\Port\AccountDeletionJobsInterface;
use App\Context\ClientPortal\Application\Port\DeletionCodeCheck;
use App\Context\ClientPortal\Application\Port\PortalAccountErasureInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RequestAccountDeletionHandler
{
    public function __construct(
        private PortalAccountErasureInterface $accounts,
        private AccountDeletionJobsInterface $jobs,
    ) {
    }

    public function __invoke(RequestAccountDeletion $command): DeletionCodeCheck
    {
        $check = $this->accounts->verifyDeletionCode($command->userId, $command->challengeId, $command->code);

        if (DeletionCodeCheck::VERIFIED !== $check) {
            return $check;
        }

        $this->accounts->disable($command->userId);
        $this->jobs->queueDeletion($command->userId);

        return $check;
    }
}
