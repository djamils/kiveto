<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Command\ManuallyMarkMarketingAuthorizationWithdrawn;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ManuallyMarkMarketingAuthorizationWithdrawn implements CommandInterface
{
    public function __construct(
        public string $marketingAuthorizationId,
        public string $reason,
    ) {
    }
}
