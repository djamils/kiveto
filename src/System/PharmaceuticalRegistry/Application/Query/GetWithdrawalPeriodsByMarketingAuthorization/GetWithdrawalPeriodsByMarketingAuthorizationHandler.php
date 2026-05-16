<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetWithdrawalPeriodsByMarketingAuthorization;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetWithdrawalPeriodsByMarketingAuthorizationHandler
{
    /**
     * @return WithdrawalPeriodView[]
     */
    public function __invoke(GetWithdrawalPeriodsByMarketingAuthorization $query): array
    {
        return [];
    }
}
