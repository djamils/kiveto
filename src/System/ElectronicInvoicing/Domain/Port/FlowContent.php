<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

/**
 * The file of a flow: a lifecycle message (CDAR) for an outbound document.
 */
final readonly class FlowContent
{
    public function __construct(
        public string $flowId,
        public string $content,
    ) {
    }
}
