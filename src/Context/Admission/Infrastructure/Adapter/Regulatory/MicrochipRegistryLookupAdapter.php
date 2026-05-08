<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Adapter\Regulatory;

use App\Context\Admission\Application\Port\MicrochipRegistryLookupPort;
use App\Context\Regulatory\Application\Command\OpenMicrochipRegistryLookup\OpenMicrochipRegistryLookup;
use App\Shared\Application\Bus\CommandBusInterface;

final readonly class MicrochipRegistryLookupAdapter implements MicrochipRegistryLookupPort
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function initiateChipLookup(string $chipNumber, string $clinicId): void
    {
        $this->commandBus->dispatch(new OpenMicrochipRegistryLookup(chipNumber: $chipNumber, clinicId: $clinicId));
    }
}
