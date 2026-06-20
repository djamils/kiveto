<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Service;

use App\Context\Procurement\Application\Port\SupplierIntegrationAdapterInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;

final class SupplierIntegrationDispatcher
{
    /** @var list<SupplierIntegrationAdapterInterface> */
    private array $adapters;

    /**
     * @param iterable<SupplierIntegrationAdapterInterface> $adapters
     */
    public function __construct(iterable $adapters)
    {
        $this->adapters = iterator_to_array($adapters, false);
    }

    public function dispatch(Supplier $supplier): SupplierIntegrationAdapterInterface
    {
        $identifier = $supplier->adapterIdentifier();

        // Try to find adapter by its unique identifier.
        if (null !== $identifier) {
            foreach ($this->adapters as $adapter) {
                if ($adapter->identifier() === $identifier) {
                    return $adapter;
                }
            }
        }

        // If mode is SIMULATION, fall back to the SimulatedSupplierAdapter.
        if (SupplierIntegrationMode::SIMULATION === $supplier->integrationMode()) {
            foreach ($this->adapters as $adapter) {
                if (SupplierIntegrationMode::SIMULATION === $adapter->mode()) {
                    return $adapter;
                }
            }
        }

        throw new \RuntimeException(\sprintf(
            'No supplier integration adapter found for supplier "%s" with mode "%s" and adapter identifier "%s".',
            $supplier->id()->toString(),
            $supplier->integrationMode()->value,
            $identifier ?? 'null',
        ));
    }
}
