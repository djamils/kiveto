<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\IdentityAccess;

use App\Context\Clinic\Application\Port\InvitationStatusReaderInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;

final readonly class QueryBusInvitationStatusReader implements InvitationStatusReaderInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function statuses(string $clinicId, array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $statuses = $this->queryBus->ask(new GetInvitationStatuses($clinicId, $userIds));
        \assert(\is_array($statuses));

        $answer = [];

        foreach ($statuses as $userId => $status) {
            if (\is_string($status)) {
                $answer[(string) $userId] = $status;
            }
        }

        return $answer;
    }
}
