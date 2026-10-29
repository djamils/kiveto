<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security;

use App\System\IdentityAccess\Application\Port\OtpCodeGeneratorInterface;

final readonly class RandomOtpCodeGenerator implements OtpCodeGeneratorInterface
{
    public function generate(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', \STR_PAD_LEFT);
    }
}
