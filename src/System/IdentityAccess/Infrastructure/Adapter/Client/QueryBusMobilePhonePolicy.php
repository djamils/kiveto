<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\IsPortalMobileNumber\IsPortalMobileNumber;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Port\MobilePhonePolicyInterface;

final readonly class QueryBusMobilePhonePolicy implements MobilePhonePolicyInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function isPortalMobile(string $phone): bool
    {
        return true === $this->queryBus->ask(new IsPortalMobileNumber($phone));
    }
}
