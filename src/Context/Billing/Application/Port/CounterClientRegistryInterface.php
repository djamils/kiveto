<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Context\Billing\Application\Command\RecordCounterSale\CounterSaleNewClient;

/**
 * Creates, in the client file of the clinic, the record of a newcomer the
 * desk sells to.
 */
interface CounterClientRegistryInterface
{
    /**
     * @return string the id of the client created
     */
    public function register(string $clinicId, CounterSaleNewClient $client): string;
}
