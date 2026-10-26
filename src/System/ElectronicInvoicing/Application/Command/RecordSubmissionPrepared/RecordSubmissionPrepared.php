<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RecordSubmissionPrepared;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The file of a transmission was rendered and stored: freeze it.
 */
final readonly class RecordSubmissionPrepared implements CommandInterface
{
    public function __construct(
        public string $transmissionId,
        public string $documentId,
        public string $sha256,
        public string $electronicAddressScheme,
        public string $electronicAddressIdentifier,
    ) {
    }
}
