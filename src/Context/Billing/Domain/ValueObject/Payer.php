<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

use App\Context\Billing\Domain\Exception\InvalidPayerException;

/**
 * Who owes the invoice, snapshotted at draft time.
 *
 * The client id is kept for navigation, but the name and address are copies:
 * an invoice must still print what it printed the day it was issued, even after
 * the owner moves or changes their name. A structure is billed under its
 * legal name, « à l'attention de » its contact person.
 */
final class Payer
{
    /**
     * @param list<string> $addressLines
     */
    private function __construct(
        private readonly ?PayerId $clientId,
        private readonly string $displayName,
        private readonly array $addressLines,
        private readonly ?string $attention = null,
    ) {
    }

    /**
     * @param list<string> $addressLines
     */
    public static function create(?PayerId $clientId, string $displayName, array $addressLines = [], ?string $attention = null): self
    {
        $displayName = mb_trim($displayName);

        if ('' === $displayName) {
            throw new InvalidPayerException('The payer name cannot be empty.');
        }

        $lines = [];
        foreach ($addressLines as $line) {
            $line = mb_trim($line);

            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        $attention = null === $attention ? '' : mb_trim($attention);

        return new self($clientId, $displayName, $lines, '' === $attention ? null : $attention);
    }

    /**
     * The counter cannot always name the payer — an emergency intake by a
     * passer-by has no client record yet.
     */
    public static function anonymous(): self
    {
        return new self(null, 'Client de passage', []);
    }

    /**
     * A counter sale to a client who refuses to give their contact details:
     * the ticket carries no name.
     */
    public static function contactDetailsRefused(): self
    {
        return new self(null, 'Vente sans coordonnées', []);
    }

    public function clientId(): ?PayerId
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

    /**
     * The contact person a structure's invoice is addressed to: « Mme Claire Rostand ».
     */
    public function attention(): ?string
    {
        return $this->attention;
    }

    public function equals(self $other): bool
    {
        return $this->displayName === $other->displayName
            && $this->addressLines === $other->addressLines
            && $this->attention === $other->attention
            && $this->clientId?->toString() === $other->clientId?->toString();
    }
}
