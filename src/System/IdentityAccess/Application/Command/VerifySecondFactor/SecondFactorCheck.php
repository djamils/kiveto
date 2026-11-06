<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifySecondFactor;

enum SecondFactorCheck: string
{
    case APP_CODE = 'app_code';

    case BACKUP_CODE = 'backup_code';

    case INVALID = 'invalid';

    case NOT_ENABLED = 'not_enabled';

    public function isAccepted(): bool
    {
        return self::APP_CODE === $this || self::BACKUP_CODE === $this;
    }
}
