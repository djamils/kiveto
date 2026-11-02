<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Link;

interface LinkDecisionRepositoryInterface
{
    public function save(LinkDecision $decision): void;

    /** One decision of the clinic; null for an unknown id or another clinic's. */
    public function findInClinic(string $clinicId, string $decisionId): ?LinkDecision;

    /**
     * The clinic's decisions, the latest first, at most `$limit`.
     *
     * @return list<LinkDecision>
     */
    public function findByClinic(string $clinicId, int $limit): array;
}
