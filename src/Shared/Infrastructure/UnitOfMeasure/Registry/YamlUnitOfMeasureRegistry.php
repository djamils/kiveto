<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\UnitOfMeasure\Registry;

use App\Shared\Domain\UnitOfMeasure\Exception\UnknownUnitOfMeasureException;
use App\Shared\Domain\UnitOfMeasure\Service\UnitOfMeasureRegistry;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitCategory;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitDefinition;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlUnitOfMeasureRegistry implements UnitOfMeasureRegistry
{
    /** @var array<string, UnitDefinition> */
    private array $units;

    public function __construct(string $catalogPath)
    {
        $data = Yaml::parseFile($catalogPath);

        if (!\is_array($data) || !isset($data['units']) || !\is_array($data['units'])) {
            throw new \RuntimeException(\sprintf(
                'Invalid units catalogue at "%s": missing "units" key.',
                $catalogPath,
            ));
        }

        $units = [];

        foreach ($data['units'] as $code => $entry) {
            \assert(\is_string($code) && \is_array($entry));
            $this->assertValidEntry($catalogPath, $code, $entry);

            /** @var string $categoryRaw */
            $categoryRaw = $entry['category'];

            /** @var string|null $baseFactor */
            $baseFactor = $entry['base_factor'] ?? null;

            $units[$code] = new UnitDefinition(
                UnitOfMeasure::fromString($code),
                UnitCategory::from($categoryRaw),
                $baseFactor,
            );
        }

        $this->units = $units;
    }

    public function resolve(UnitOfMeasure $unit): UnitDefinition
    {
        $key = $unit->toString();

        if (!isset($this->units[$key])) {
            throw new UnknownUnitOfMeasureException($key);
        }

        return $this->units[$key];
    }

    public function has(UnitOfMeasure $unit): bool
    {
        return isset($this->units[$unit->toString()]);
    }

    /** @return list<UnitDefinition> */
    public function all(): array
    {
        return array_values($this->units);
    }

    private function assertValidEntry(string $catalogPath, string $code, mixed $entry): void
    {
        $hasValidBaseFactor = !\is_array($entry)
            || !\array_key_exists('base_factor', $entry)
            || null === $entry['base_factor']
            || \is_string($entry['base_factor']);

        $invalid = !\is_array($entry)
            || !\is_string($entry['category'] ?? null)
            || !$hasValidBaseFactor;

        if ($invalid) {
            throw new \RuntimeException(\sprintf(
                'Invalid unit entry "%s" in catalogue "%s".',
                $code,
                $catalogPath,
            ));
        }
    }
}
