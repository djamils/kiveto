<?php

declare(strict_types=1);

namespace App\System\Translation\Application\Port;

use App\System\Translation\Domain\ValueObject\AppScope;

interface AppScopeResolverInterface
{
    public function resolve(): AppScope;
}
