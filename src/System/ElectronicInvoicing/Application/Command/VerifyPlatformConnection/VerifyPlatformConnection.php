<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The platform confirmed the organisation's onboarding.
 */
final readonly class VerifyPlatformConnection implements CommandInterface
{
    public function __construct(public string $organisationId)
    {
    }
}
