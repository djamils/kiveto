<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\GetCatalog;

readonly class GetCatalog
{
    public function __construct(
        public string $scope,
        public string $locale,
        public string $domain,
    ) {
    }
}
