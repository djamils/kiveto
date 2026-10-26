<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

/**
 * One lifecycle status emitted by the simulated platform.
 */
final readonly class SimulatedStatus
{
    public function __construct(
        public int $code,
        public ?string $reason,
        public \DateTimeImmutable $at,
    ) {
    }

    /**
     * @return array{code: int, reason: ?string, at: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'reason' => $this->reason, 'at' => $this->at->format(\DateTimeInterface::ATOM)];
    }

    /**
     * @param array{code: int, reason: ?string, at: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['code'], $data['reason'], new \DateTimeImmutable($data['at']));
    }
}
