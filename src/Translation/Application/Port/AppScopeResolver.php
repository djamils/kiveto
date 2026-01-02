<?php

declare(strict_types=1);

namespace App\Translation\Application\Port;

use App\Translation\Domain\Model\ValueObject\AppScope;

interface AppScopeResolver
{
    public function resolve(): AppScope;
}
