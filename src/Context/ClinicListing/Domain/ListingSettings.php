<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Domain;

use App\Context\ClinicListing\Domain\Exception\InvalidListingSettingsException;

/**
 * How a clinic appears in the directory, set in VetApp « Paramètres ›
 * Annuaire »: whether it is listed at all (no by default), whether it takes
 * new clients, and the tags shown on its card (« NAC », « Urgences »…).
 * No row means the defaults.
 */
final class ListingSettings
{
    public const int MAX_TAGS       = 8;
    public const int TAG_MIN_LENGTH = 2;
    public const int TAG_MAX_LENGTH = 30;

    private string $clinicId;
    private bool $listed;
    private bool $acceptsNewClients;
    /** @var list<string> */
    private array $tags;

    private function __construct()
    {
    }

    public static function defaults(string $clinicId): self
    {
        $settings                    = new self();
        $settings->clinicId          = $clinicId;
        $settings->listed            = false;
        $settings->acceptsNewClients = true;
        $settings->tags              = [];

        return $settings;
    }

    /**
     * @param list<string> $tags
     */
    public static function reconstitute(string $clinicId, bool $listed, bool $acceptsNewClients, array $tags): self
    {
        $settings                    = new self();
        $settings->clinicId          = $clinicId;
        $settings->listed            = $listed;
        $settings->acceptsNewClients = $acceptsNewClients;
        $settings->tags              = $tags;

        return $settings;
    }

    /**
     * Replaces the settings. Tags are trimmed (inner spaces collapsed), blank
     * ones dropped and duplicates (ignoring case) kept once, in their order.
     *
     * @param list<string> $tags
     *
     * @throws InvalidListingSettingsException a tag too short or too long, or too many tags
     */
    public function update(bool $listed, bool $acceptsNewClients, array $tags): void
    {
        $clean = [];

        foreach ($tags as $tag) {
            $tag = mb_trim((string) preg_replace('/\s+/u', ' ', $tag));

            if ('' === $tag || \array_key_exists(mb_strtolower($tag), $clean)) {
                continue;
            }

            $length = mb_strlen($tag);

            if ($length < self::TAG_MIN_LENGTH || $length > self::TAG_MAX_LENGTH) {
                throw new InvalidListingSettingsException('tags', \sprintf('« %s » : un tag compte de %d à %d caractères.', $tag, self::TAG_MIN_LENGTH, self::TAG_MAX_LENGTH));
            }

            $clean[mb_strtolower($tag)] = $tag;
        }

        if (\count($clean) > self::MAX_TAGS) {
            throw new InvalidListingSettingsException('tags', \sprintf('%d tags au plus.', self::MAX_TAGS));
        }

        $this->listed            = $listed;
        $this->acceptsNewClients = $acceptsNewClients;
        $this->tags              = array_values($clean);
    }

    public function clinicId(): string
    {
        return $this->clinicId;
    }

    public function isListed(): bool
    {
        return $this->listed;
    }

    public function acceptsNewClients(): bool
    {
        return $this->acceptsNewClients;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->tags;
    }
}
