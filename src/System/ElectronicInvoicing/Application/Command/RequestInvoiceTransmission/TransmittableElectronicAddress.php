<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

/**
 * An electronic address (EN16931 BT-34 / BT-49): a scheme and an identifier,
 * rendered as `<ram:URIID schemeID="scheme">identifier</ram:URIID>`.
 */
final readonly class TransmittableElectronicAddress
{
    public function __construct(
        public string $scheme,
        public string $identifier,
    ) {
    }

    /**
     * @return array{scheme: string, identifier: string}
     */
    public function toArray(): array
    {
        return ['scheme' => $this->scheme, 'identifier' => $this->identifier];
    }

    /**
     * @param array{scheme: string, identifier: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['scheme'], $data['identifier']);
    }
}
