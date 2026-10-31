<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Support;

use App\Context\ClientPortal\Application\Port\PublishableSource;

final class SourcePages
{
    /**
     * Keyset page of sources ordered by id.
     *
     * @param list<PublishableSource> $sources
     *
     * @return list<PublishableSource>
     */
    public static function after(array $sources, ?string $afterId, int $limit): array
    {
        $after = array_values(array_filter(
            $sources,
            static fn (PublishableSource $s): bool => null === $afterId || strcmp($s->id, $afterId) > 0,
        ));

        return \array_slice($after, 0, $limit);
    }
}
