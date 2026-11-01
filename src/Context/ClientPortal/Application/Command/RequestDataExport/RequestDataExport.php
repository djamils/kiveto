<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\RequestDataExport;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Exporter mes données »: consumes the account's daily export and queues
 * the build. Returns the export id (string).
 */
final readonly class RequestDataExport implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
