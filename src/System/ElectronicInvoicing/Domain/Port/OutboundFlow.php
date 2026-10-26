<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Port;

/**
 * A file deposited on the platform for an organisation.
 */
final readonly class OutboundFlow
{
    public function __construct(
        public string $organisationId,
        public string $trackingId,
        public string $fileName,
        public string $content,
        public string $sha256,
        public string $syntax,
        public string $processingRule,
    ) {
    }
}
