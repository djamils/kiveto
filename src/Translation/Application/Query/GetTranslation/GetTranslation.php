<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\GetTranslation;

readonly class GetTranslation
{
    public function __construct(
        public string $scope,
        public string $locale,
        public string $domain,
        public string $key,
    ) {
    }
}
