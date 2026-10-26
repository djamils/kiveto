<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Domain\Port\OutboundFlow;

/**
 * A file deposited on the simulated platform and the statuses the platform
 * emitted for it so far.
 *
 * Every emitted status is exposed as its own platform flow, identified by
 * statusFlowId(): on a real platform a lifecycle message is a flow of its
 * own, and one listed entry per status keeps the (updatedAt, flowId) cursor
 * meaningful. Statuses get strictly increasing timestamps (one second apart
 * at least), so they are always read back in the order they were emitted.
 */
final class SimulatedFlow
{
    /**
     * @param list<SimulatedStatus> $statuses
     */
    public function __construct(
        public readonly string $flowId,
        public readonly string $organisationId,
        public readonly string $trackingId,
        public readonly string $sha256,
        public readonly string $documentNumber,
        public readonly string $typeCode,
        public readonly ?\DateTimeImmutable $issueDate,
        public readonly string $sellerSiren,
        public readonly ?string $buyerSiren,
        public readonly SimulationScenario $scenario,
        public readonly string $file,
        public readonly \DateTimeImmutable $createdAt,
        private array $statuses = [],
        private ?int $nextStep = 0,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
    }

    /**
     * A file just deposited: its first status is emitted at once.
     */
    public static function deposit(string $flowId, OutboundFlow $flow, DepositedDocument $document, SimulationScenario $scenario, \DateTimeImmutable $now): self
    {
        $deposited = new self(
            flowId: $flowId,
            organisationId: $flow->organisationId,
            trackingId: $flow->trackingId,
            sha256: $flow->sha256,
            documentNumber: $document->number ?? $flow->trackingId,
            typeCode: $document->typeCode ?? '380',
            issueDate: $document->issueDate,
            sellerSiren: $document->sellerSiren ?? $flow->organisationId,
            buyerSiren: $document->buyerSiren,
            scenario: $scenario,
            file: $flow->content,
            createdAt: self::seconds($now),
        );
        $deposited->advance($now);

        return $deposited;
    }

    /**
     * Emits the next status of the scenario.
     *
     * @return bool false when the scenario was already complete
     */
    public function advance(\DateTimeImmutable $now): bool
    {
        $steps = $this->scenario->steps();

        if (null === $this->nextStep || !isset($steps[$this->nextStep])) {
            return false;
        }

        [$code, $reason] = $steps[$this->nextStep];
        $this->emit($code->value, $reason, $now);
        $this->nextStep = isset($steps[$this->nextStep + 1]) ? $this->nextStep + 1 : null;

        return true;
    }

    /**
     * Emits a status outside the scenario (manual simulation).
     */
    public function emit(int $code, ?string $reason, \DateTimeImmutable $now): SimulatedStatus
    {
        $at = self::seconds($now);

        if (null !== $this->updatedAt && $at <= $this->updatedAt) {
            $at = $this->updatedAt->modify('+1 second');
        }

        $status           = new SimulatedStatus($code, $reason, $at);
        $this->statuses[] = $status;
        $this->updatedAt  = $at;

        return $status;
    }

    public function isPending(): bool
    {
        return null !== $this->nextStep;
    }

    public function nextStep(): ?int
    {
        return $this->nextStep;
    }

    /**
     * @return list<SimulatedStatus>
     */
    public function statuses(): array
    {
        return $this->statuses;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt;
    }

    public function statusFlowId(int $index): string
    {
        return \sprintf('%s-%02d', $this->flowId, $index + 1);
    }

    /**
     * The status published as $statusFlowId, or null when it is not one of this flow's.
     */
    public function statusFor(string $statusFlowId): ?SimulatedStatus
    {
        foreach ($this->statuses as $index => $status) {
            if ($this->statusFlowId($index) === $statusFlowId) {
                return $status;
            }
        }

        return null;
    }

    /**
     * The flow id a status flow id belongs to.
     */
    public static function depositFlowId(string $statusFlowId): string
    {
        $separator = strrpos($statusFlowId, '-');

        return false === $separator ? $statusFlowId : substr($statusFlowId, 0, $separator);
    }

    private static function seconds(\DateTimeImmutable $at): \DateTimeImmutable
    {
        $utc = $at->setTimezone(new \DateTimeZone('UTC'));

        return $utc->setTime((int) $utc->format('H'), (int) $utc->format('i'), (int) $utc->format('s'));
    }
}
