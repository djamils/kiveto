<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * The invoicing framework of a French e-invoice (« cadre de facturation »,
 * EN16931 BT-23): the nature of what is supplied followed by the case, e.g.
 * S2 for services paid at issue.
 */
final readonly class InvoicingFramework
{
    private function __construct(
        public FrameworkNature $nature,
        public FrameworkCase $case,
    ) {
    }

    public static function of(FrameworkNature $nature, FrameworkCase $case): self
    {
        return new self($nature, $case);
    }

    public static function fromCode(string $code): self
    {
        if (1 !== preg_match('/^([BSM])([12])$/', $code, $parts)) {
            throw new \InvalidArgumentException(\sprintf('Invalid invoicing framework "%s".', $code));
        }

        return new self(FrameworkNature::from($parts[1]), FrameworkCase::from($parts[2]));
    }

    public function code(): string
    {
        return $this->nature->value . $this->case->value;
    }

    public function equals(self $other): bool
    {
        return $this->nature === $other->nature && $this->case === $other->case;
    }
}
