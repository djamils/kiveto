<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\RegimeLoader;

use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RegimeFileLocator
{
    private string $resourcesDir;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
    ) {
        $this->resourcesDir = $projectDir . '/src/System/Taxation/Infrastructure/Resources';
    }

    public function locate(TaxRegimeId $regimeId): string
    {
        return $this->resourcesDir . '/regimes/' . strtolower($regimeId->toString()) . '.yaml';
    }

    /** @return list<string> */
    public function findAll(): array
    {
        $files = glob($this->resourcesDir . '/regimes/*.yaml');

        return false === $files ? [] : $files;
    }
}
