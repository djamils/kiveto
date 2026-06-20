<?php

declare(strict_types=1);

namespace App\Fixtures\Context\Procurement\Story;

use App\Fixtures\Context\Procurement\Factory\SupplierEntityFactory;
use Zenstruck\Foundry\Story;

/**
 * Seeds the three main simulated suppliers used in demo data.
 */
final class ThreeSimulatedSuppliersStory extends Story
{
    public function build(): void
    {
        SupplierEntityFactory::createOne([
            'name'              => 'Centravet',
            'code'              => 'CENTRAVET',
            'type'              => 'CENTRALE',
            'countryCode'       => 'FR',
            'defaultCurrency'   => 'EUR',
            'integrationMode'   => 'SIMULATION',
            'adapterIdentifier' => 'simulated',
            'status'            => 'ACTIVE',
        ]);

        SupplierEntityFactory::createOne([
            'name'              => 'Alcyon',
            'code'              => 'ALCYON',
            'type'              => 'LABORATORY',
            'countryCode'       => 'FR',
            'defaultCurrency'   => 'EUR',
            'integrationMode'   => 'SIMULATION',
            'adapterIdentifier' => 'simulated',
            'status'            => 'ACTIVE',
        ]);

        SupplierEntityFactory::createOne([
            'name'              => 'Hippocampe',
            'code'              => 'HIPPOCAMPE',
            'type'              => 'DIVERS',
            'countryCode'       => 'FR',
            'defaultCurrency'   => 'EUR',
            'integrationMode'   => 'SIMULATION',
            'adapterIdentifier' => 'simulated',
            'status'            => 'ACTIVE',
        ]);
    }
}
