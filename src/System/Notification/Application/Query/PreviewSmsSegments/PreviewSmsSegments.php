<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Query\PreviewSmsSegments;

use App\Shared\Application\Bus\QueryInterface;

/**
 * How many SMS segments $count messages of this purpose would consume,
 * rendered with these parameters as the clinic would send them (its name
 * and phone are part of the text) — e.g. the bulk unpaid-reminder
 * confirmation. 0 when the clinic has no messaging profile (nothing would
 * be sent).
 */
final readonly class PreviewSmsSegments implements QueryInterface
{
    /**
     * @param array<string, scalar|list<scalar>> $parameters exactly the parameters of the purpose's SMS template
     */
    public function __construct(
        public string $clinicId,
        public string $purpose,
        public array $parameters,
        public int $count = 1,
    ) {
    }
}
