<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

use App\Context\Prescription\Domain\Exception\InvalidOwnerSnapshotException;

/**
 * Who the ordonnance is handed to, snapshotted at draft time.
 */
final class OwnerSnapshot
{
    /**
     * @param list<string> $addressLines
     */
    private function __construct(
        private readonly ?OwnerId $clientId,
        private readonly string $displayName,
        private readonly array $addressLines,
    ) {
    }

    /**
     * @param list<string> $addressLines
     */
    public static function create(?OwnerId $clientId, string $displayName, array $addressLines = []): self
    {
        $displayName = mb_trim($displayName);

        if ('' === $displayName) {
            throw new InvalidOwnerSnapshotException('The owner must be named on a prescription.');
        }

        $lines = [];

        foreach ($addressLines as $line) {
            $line = mb_trim($line);

            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return new self($clientId, $displayName, $lines);
    }

    /**
     * An animal presented by a passer-by still needs someone on the document.
     */
    public static function unknown(): self
    {
        return new self(null, 'Détenteur non identifié', []);
    }

    public function clientId(): ?OwnerId
    {
        return $this->clientId;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    /**
     * @return list<string>
     */
    public function addressLines(): array
    {
        return $this->addressLines;
    }
}
