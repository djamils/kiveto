<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\DeleteDataExportFile;

use App\Shared\Application\Bus\CommandInterface;

/** The archive of an export whose 48 hours are over (routed `async`, delayed 48 h). */
final readonly class DeleteDataExportFile implements CommandInterface
{
    public function __construct(
        public string $exportId,
    ) {
    }
}
