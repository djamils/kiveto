<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

use App\Context\Prescription\Domain\Exception\InvalidPrescriptionLineException;

/**
 * One medicine of the ordonnance, frozen.
 *
 * It carries what has to be printed for the line to be dispensable: the
 * commercial name, the pharmaceutical form, the active substances, the
 * posology, and the class that decides how long the whole document is valid.
 * None of it is looked up again afterwards — the catalog and the registry will
 * change, the signed prescription may not.
 */
final class PrescriptionLine
{
    /**
     * @param list<string> $activeSubstances
     */
    private function __construct(
        private readonly PrescriptionLineId $id,
        private readonly ?string $articleId,
        private readonly ?string $code,
        private readonly string $label,
        private readonly ?string $pharmaceuticalForm,
        private readonly array $activeSubstances,
        private readonly ?string $dose,
        private readonly ?string $frequency,
        private readonly ?int $durationDays,
        private readonly ?string $route,
        private readonly string $quantity,
        private readonly DrugPrescriptionClass $prescriptionClass,
        private readonly ?string $instructions,
    ) {
    }

    /**
     * @param list<string> $activeSubstances
     */
    public static function create(
        PrescriptionLineId $id,
        ?string $articleId,
        ?string $code,
        string $label,
        ?string $pharmaceuticalForm,
        array $activeSubstances,
        ?string $dose,
        ?string $frequency,
        ?int $durationDays,
        ?string $route,
        string $quantity,
        DrugPrescriptionClass $prescriptionClass,
        ?string $instructions = null,
    ): self {
        $label = mb_trim($label);

        if ('' === $label) {
            throw new InvalidPrescriptionLineException('A prescription line needs a medicine name.');
        }

        if (1 !== preg_match('/^\d+(\.\d{1,2})?$/', $quantity)) {
            throw new InvalidPrescriptionLineException(
                \sprintf('Invalid quantity "%s": expected a positive decimal with at most 2 places.', $quantity),
            );
        }

        /** @var numeric-string $quantity */
        if (0 === bccomp($quantity, '0', 2)) {
            throw new InvalidPrescriptionLineException('A prescription line cannot have a zero quantity.');
        }

        if (null !== $durationDays && $durationDays <= 0) {
            throw new InvalidPrescriptionLineException('A treatment lasts at least one day.');
        }

        $substances = [];

        foreach ($activeSubstances as $substance) {
            $substance = mb_trim($substance);

            if ('' !== $substance) {
                $substances[] = $substance;
            }
        }

        return new self(
            $id,
            $articleId,
            self::clean($code),
            $label,
            self::clean($pharmaceuticalForm),
            $substances,
            self::clean($dose),
            self::clean($frequency),
            $durationDays,
            self::clean($route),
            $quantity,
            $prescriptionClass,
            self::clean($instructions),
        );
    }

    public function id(): PrescriptionLineId
    {
        return $this->id;
    }

    public function articleId(): ?string
    {
        return $this->articleId;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function pharmaceuticalForm(): ?string
    {
        return $this->pharmaceuticalForm;
    }

    /**
     * @return list<string>
     */
    public function activeSubstances(): array
    {
        return $this->activeSubstances;
    }

    public function dose(): ?string
    {
        return $this->dose;
    }

    public function frequency(): ?string
    {
        return $this->frequency;
    }

    public function durationDays(): ?int
    {
        return $this->durationDays;
    }

    public function route(): ?string
    {
        return $this->route;
    }

    public function quantity(): string
    {
        return $this->quantity;
    }

    public function prescriptionClass(): DrugPrescriptionClass
    {
        return $this->prescriptionClass;
    }

    public function instructions(): ?string
    {
        return $this->instructions;
    }

    public function isControlled(): bool
    {
        return $this->prescriptionClass->isControlled();
    }

    /**
     * The posology as one sentence, the way it is printed under the medicine.
     */
    public function posology(): string
    {
        $parts = array_filter([
            $this->dose,
            $this->frequency,
            null !== $this->durationDays
                ? \sprintf('pendant %d jour%s', $this->durationDays, $this->durationDays > 1 ? 's' : '')
                : null,
            null !== $this->route ? 'voie ' . $this->route : null,
        ]);

        return implode(', ', $parts);
    }

    private static function clean(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = mb_trim($value);

        return '' === $value ? null : $value;
    }
}
