<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Domain\Link\LinkDecision;
use App\Context\ClientPortal\Domain\Link\LinkDecisionId;
use App\Context\ClientPortal\Domain\Link\LinkDecisionOutcome;
use App\Context\ClientPortal\Domain\Link\LinkDecisionRepositoryInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;

/**
 * Records a « Rattachement » answer once it decided at least one file: an
 * answer that found nothing pending any more (a colleague was first, a
 * replay) leaves no trace, so the request is listed once.
 */
final readonly class LinkDecisionRecorder
{
    public function __construct(
        private LinkDecisionRepositoryInterface $decisions,
        private UuidGeneratorInterface $uuids,
    ) {
    }

    /**
     * @param list<string> $clientIds the files the answer moved out of `pending`
     */
    public function record(string $clinicId, string $userId, LinkDecisionOutcome $outcome, array $clientIds, \DateTimeImmutable $now, string $byUserId, string $byName): void
    {
        if ([] === $clientIds) {
            return;
        }

        $this->decisions->save(LinkDecision::record(LinkDecisionId::fromString($this->uuids->generate()), $clinicId, $userId, $outcome, $clientIds, $now, $byUserId, $byName));
    }
}
