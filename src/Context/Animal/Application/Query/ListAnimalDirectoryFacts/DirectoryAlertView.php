<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\ListAnimalDirectoryFacts;

final readonly class DirectoryAlertView
{
    public function __construct(
        public string $kind,
        public string $label,
    ) {
    }
}
