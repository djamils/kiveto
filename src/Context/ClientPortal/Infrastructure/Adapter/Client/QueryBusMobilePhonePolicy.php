<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Client;

use App\Context\Client\Application\Query\IsPortalMobileNumber\IsPortalMobileNumber;
use App\Context\ClientPortal\Application\Port\MobilePhonePolicyInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/** Client's MobileNumberPolicy, the rule the Portal API applies as well. */
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
