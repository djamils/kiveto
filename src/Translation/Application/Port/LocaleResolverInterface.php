<?php

declare(strict_types=1);

namespace App\Translation\Application\Port;

use App\Shared\Domain\Localization\Locale;

interface LocaleResolverInterface
{
    public function resolve(): Locale;
}
