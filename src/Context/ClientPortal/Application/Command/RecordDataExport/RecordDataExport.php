<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RecordDataExport;

use App\Shared\Application\Bus\CommandInterface;

/** The outcome of a build: the archive is stored (`built`), or the build failed. */
final readonly class RecordDataExport implements CommandInterface
{
    public function __construct(
        public string $exportId,
        public bool $built,
    ) {
    }
}
