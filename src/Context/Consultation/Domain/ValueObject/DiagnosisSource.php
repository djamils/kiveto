<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

enum DiagnosisSource: string
{
    case MANUAL        = 'MANUAL';
    case AI_SUGGESTION = 'AI_SUGGESTION';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL        => 'Saisi manuellement',
            self::AI_SUGGESTION => 'Suggestion IA acceptée',
        };
    }
}
