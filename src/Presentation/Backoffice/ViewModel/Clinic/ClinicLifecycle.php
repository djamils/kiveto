<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\ViewModel\Clinic;

/**
 * The status the console shows for a clinic: suspended, onboarding, or
 * active under its plan (pilot, paid, internal).
 */
final readonly class ClinicLifecycle
{
    public const string ACTIVE = 'active';

    public const string ONBOARDING = 'onboarding';

    public const string SUSPENDED = 'suspended';

    private function __construct(
        public string $key,
        public string $label,
        public string $badge,
    ) {
    }

    public static function of(bool $suspended, bool $onboarded, string $plan): self
    {
        if ($suspended) {
            return new self(self::SUSPENDED, 'Suspendue', 'is-red');
        }

        if (!$onboarded) {
            return new self(self::ONBOARDING, 'Onboarding', 'is-indigo');
        }

        return match ($plan) {
            'paid'     => new self(self::ACTIVE, 'Client actif', 'is-green'),
            'internal' => new self(self::ACTIVE, 'Interne', 'is-outline'),
            default    => new self(self::ACTIVE, 'Pilote actif', 'is-green'),
        };
    }

    /**
     * « Marseille 8e », « Paris 1er », « Lyon 3e » from an arrondissement's
     * postal code; the city alone elsewhere.
     */
    public static function place(?string $city, ?string $postalCode): ?string
    {
        if (null === $city) {
            return null;
        }

        $prefixes = ['Paris' => '750', 'Marseille' => '130', 'Lyon' => '6900'];
        $prefix   = $prefixes[trim($city)] ?? null;

        if (null !== $prefix && null !== $postalCode && str_starts_with($postalCode, $prefix) && 5 === \strlen($postalCode)) {
            $district = (int) substr($postalCode, -2);

            if ($district >= 1 && $district <= 20) {
                return trim($city) . ' ' . $district . (1 === $district ? 'er' : 'e');
            }
        }

        return trim($city);
    }
}
