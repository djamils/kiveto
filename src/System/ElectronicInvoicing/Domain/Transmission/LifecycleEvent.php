<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

/**
 * A lifecycle status the platform reported, with the flow it came in.
 */
final readonly class LifecycleEvent
{
    public function __construct(
        public LifecycleStatusCode $code,
        public ?string $reasonCode,
        public ?string $reasonText,
        public string $sourceFlowId,
        public \DateTimeImmutable $at,
    ) {
    }

    public function sameAs(self $other): bool
    {
        return $this->code === $other->code && $this->sourceFlowId === $other->sourceFlowId;
    }
}
