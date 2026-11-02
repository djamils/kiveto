<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\SweepExpiredDataExports;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Nightly (schedule `client_portal`): deletes the archives of the ready
 * exports past their 48 hours whose delayed deletion was lost, and gives
 * up the exports stuck in `pending`.
 */
final readonly class SweepExpiredDataExports implements CommandInterface
{
}
