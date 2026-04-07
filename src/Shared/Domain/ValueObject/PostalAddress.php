<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

final readonly class PostalAddress
{
    public function __construct(
        public string $streetLine1,
        public string $city,
        public string $countryCode,
        public ?string $streetLine2 = null,
        public ?string $postalCode = null,
        public ?string $region = null,
    ) {
        $this->validate();
    }

    public static function create(
        string $streetLine1,
        string $city,
        string $countryCode,
        ?string $streetLine2 = null,
        ?string $postalCode = null,
        ?string $region = null,
    ): self {
        return new self(
            streetLine1: trim($streetLine1),
            city: trim($city),
            countryCode: mb_strtoupper(trim($countryCode)),
            streetLine2: self::normalizeOptional($streetLine2),
            postalCode: self::normalizeOptional($postalCode),
            region: self::normalizeOptional($region),
        );
    }

    public function equals(self $other): bool
    {
        return $this->streetLine1 === $other->streetLine1
            && $this->streetLine2 === $other->streetLine2
            && $this->postalCode === $other->postalCode
            && $this->city === $other->city
            && $this->region === $other->region
            && $this->countryCode === $other->countryCode;
    }

    private function validate(): void
    {
        if ('' === $this->streetLine1) {
            throw new \InvalidArgumentException('Street line 1 cannot be empty.');
        }

        if ('' === $this->city) {
            throw new \InvalidArgumentException('City cannot be empty.');
        }

        if (!preg_match('/^[A-Z]{2}$/', $this->countryCode)) {
            throw new \InvalidArgumentException(\sprintf(
                'Country code must be 2 uppercase letters (ISO 3166-1 alpha-2), got: "%s".',
                $this->countryCode,
            ));
        }
    }

    private static function normalizeOptional(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
