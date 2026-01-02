<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\ListDomains;

readonly class ListDomains
{
    public function __construct(
        public ?string $scope = null,
        public ?string $locale = null,
    ) {
    }
}
