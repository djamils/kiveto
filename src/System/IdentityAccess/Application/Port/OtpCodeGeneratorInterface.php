<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Port;

interface OtpCodeGeneratorInterface
{
    /** Six random digits, zero-padded. */
    public function generate(): string;
}
