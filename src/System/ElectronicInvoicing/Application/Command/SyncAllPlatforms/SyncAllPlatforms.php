<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\SyncAllPlatforms;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The periodic tick: synchronise every polled organisation and resend what
 * is overdue.
 */
final readonly class SyncAllPlatforms implements CommandInterface
{
}
