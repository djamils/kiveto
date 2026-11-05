<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Bus\Messenger;

use App\Shared\Application\Bus\RequestCacheableQueryInterface;

/** A request-cacheable query of the query bus tests. */
final readonly class CacheableProbe implements RequestCacheableQueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
