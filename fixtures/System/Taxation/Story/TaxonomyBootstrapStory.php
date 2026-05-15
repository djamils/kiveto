<?php

declare(strict_types=1);

namespace App\Fixtures\System\Taxation\Story;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Taxation\Application\Command\LoadTaxRegime\LoadTaxRegime;
use App\System\Taxation\Application\Command\RegisterTaxCategory\RegisterTaxCategory;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Zenstruck\Foundry\Story;

final class TaxonomyBootstrapStory extends Story
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function build(): void
    {
        $categoriesPath = $this->projectDir . '/src/System/Taxation/Infrastructure/Resources/categories.yaml';

        /** @var array{categories: list<array{code: string, displayName: string, description: string|null}>} $data */
        $data = Yaml::parseFile($categoriesPath);

        foreach ($data['categories'] as $cat) {
            $this->commandBus->dispatch(new RegisterTaxCategory(
                TaxCategoryCode::fromString($cat['code']),
                $cat['displayName'],
                $cat['description'] ?? null,
            ));
        }

        $this->commandBus->dispatch(new LoadTaxRegime(TaxRegimeId::fromString('FR')));
    }
}
