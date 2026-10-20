<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedFlow;
use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatedFlowStoreInterface;

final class InMemorySimulatedFlowStore implements SimulatedFlowStoreInterface
{
    /** @var array<string, SimulatedFlow> */
    private array $flows = [];

    public function save(SimulatedFlow $flow): void
    {
        $this->flows[$flow->flowId] = clone $flow;
    }

    public function find(string $flowId): ?SimulatedFlow
    {
        return isset($this->flows[$flowId]) ? clone $this->flows[$flowId] : null;
    }

    public function findByDeposit(string $trackingId, string $sha256): ?SimulatedFlow
    {
        return $this->first(static fn (SimulatedFlow $f): bool => $f->trackingId === $trackingId && $f->sha256 === $sha256);
    }

    public function findByDocument(string $sellerSiren, string $documentNumber): ?SimulatedFlow
    {
        $matches = array_filter($this->flows, static fn (SimulatedFlow $f): bool => $f->sellerSiren === $sellerSiren && $f->documentNumber === $documentNumber);
        $last    = end($matches);

        return false === $last ? null : clone $last;
    }

    public function forOrganisation(string $organisationId): array
    {
        return array_values(array_map(
            static fn (SimulatedFlow $f): SimulatedFlow => clone $f,
            array_filter($this->flows, static fn (SimulatedFlow $f): bool => $f->organisationId === $organisationId),
        ));
    }

    public function pending(): array
    {
        return array_values(array_map(
            static fn (SimulatedFlow $f): SimulatedFlow => clone $f,
            array_filter($this->flows, static fn (SimulatedFlow $f): bool => $f->isPending()),
        ));
    }

    public function count(): int
    {
        return \count($this->flows);
    }

    /**
     * @param callable(SimulatedFlow): bool $predicate
     */
    private function first(callable $predicate): ?SimulatedFlow
    {
        foreach ($this->flows as $flow) {
            if ($predicate($flow)) {
                return clone $flow;
            }
        }

        return null;
    }
}
