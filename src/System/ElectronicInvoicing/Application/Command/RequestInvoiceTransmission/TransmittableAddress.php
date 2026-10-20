<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

/**
 * A postal address (EN16931 BG-5 / BG-8).
 */
final readonly class TransmittableAddress
{
    /**
     * @param list<string> $lines
     */
    public function __construct(
        public array $lines,
        public ?string $postalCode,
        public ?string $city,
        public string $countryCode,
    ) {
    }

    /**
     * @return array{lines: list<string>, postalCode: ?string, city: ?string, countryCode: string}
     */
    public function toArray(): array
    {
        return ['lines' => $this->lines, 'postalCode' => $this->postalCode, 'city' => $this->city, 'countryCode' => $this->countryCode];
    }

    /**
     * @param array{lines: list<string>, postalCode: ?string, city: ?string, countryCode: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['lines'], $data['postalCode'], $data['city'], $data['countryCode']);
    }
}
