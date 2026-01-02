<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\ListLocales;

readonly class ListLocales
{
    public function __construct(
        public ?string $scope = null,
        public ?string $domain = null,
    ) {
    }
}
