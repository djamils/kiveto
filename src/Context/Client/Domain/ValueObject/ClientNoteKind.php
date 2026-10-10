<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\ValueObject;

enum ClientNoteKind: string
{
    case INFORMATION = 'information';
    case ATTENTION   = 'attention';

    public function label(): string
    {
        return match ($this) {
            self::INFORMATION => 'Information',
            self::ATTENTION   => 'Point d’attention',
        };
    }
}
