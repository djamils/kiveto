<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListDirectoryMatches;

final readonly class DirectoryMatches
{
    /**
     * @param array<string, ?string> $owners matching animal id => its primary owner, if any
     */
    public function __construct(
        public array $owners,
    ) {
    }

    /**
     * @return list<string>
     */
    public function animalIds(): array
    {
        return array_keys($this->owners);
    }

    /**
     * The primary owners of the matching animals, once each.
     *
     * @return list<string>
     */
    public function ownerIds(): array
    {
        return array_values(array_unique(array_filter($this->owners, static fn (?string $owner): bool => null !== $owner)));
    }
}
