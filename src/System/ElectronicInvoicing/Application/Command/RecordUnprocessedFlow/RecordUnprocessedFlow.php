<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordUnprocessedFlow;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A flow the synchronisation could not process — reason `unfetchable`,
 * `unparsable` or `unresolved`, with the error and the raw content when it
 * was read — or, reason null, one it finally processed.
 */
final readonly class RecordUnprocessedFlow implements CommandInterface
{
    public const string UNFETCHABLE = 'unfetchable';
    public const string UNPARSABLE  = 'unparsable';
    public const string UNRESOLVED  = 'unresolved';

    public function __construct(
        public string $organisationId,
        public string $flowId,
        public ?string $reason,
        public ?string $error = null,
        public ?string $content = null,
    ) {
    }
}
