<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Domain\Link\LinkDecision;
use App\Context\ClientPortal\Domain\Link\LinkDecisionRepositoryInterface;

final class InMemoryLinkDecisions implements LinkDecisionRepositoryInterface
{
    /** @var array<string, LinkDecision> */
    public array $decisions = [];

    public function save(LinkDecision $decision): void
    {
        $this->decisions[$decision->id()->toString()] = $decision;
    }

    public function findInClinic(string $clinicId, string $decisionId): ?LinkDecision
    {
        $decision = $this->decisions[$decisionId] ?? null;

        return null !== $decision && $decision->clinicId() === $clinicId ? $decision : null;
    }

    public function findByClinic(string $clinicId, int $limit): array
    {
        $rows = array_values(array_filter($this->decisions, static fn (LinkDecision $d): bool => $d->clinicId() === $clinicId));
        usort($rows, static fn (LinkDecision $a, LinkDecision $b): int => [$b->decidedAt(), $b->id()->toString()] <=> [$a->decidedAt(), $a->id()->toString()]);

        return \array_slice($rows, 0, $limit);
    }
}
