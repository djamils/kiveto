<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Domain\Transmission;

enum TransmissionKind: string
{
    case INVOICE     = 'invoice';
    case CREDIT_NOTE = 'credit_note';

    public static function fromTypeCode(string $typeCode): self
    {
        return '381' === $typeCode ? self::CREDIT_NOTE : self::INVOICE;
    }
}
