<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\WithdrawPublishedDocument;

use App\Context\ClientPortal\Domain\Document\DocumentType;
use App\Shared\Application\Bus\CommandInterface;

/** The source was cancelled: the document leaves the Portal for good. */
final readonly class WithdrawPublishedDocument implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public DocumentType $type,
        public string $sourceRef,
    ) {
    }
}
