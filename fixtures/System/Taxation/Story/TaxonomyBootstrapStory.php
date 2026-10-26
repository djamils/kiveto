<?php

declare(strict_types=1);

namespace App\Fixtures\System\Taxation\Story;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Taxation\Application\Command\LoadTaxRegime\LoadTaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use App\System\Taxation\Infrastructure\Catalogue\YamlTaxCategoryCatalogue;
use Zenstruck\Foundry\Story;

final class TaxonomyBootstrapStory extends Story
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly YamlTaxCategoryCatalogue $catalogue,
    ) {
    }

    public function build(): void
    {
        foreach ($this->catalogue->commands() as $command) {
            $this->commandBus->dispatch($command);
        }

        $this->commandBus->dispatch(new LoadTaxRegime(TaxRegimeId::fromString('FR')));
    }
}
