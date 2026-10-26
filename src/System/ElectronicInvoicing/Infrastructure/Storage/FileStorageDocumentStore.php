<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Storage;

use App\Shared\Domain\Storage\FileStorageInterface;
use App\System\ElectronicInvoicing\Application\Port\DocumentStoreInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\DocumentReference;

/**
 * Keeps the transmitted files — the authoritative record of what was sent —
 * on the shared file storage, under a name derived from the tracking id and
 * the content hash.
 */
final readonly class FileStorageDocumentStore implements DocumentStoreInterface
{
    public function __construct(private FileStorageInterface $storage)
    {
    }

    public function store(string $trackingId, string $sha256, string $content): DocumentReference
    {
        $name = \sprintf('electronic-invoicing/%s-%s.xml', preg_replace('/[^A-Za-z0-9_-]/', '_', $trackingId), substr($sha256, 0, 16));

        return DocumentReference::of($this->storage->store($name, $content), $sha256);
    }

    public function load(DocumentReference $reference): string
    {
        return $this->storage->read($reference->storageId());
    }
}
