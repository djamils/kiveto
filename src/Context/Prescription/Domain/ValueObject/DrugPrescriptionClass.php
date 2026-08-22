<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

/**
 * How tightly a medicine is controlled, copied onto the line at snapshot time.
 *
 * Mirrors the Catalog's own classification: the prescription keeps its own copy
 * so a later reclassification cannot rewrite a document that was already signed.
 */
enum DrugPrescriptionClass: string
{
    case NONE          = 'NONE';
    case RX            = 'RX';
    case RX_CONTROLLED = 'RX_CONTROLLED';
    case RX_NARCOTIC   = 'RX_NARCOTIC';

    public function label(): string
    {
        return match ($this) {
            self::NONE          => 'Sans ordonnance',
            self::RX            => 'Sur ordonnance',
            self::RX_CONTROLLED => 'Substance contrôlée',
            self::RX_NARCOTIC   => 'Stupéfiant',
        };
    }

    /**
     * Whether the line falls under the narcotics and controlled-substances
     * rules: a short validity and no renewal.
     */
    public function isControlled(): bool
    {
        return self::RX_CONTROLLED === $this || self::RX_NARCOTIC === $this;
    }
}
