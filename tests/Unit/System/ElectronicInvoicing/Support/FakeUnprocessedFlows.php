<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Support;

use App\System\ElectronicInvoicing\Application\Port\UnprocessedFlowsInterface;

final class FakeUnprocessedFlows implements UnprocessedFlowsInterface
{
    /** @var array<string, int> */
    public array $attempts = [];

    /** @var array<string, array{reason: string, error: ?string, content: ?string}> */
    public array $quarantined = [];

    public function record(string $organisationId, string $flowId, string $reason, ?string $error, ?string $content, \DateTimeImmutable $now): int
    {
        $this->quarantined[$flowId] = ['reason' => $reason, 'error' => $error, 'content' => $content];

        return $this->attempts[$flowId] = ($this->attempts[$flowId] ?? 0) + 1;
    }

    public function toRetry(string $organisationId, int $maxAttempts): array
    {
        return array_keys(array_filter($this->attempts, static fn (int $a): bool => $a < $maxAttempts));
    }

    public function resolve(string $organisationId, string $flowId): void
    {
        unset($this->attempts[$flowId]);
    }
}
