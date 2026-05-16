<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Application\Query\GetWithdrawalPeriodsByMarketingAuthorization;

readonly class WithdrawalPeriodView
{
    public function __construct(
        public string $administrationRoute,
        public string $targetSpeciesCode,
        public ?int $withdrawalPeriodValue,
        public ?string $withdrawalPeriodUnit,
        public ?string $foodProductionPurpose,
        public ?string $jurisdictionalNote,
    ) {
    }
}
