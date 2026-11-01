<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BuildDataExport;

use App\Shared\Application\Bus\ExternalApiCommandInterface;

/**
 * Builds and stores the archive of an export (the summary PDF goes through
 * Gotenberg), outside any transaction, then records the outcome through
 * the short RecordDataExport command.
 */
final readonly class BuildDataExport implements ExternalApiCommandInterface
{
    public function __construct(
        public string $exportId,
    ) {
    }
}
