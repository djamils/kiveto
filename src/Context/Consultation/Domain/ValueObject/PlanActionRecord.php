<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

/**
 * One typed action of the SOAP "P" plan.
 *
 * Acts carry a price snapshot resolved from the Catalog at add time; that
 * snapshot is what the derived billing line copies and never re-resolves.
 */
final readonly class PlanActionRecord
{
    private function __construct(
        private string $id,
        private PlanActionKind $kind,
        private string $description,
        private ?string $catalogCode,
        private ?string $posology,
        private ?int $durationDays,
        private ?int $followUpDays,
        private float $quantity,
        private ?int $unitPriceMinorUnits,
        private ?string $currency,
        private ?string $taxCategoryCode,
        private \DateTimeImmutable $createdAtUtc,
        private string $createdByUserId,
    ) {
    }

    public static function create(
        PlanActionKind $kind,
        string $description,
        ?string $catalogCode,
        ?string $posology,
        ?int $durationDays,
        ?int $followUpDays,
        float $quantity,
        ?int $unitPriceMinorUnits,
        ?string $currency,
        ?string $taxCategoryCode,
        \DateTimeImmutable $createdAtUtc,
        UserId $createdByUserId,
    ): self {
        self::assertPositiveDays($durationDays, 'Plan action duration');
        self::assertPositiveDays($followUpDays, 'Plan action follow-up delay');
        self::assertQuantity($quantity);
        self::assertPrice($unitPriceMinorUnits, $currency);

        return new self(
            Uuid::v7()->toString(),
            $kind,
            self::normalizeDescription($description),
            self::normalizeShortText($catalogCode, 'Plan action catalog code', 40),
            self::normalizeShortText($posology, 'Plan action posology', 255),
            $durationDays,
            $followUpDays,
            $quantity,
            $unitPriceMinorUnits,
            $currency,
            self::normalizeShortText($taxCategoryCode, 'Plan action tax category code', 40),
            $createdAtUtc,
            $createdByUserId->toString(),
        );
    }

    public static function reconstitute(
        string $id,
        PlanActionKind $kind,
        string $description,
        ?string $catalogCode,
        ?string $posology,
        ?int $durationDays,
        ?int $followUpDays,
        float $quantity,
        ?int $unitPriceMinorUnits,
        ?string $currency,
        ?string $taxCategoryCode,
        \DateTimeImmutable $createdAtUtc,
        string $createdByUserId,
    ): self {
        return new self(
            $id,
            $kind,
            $description,
            $catalogCode,
            $posology,
            $durationDays,
            $followUpDays,
            $quantity,
            $unitPriceMinorUnits,
            $currency,
            $taxCategoryCode,
            $createdAtUtc,
            $createdByUserId,
        );
    }

    /**
     * Editable fields of an existing action. The price snapshot is deliberately
     * preserved: re-pricing only happens when the line itself is re-created.
     */
    public function withDetails(
        string $description,
        ?string $posology,
        ?int $durationDays,
        ?int $followUpDays,
        float $quantity,
    ): self {
        self::assertPositiveDays($durationDays, 'Plan action duration');
        self::assertPositiveDays($followUpDays, 'Plan action follow-up delay');
        self::assertQuantity($quantity);

        return new self(
            $this->id,
            $this->kind,
            self::normalizeDescription($description),
            $this->catalogCode,
            self::normalizeShortText($posology, 'Plan action posology', 255),
            $durationDays,
            $followUpDays,
            $quantity,
            $this->unitPriceMinorUnits,
            $this->currency,
            $this->taxCategoryCode,
            $this->createdAtUtc,
            $this->createdByUserId,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getKind(): PlanActionKind
    {
        return $this->kind;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCatalogCode(): ?string
    {
        return $this->catalogCode;
    }

    public function getPosology(): ?string
    {
        return $this->posology;
    }

    public function getDurationDays(): ?int
    {
        return $this->durationDays;
    }

    public function getFollowUpDays(): ?int
    {
        return $this->followUpDays;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getUnitPriceMinorUnits(): ?int
    {
        return $this->unitPriceMinorUnits;
    }

    public function getCurrency(): ?string
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

    private static function normalizeDescription(string $description): string
    {
        $description = trim($description);

        if ('' === $description) {
            throw new \InvalidArgumentException('Plan action description cannot be empty');
        }

        if (mb_strlen($description) > 255) {
            throw new \InvalidArgumentException('Plan action description cannot exceed 255 characters');
        }

        return $description;
    }

    private static function normalizeShortText(?string $value, string $subject, int $maxLength): ?string
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

    private static function assertPositiveDays(?int $days, string $subject): void
    {
        if (null !== $days && $days < 1) {
            throw new \InvalidArgumentException(\sprintf('%s must be at least 1 day', $subject));
        }
    }

    /**
     * Bounds match the persisted DECIMAL(10,2): anything that rounds to zero or
     * overflows the column would otherwise be silently truncated or blow up as
     * a driver error at flush time.
     */
    private static function assertQuantity(float $quantity): void
    {
        if (round($quantity, 2) <= 0) {
            throw new \InvalidArgumentException('Plan action quantity must be positive');
        }

        if ($quantity > 99999.99) {
            throw new \InvalidArgumentException('Plan action quantity cannot exceed 99999.99');
        }
    }

    private static function assertPrice(?int $unitPriceMinorUnits, ?string $currency): void
    {
        if (null === $unitPriceMinorUnits) {
            return;
        }

        if ($unitPriceMinorUnits < 0) {
            throw new \InvalidArgumentException('Plan action price cannot be negative');
        }

        if (null === $currency || '' === trim($currency)) {
            throw new \InvalidArgumentException('Plan action price requires a currency');
        }
    }
}
