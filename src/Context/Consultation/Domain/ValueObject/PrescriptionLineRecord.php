<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * One medication line of the prescription panel.
 *
 * The unit price is snapshotted from the Catalog at add time and never
 * re-resolved afterwards: a later catalog price change must not alter an
 * existing prescription.
 */
final readonly class PrescriptionLineRecord
{
    private function __construct(
        private string $id,
        private ?string $articleId,
        private ?string $code,
        private string $label,
        private ?string $dose,
        private ?string $frequency,
        private ?int $durationDays,
        private ?string $route,
        private float $quantity,
        private int $unitPriceMinorUnits,
        private string $currency,
        private ?string $taxCategoryCode,
        private \DateTimeImmutable $createdAtUtc,
        private string $createdByUserId,
    ) {
    }

    public static function create(
        ?string $articleId,
        ?string $code,
        string $label,
        ?string $dose,
        ?string $frequency,
        ?int $durationDays,
        ?string $route,
        float $quantity,
        int $unitPriceMinorUnits,
        string $currency,
        ?string $taxCategoryCode,
        \DateTimeImmutable $createdAtUtc,
        UserId $createdByUserId,
    ): self {
        $label = trim($label);

        if ('' === $label) {
            throw new \InvalidArgumentException('Prescription label cannot be empty');
        }

        if (mb_strlen($label) > 255) {
            throw new \InvalidArgumentException('Prescription label cannot exceed 255 characters');
        }

        // Bounds match the persisted DECIMAL(10,2).
        if (round($quantity, 2) <= 0) {
            throw new \InvalidArgumentException('Prescription quantity must be positive');
        }

        if ($quantity > 99999.99) {
            throw new \InvalidArgumentException('Prescription quantity cannot exceed 99999.99');
        }

        if (null !== $durationDays && $durationDays < 1) {
            throw new \InvalidArgumentException('Prescription duration must be at least 1 day');
        }

        if ($unitPriceMinorUnits < 0) {
            throw new \InvalidArgumentException('Prescription price cannot be negative');
        }

        if ('' === trim($currency)) {
            throw new \InvalidArgumentException('Prescription price requires a currency');
        }

        $articleId = self::normalize($articleId, 'Prescription article id', 36);

        if (null !== $articleId && !Uuid::isValid($articleId)) {
            throw new \InvalidArgumentException('Prescription article id must be a valid UUID');
        }

        return new self(
            Uuid::v7()->toString(),
            $articleId,
            self::normalize($code, 'Prescription code', 40),
            $label,
            self::normalize($dose, 'Prescription dose', 60),
            self::normalize($frequency, 'Prescription frequency', 60),
            $durationDays,
            self::normalize($route, 'Prescription route', 60),
            $quantity,
            $unitPriceMinorUnits,
            $currency,
            self::normalize($taxCategoryCode, 'Prescription tax category code', 40),
            $createdAtUtc,
            $createdByUserId->toString(),
        );
    }

    public static function reconstitute(
        string $id,
        ?string $articleId,
        ?string $code,
        string $label,
        ?string $dose,
        ?string $frequency,
        ?int $durationDays,
        ?string $route,
        float $quantity,
        int $unitPriceMinorUnits,
        string $currency,
        ?string $taxCategoryCode,
        \DateTimeImmutable $createdAtUtc,
        string $createdByUserId,
    ): self {
        return new self(
            $id,
            $articleId,
            $code,
            $label,
            $dose,
            $frequency,
            $durationDays,
            $route,
            $quantity,
            $unitPriceMinorUnits,
            $currency,
            $taxCategoryCode,
            $createdAtUtc,
            $createdByUserId,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getArticleId(): ?string
    {
        return $this->articleId;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDose(): ?string
    {
        return $this->dose;
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnitPriceMinorUnits(): int
    {
        return $this->unitPriceMinorUnits;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getTaxCategoryCode(): ?string
    {
        return $this->taxCategoryCode;
    }

    public function getCreatedAtUtc(): \DateTimeImmutable
    {
        return $this->createdAtUtc;
    }

    public function getCreatedByUserId(): string
    {
        return $this->createdByUserId;
    }

    /**
     * Human-readable posology built from the individual fields, used by the
     * prescription list and the printable output.
     */
    public function getPosologySummary(): string
    {
        $parts = array_filter([
            $this->dose,
            $this->frequency,
            null !== $this->durationDays ? \sprintf('%d j', $this->durationDays) : null,
            $this->route,
        ]);

        return implode(' · ', $parts);
    }

    private static function normalize(?string $value, string $subject, int $maxLength): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new \InvalidArgumentException(\sprintf(
                '%s cannot exceed %d characters',
                $subject,
                $maxLength,
            ));
        }

        return $value;
    }
}
