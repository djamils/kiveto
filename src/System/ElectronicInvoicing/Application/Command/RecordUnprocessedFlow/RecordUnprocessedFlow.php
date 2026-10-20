<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordUnprocessedFlow;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A flow the synchronisation could not process (reason `unparsable` or
 * `unresolved`), or — reason null — one it finally processed.
 */
final readonly class RecordUnprocessedFlow implements CommandInterface
{
    public const string UNPARSABLE = 'unparsable';
    public const string UNRESOLVED = 'unresolved';

    public function __construct(
        public string $organisationId,
        public string $flowId,
        public ?string $reason,
    ) {
    }
}
