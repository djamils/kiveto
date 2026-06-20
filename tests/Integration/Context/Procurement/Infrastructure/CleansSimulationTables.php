<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Infrastructure;

use Doctrine\DBAL\Connection;

/**
 * Trait for integration tests that write to simulated adapter tables.
 * These tables are NOT covered by DAMA DoctrineTestBundle's transaction rollback
 * because SimulatedSupplierAdapter uses raw DBAL writes (outside Doctrine EM).
 * Always call cleanSimulationTables() in tearDown().
 *
 * @phpstan-ignore trait.unused
 */
trait CleansSimulationTables
{
    abstract protected function getConnection(): Connection;

    protected function cleanSimulationTables(): void
    {
        $this->getConnection()->executeStatement('DELETE FROM procurement__simulated_orders');
        $this->getConnection()->executeStatement('DELETE FROM procurement__simulated_deliveries');
    }
}
