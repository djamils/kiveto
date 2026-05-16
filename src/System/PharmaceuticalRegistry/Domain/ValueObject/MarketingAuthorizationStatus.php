<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\ValueObject;

enum MarketingAuthorizationStatus: string
{
    case UNDER_REVIEW              = 'UNDER_REVIEW';
    case ACTIVE                    = 'ACTIVE';
    case EXCEPTIONAL_CIRCUMSTANCES = 'EXCEPTIONAL_CIRCUMSTANCES';
    case UNLIMITED                 = 'UNLIMITED';
    case WITHDRAWN                 = 'WITHDRAWN';
    case WITHDRAWN_WITH_DEROGATION = 'WITHDRAWN_WITH_DEROGATION';
    case SUSPENDED                 = 'SUSPENDED';
    case REFUSED                   = 'REFUSED';
    case ABANDONED                 = 'ABANDONED';
    case LAPSED                    = 'LAPSED';

    public function isMarketable(): bool
    {
        return match ($this) {
            self::ACTIVE, self::EXCEPTIONAL_CIRCUMSTANCES, self::UNLIMITED => true,
            self::UNDER_REVIEW, self::WITHDRAWN, self::WITHDRAWN_WITH_DEROGATION,
            self::SUSPENDED, self::REFUSED, self::ABANDONED, self::LAPSED => false,
        };
    }
}
