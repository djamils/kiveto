<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\System\ElectronicInvoicing\Application\Port\SubmissionScheduleInterface;

final class FakeSchedule implements SubmissionScheduleInterface
{
    /**
     * @param list<string> $prepared
     * @param list<string> $stale
     * @param list<string> $waiting
     * @param list<string> $markable
     */
    public function __construct(
        public array $prepared = [],
        public array $stale = [],
        public array $waiting = [],
        public array $markable = [],
    ) {
    }

    public function preparedWithoutConnection(string $organisationId): array
    {
        return $this->prepared;
    }

    public function staleRequested(\DateTimeImmutable $requestedBefore): array
    {
        return $this->stale;
    }

    public function organisationsWaitingForConnection(): array
    {
        return $this->waiting;
    }

    public function markSubmissionRequested(array $transmissionIds, \DateTimeImmutable $now, \DateTimeImmutable $requestedBefore): array
    {
        return array_values(array_intersect($transmissionIds, $this->markable));
    }
}
