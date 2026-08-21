<?php

declare(strict_types=1);

namespace App\Context\Consultation\Domain\ValueObject;

use Symfony\Component\Uid\Uuid;

final readonly class TypedVitalRecord
{
    private function __construct(
        private string $id,
        private VitalType $type,
        private string $value,
        private \DateTimeImmutable $recordedAtUtc,
        private string $recordedByUserId,
    ) {
    }

    public static function create(
        VitalType $type,
        string $value,
        \DateTimeImmutable $recordedAtUtc,
        UserId $recordedByUserId,
    ): self {
        $value = trim($value);

        if ('' === $value) {
            throw new \InvalidArgumentException('Vital value cannot be empty');
        }

        if (mb_strlen($value) > 60) {
            throw new \InvalidArgumentException('Vital value cannot exceed 60 characters');
        }

        $min = $type->min();
        $max = $type->max();

        if (null !== $min && null !== $max) {
            if (!is_numeric($value)) {
                throw new \InvalidArgumentException(\sprintf('%s must be a numeric value', $type->label()));
            }

            $numeric = (float) $value;

            if ($numeric < $min || $numeric > $max) {
                throw new \InvalidArgumentException(\sprintf(
                    '%s must be between %s and %s',
                    $type->label(),
                    self::formatBound($min),
                    self::formatBound($max),
                ));
            }
        }

        return new self(
            Uuid::v7()->toString(),
            $type,
            $value,
            $recordedAtUtc,
            $recordedByUserId->toString(),
        );
    }

    public static function reconstitute(
        string $id,
        VitalType $type,
        string $value,
        \DateTimeImmutable $recordedAtUtc,
        string $recordedByUserId,
    ): self {
        return new self($id, $type, $value, $recordedAtUtc, $recordedByUserId);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): VitalType
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getRecordedAtUtc(): \DateTimeImmutable
    {
        return $this->recordedAtUtc;
    }

    public function getRecordedByUserId(): string
    {
        return $this->recordedByUserId;
    }

    private static function formatBound(float $bound): string
    {
        return rtrim(rtrim(\sprintf('%.2F', $bound), '0'), '.');
    }
}
