<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator;

/**
 * Persistence of the simulated platform's flows.
 */
interface SimulatedFlowStoreInterface
{
    public function save(SimulatedFlow $flow): void;

    public function find(string $flowId): ?SimulatedFlow;

    public function findByDeposit(string $trackingId, string $sha256): ?SimulatedFlow;

    /**
     * The latest flow deposited for that document, if any.
     */
    public function findByDocument(string $sellerSiren, string $documentNumber): ?SimulatedFlow;

    /**
     * @return list<SimulatedFlow>
     */
    public function forOrganisation(string $organisationId): array;

    /**
     * Flows whose scenario still has statuses to emit, oldest first.
     *
     * @return list<SimulatedFlow>
     */
    public function pending(): array;
}
