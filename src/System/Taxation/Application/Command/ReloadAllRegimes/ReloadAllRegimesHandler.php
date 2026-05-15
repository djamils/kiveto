<?php

declare(strict_types=1);

namespace App\System\Taxation\Application\Command\ReloadAllRegimes;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Taxation\Application\Command\LoadTaxRegime\LoadTaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\RegimeLoader\RegimeFileLocator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReloadAllRegimesHandler
{
    public function __construct(
        private RegimeFileLocator $locator,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(ReloadAllRegimes $command): void
    {
        $files = $this->locator->findAll();

        foreach ($files as $file) {
            $regimeId = TaxRegimeId::fromString(strtoupper(pathinfo($file, \PATHINFO_FILENAME)));
            $this->commandBus->dispatch(new LoadTaxRegime($regimeId));
        }
    }
}
