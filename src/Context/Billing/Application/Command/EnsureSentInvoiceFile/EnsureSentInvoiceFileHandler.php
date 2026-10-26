<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\EnsureSentInvoiceFile;

use App\Context\Billing\Application\Service\SentInvoiceFiles;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnsureSentInvoiceFileHandler
{
    public function __construct(private SentInvoiceFiles $files)
    {
    }

    public function __invoke(EnsureSentInvoiceFile $command): SentDocumentFile
    {
        return $this->files->ensure($command->clinicId, $command->invoiceId);
    }
}
