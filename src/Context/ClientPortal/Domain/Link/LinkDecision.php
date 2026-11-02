<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Link;

/**
 * The record of a clinic's answer to a « Rattachement » (S6-F3 audit
 * trail): who confirmed or rejected which pending links of an owner account,
 * and when. One decision per answer, listing the client files it actually
 * moved out of `pending` — the links themselves keep their own status; the
 * decision is what « Demandes » shows under « Traitées » / « Refusées ».
 * Written once, never changed.
 */
final class LinkDecision
{
    private LinkDecisionId $id;
    private string $clinicId;
    private string $userId;
    private LinkDecisionOutcome $outcome;

    /** @var list<string> */
    private array $clientIds;
    private \DateTimeImmutable $decidedAt;
    private string $decidedByUserId;
    private string $decidedByName;

    private function __construct()
    {
    }

    /**
     * @param list<string> $clientIds the files the answer decided
     * @param string       $byName    the staff member's name, shown to colleagues
     */
    public static function record(
        LinkDecisionId $id,
        string $clinicId,
        string $userId,
        LinkDecisionOutcome $outcome,
        array $clientIds,
        \DateTimeImmutable $now,
        string $byUserId,
        string $byName,
    ): self {
        return self::reconstitute($id, $clinicId, $userId, $outcome, $clientIds, $now, $byUserId, $byName);
    }

    /**
     * @param list<string> $clientIds
     */
    public static function reconstitute(
        LinkDecisionId $id,
        string $clinicId,
        string $userId,
        LinkDecisionOutcome $outcome,
        array $clientIds,
        \DateTimeImmutable $decidedAt,
        string $decidedByUserId,
        string $decidedByName,
    ): self {
        $decision                  = new self();
        $decision->id              = $id;
        $decision->clinicId        = $clinicId;
        $decision->userId          = $userId;
        $decision->outcome         = $outcome;
        $decision->clientIds       = $clientIds;
        $decision->decidedAt       = $decidedAt;
        $decision->decidedByUserId = $decidedByUserId;
        $decision->decidedByName   = $decidedByName;

        return $decision;
    }

    public function id(): LinkDecisionId
    {
        return $this->id;
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    /** The owner account (PORTAL user) whose links were decided. */
    public function userId(): string
    {
        return $this->userId;
    }

    public function outcome(): LinkDecisionOutcome
    {
        return $this->outcome;
    }

    /** @return list<string> */
    public function clientIds(): array
    {
        return $this->clientIds;
    }

    public function decidedAt(): \DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function decidedByUserId(): string
    {
        return $this->decidedByUserId;
    }

    public function decidedByName(): string
    {
        return $this->decidedByName;
    }
}
