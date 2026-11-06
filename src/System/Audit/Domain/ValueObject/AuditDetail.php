<?php

declare(strict_types=1);

namespace App\System\Audit\Domain\ValueObject;

/**
 * One line of an entry's unfolded detail (« Durée » → « 18 min »).
 */
final class AuditDetail
{
    private const int MAX_LABEL_LENGTH = 60;

    private const int MAX_VALUE_LENGTH = 255;

    private string $label;
    private string $value;

    private function __construct()
    {
    }

    public static function of(string $label, string $value): self
    {
        $label = trim($label);

        if ('' === $label) {
            throw new \InvalidArgumentException('An audit detail needs a label.');
        }

        $detail        = new self();
        $detail->label = mb_substr($label, 0, self::MAX_LABEL_LENGTH);
        $detail->value = mb_substr(trim($value), 0, self::MAX_VALUE_LENGTH);

        return $detail;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->label === $other->label && $this->value === $other->value;
    }
}
