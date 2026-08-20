<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * A durable medical flag on the animal's record: an allergy or a chronic
 * condition. Surfaced by the consultation cockpit banner and cross-checked
 * against prescribed active substances.
 */
final readonly class MedicalAlert
{
    private function __construct(
        public string $id,
        public MedicalAlertKind $kind,
        public string $label,
        public ?string $note,
    ) {
    }

    public static function create(MedicalAlertKind $kind, string $label, ?string $note = null): self
    {
        return new self(Uuid::v7()->toString(), $kind, self::normalizeLabel($label), self::normalizeNote($note));
    }

    public static function reconstitute(string $id, MedicalAlertKind $kind, string $label, ?string $note): self
    {
        return new self($id, $kind, $label, $note);
    }

    public function isAllergy(): bool
    {
        return MedicalAlertKind::ALLERGY === $this->kind;
    }

    /**
     * Naive, label-based match used by the prescription warning: an active
     * substance whose name contains the allergy label. Deliberately not an
     * interaction engine — the UI wording asks the vet to double-check.
     */
    public function matchesSubstance(string $substanceLabel): bool
    {
        $needle   = mb_strtolower($this->label);
        $haystack = mb_strtolower($substanceLabel);

        return '' !== $needle && str_contains($haystack, $needle);
    }

    private static function normalizeLabel(string $label): string
    {
        $label = trim($label);

        if ('' === $label) {
            throw new \InvalidArgumentException('Medical alert label cannot be empty');
        }

        if (mb_strlen($label) > 120) {
            throw new \InvalidArgumentException('Medical alert label cannot exceed 120 characters');
        }

        return $label;
    }

    private static function normalizeNote(?string $note): ?string
    {
        if (null === $note) {
            return null;
        }

        $note = trim($note);

        if ('' === $note) {
            return null;
        }

        if (mb_strlen($note) > 1000) {
            throw new \InvalidArgumentException('Medical alert note cannot exceed 1000 characters');
        }

        return $note;
    }
}
