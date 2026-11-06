<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\Console;

/**
 * One marketing authorisation an import added, changed or withdrew.
 */
final readonly class RegistryDiffLine
{
    /**
     * @param list<string> $changedFields
     */
    public function __construct(
        public string $identifier,
        public string $kind,
        public ?string $name,
        public ?string $laboratory,
        public array $changedFields,
    ) {
    }
}
