<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Application\Command\RequestInvoiceTransmission;

/**
 * A document note (BT-22) with its subject code (BT-21): PMD, PMT, AAB, REG, ABL….
 */
final readonly class TransmittableNote
{
    public function __construct(
        public string $subjectCode,
        public string $text,
    ) {
    }

    /**
     * @return array{subjectCode: string, text: string}
     */
    public function toArray(): array
    {
        return ['subjectCode' => $this->subjectCode, 'text' => $this->text];
    }

    /**
     * @param array{subjectCode: string, text: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['subjectCode'], $data['text']);
    }
}
