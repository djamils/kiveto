<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\ReconcilePublishedDocuments;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Scheduled every 5 minutes: asks again for the files expected for more
 * than 10 minutes (a lost message, a worker down), in bounded batches.
 */
final readonly class ReconcilePublishedDocuments implements CommandInterface
{
}
