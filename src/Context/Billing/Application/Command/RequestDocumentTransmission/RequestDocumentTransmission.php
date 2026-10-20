<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RequestDocumentTransmission;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Hand an issued professional document to the electronic-invoicing context.
 */
final readonly class RequestDocumentTransmission implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $documentId,
        public string $kind,
    ) {
    }
}
