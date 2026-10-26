<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

/**
 * An electronic address (BT-49): scheme and identifier — the one a file was
 * actually sent to.
 */
final class ElectronicAddress
{
    private function __construct(
        private readonly string $scheme,
        private readonly string $identifier,
    ) {
    }

    public static function of(string $scheme, string $identifier): self
    {
        if ('' === mb_trim($scheme) || '' === mb_trim($identifier)) {
            throw new \InvalidArgumentException('An electronic address needs a scheme and an identifier.');
        }

        return new self(mb_trim($scheme), mb_trim($identifier));
    }

    public function scheme(): string
    {
        return $this->scheme;
    }

    public function identifier(): string
    {
        return $this->identifier;
    }
}
