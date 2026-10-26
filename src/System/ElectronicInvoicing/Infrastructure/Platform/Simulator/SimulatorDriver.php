<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\Shared\Domain\Time\ClockInterface;
use App\System\ElectronicInvoicing\Application\Port\ConfiguredPlatformInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\LifecycleStatusCode;
use App\System\ElectronicInvoicing\Infrastructure\Platform\PlatformSelector;

/**
 * Drives the simulated platform by hand (dev / demo / fixtures): applies the
 * pending steps synchronously, or emits a chosen status. Refused unless the
 * configured platform is the simulator.
 */
final readonly class SimulatorDriver
{
    public function __construct(
        private SimulatedFlowStoreInterface $flows,
        private ConfiguredPlatformInterface $platform,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Applies the pending steps of every simulated flow, at most $maxSteps in
     * total.
     *
     * @return int the number of statuses emitted
     */
    public function advanceAll(?int $maxSteps = null): int
    {
        $this->guard();

        $applied = 0;

        foreach ($this->flows->pending() as $flow) {
            while ((null === $maxSteps || $applied < $maxSteps) && $flow->advance($this->clock->now())) {
                ++$applied;
            }

            $this->flows->save($flow);

            if (null !== $maxSteps && $applied >= $maxSteps) {
                break;
            }
        }

        return $applied;
    }

    /**
     * Emits $statusCode on the latest flow deposited for that document.
     */
    public function emit(string $sellerSiren, string $documentNumber, int $statusCode, ?string $reason): SimulatedStatus
    {
        $this->guard();

        if (null === LifecycleStatusCode::tryFrom($statusCode)) {
            throw new \InvalidArgumentException(\sprintf('Unknown lifecycle status code %d.', $statusCode));
        }

        $flow = $this->flows->findByDocument($sellerSiren, $documentNumber)
            ?? throw new \DomainException(\sprintf('No simulated flow for document %s of seller %s.', $documentNumber, $sellerSiren));

        $status = $flow->emit($statusCode, $reason, $this->clock->now());
        $this->flows->save($flow);

        return $status;
    }

    private function guard(): void
    {
        if (PlatformSelector::SIMULATOR !== $this->platform->code()) {
            throw new \LogicException('The configured e-invoicing platform is not the simulator.');
        }
    }
}
