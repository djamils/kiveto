<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\IsPortalMobileNumber;

use App\Context\Client\Domain\ValueObject\MobileNumberPolicy;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IsPortalMobileNumberHandler
{
    public function __invoke(IsPortalMobileNumber $query): bool
    {
        return MobileNumberPolicy::isMobile($query->phone);
    }
}
