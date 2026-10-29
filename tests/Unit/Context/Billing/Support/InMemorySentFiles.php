<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Support;

use App\Context\Billing\Application\Command\EnsureSentInvoiceFile\SentDocumentFile;
use App\Context\Billing\Application\Port\SentDocumentFileStoreInterface;
use App\Context\Billing\Domain\Dispatch\SentDocumentKind;

final class InMemorySentFiles implements SentDocumentFileStoreInterface
{
    /** @var array<string, SentDocumentFile> clinic|document => file */
    public array $files = [];

    /** A row another send committed between this one's read and its insert. */
    public ?SentDocumentFile $concurrent = null;

    public function find(string $clinicId, string $documentId): ?SentDocumentFile
    {
        return $this->files[$clinicId . '|' . $documentId] ?? null;
    }

    public function record(string $clinicId, SentDocumentKind $kind, string $documentId, SentDocumentFile $file, \DateTimeImmutable $at): SentDocumentFile
    {
        $key = $clinicId . '|' . $documentId;

        if (null !== $this->concurrent) {
            $this->files[$key] = $this->concurrent;
        }

        return $this->files[$key] ??= $file;
    }
}
