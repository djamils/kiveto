<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetDataExportFile;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The archive of one of the account's exports, to sign its download.
 * Answers ?DataExportFile: null unless the export is the account's and
 * ready (within its 48 hours).
 */
final readonly class GetDataExportFile implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $exportId,
    ) {
    }
}
