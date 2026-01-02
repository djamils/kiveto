<?php

declare(strict_types=1);

namespace App\Translation\Application\Port;

use App\Translation\Domain\ValueObject\Locale;

interface LocaleResolverInterface
{
    public function resolve(): Locale;
}
