<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

/**
 * The rendered file of a transmission, as stored: its storage id and the
 * sha256 of its bytes. Frozen once the transmission is submitting.
 */
final class DocumentReference
{
    private function __construct(
        private readonly string $storageId,
        private readonly string $sha256,
    ) {
    }

    public static function of(string $storageId, string $sha256): self
    {
        if ('' === $storageId || 1 !== preg_match('/^[0-9a-f]{64}$/', $sha256)) {
            throw new \InvalidArgumentException('A document reference needs a storage id and a sha256.');
        }

        return new self($storageId, $sha256);
    }

    public function storageId(): string
    {
        return $this->storageId;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }
}
