<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Domain\Request;

use App\Context\ClientPortal\Domain\Exception\InvalidPortalRequestException;

/**
 * What an owner asks to change on a pet's file, validated per field (S6e)
 * and normalised for the clinic:
 *
 * | field           | proposed value                                 | normalised                                  |
 * |-----------------|------------------------------------------------|---------------------------------------------|
 * | `name`          | 1 to 60 characters                             | `{name}`                                    |
 * | `weight`        | decimal kg (comma or dot), 0.05 to 150         | `{weightGrams, measuredOn}` (≤ today)       |
 * | `sterilization` | `neutered` or `intact`                         | `{reproductiveStatus}`                      |
 * | `chip_number`   | 15 digits                                      | `{chipNumber}`                              |
 * | `other`         | free (may be empty), a `note` is required      | `{}`                                        |
 *
 * Every proposed value is at most 120 characters, every note at most 500;
 * values are trimmed and a blank note is no note.
 */
final readonly class ProposedChange
{
    public const int MAX_PROPOSED_VALUE = 120;
    public const int MAX_NOTE           = 500;
    public const int MAX_NAME           = 60;
    public const int MIN_WEIGHT_GRAMS   = 50;
    public const int MAX_WEIGHT_GRAMS   = 150_000;

    private ChangeField $field;
    private string $proposedValue;

    /** @var array<string, string|int> */
    private array $normalised;

    private ?string $note;

    /**
     * @param array<string, string|int> $normalised
     */
    private function __construct(ChangeField $field, string $proposedValue, array $normalised, ?string $note)
    {
        $this->field         = $field;
        $this->proposedValue = $proposedValue;
        $this->normalised    = $normalised;
        $this->note          = $note;
    }

    /**
     * @param \DateTimeImmutable|null $measuredOn weight only: the day it was measured (a date); the submit day by default
     * @param \DateTimeImmutable      $today      the clinic-local day of the submit
     *
     * @throws InvalidPortalRequestException
     */
    public static function of(ChangeField $field, string $raw, ?\DateTimeImmutable $measuredOn, ?string $note, \DateTimeImmutable $today): self
    {
        $value = trim($raw);
        $note  = null === $note || '' === trim($note) ? null : trim($note);

        if (mb_strlen($value) > self::MAX_PROPOSED_VALUE) {
            throw new InvalidPortalRequestException('proposedValue', \sprintf('La valeur proposée ne doit pas dépasser %d caractères.', self::MAX_PROPOSED_VALUE));
        }

        if (null !== $note && mb_strlen($note) > self::MAX_NOTE) {
            throw new InvalidPortalRequestException('note', \sprintf('La note ne doit pas dépasser %d caractères.', self::MAX_NOTE));
        }

        $normalised = match ($field) {
            ChangeField::NAME          => ['name' => self::name($value)],
            ChangeField::WEIGHT        => self::weight($value, $measuredOn, $today),
            ChangeField::STERILIZATION => ['reproductiveStatus' => self::sterilization($value)],
            ChangeField::CHIP_NUMBER   => ['chipNumber' => self::chip($value)],
            ChangeField::OTHER         => self::other($note),
        };

        return new self($field, $value, $normalised, $note);
    }

    /**
     * @param array<string, string|int> $normalised
     */
    public static function reconstitute(ChangeField $field, string $proposedValue, array $normalised, ?string $note): self
    {
        return new self($field, $proposedValue, $normalised, $note);
    }

    /** The same change without the owner's free text (account deletion): the note, and the value of `other`. */
    public function withoutFreeText(): self
    {
        return new self($this->field, ChangeField::OTHER === $this->field ? '' : $this->proposedValue, $this->normalised, null);
    }

    public function field(): ChangeField
    {
        return $this->field;
    }

    public function proposedValue(): string
    {
        return $this->proposedValue;
    }

    /** @return array<string, string|int> */
    public function normalised(): array
    {
        return $this->normalised;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    private static function name(string $value): string
    {
        if ('' === $value || mb_strlen($value) > self::MAX_NAME) {
            throw new InvalidPortalRequestException('proposedValue', \sprintf('Le nom doit faire entre 1 et %d caractères.', self::MAX_NAME));
        }

        return $value;
    }

    /**
     * @return array{weightGrams: int, measuredOn: string}
     */
    private static function weight(string $value, ?\DateTimeImmutable $measuredOn, \DateTimeImmutable $today): array
    {
        if (1 !== preg_match('/^\d{1,3}(?:[.,]\d{1,3})?$/', $value)) {
            throw new InvalidPortalRequestException('proposedValue', 'Le poids doit être un nombre en kg, par exemple 12,4.');
        }

        $grams = (int) round((float) str_replace(',', '.', $value) * 1000);

        if ($grams < self::MIN_WEIGHT_GRAMS || $grams > self::MAX_WEIGHT_GRAMS) {
            throw new InvalidPortalRequestException('proposedValue', 'Le poids doit être compris entre 0,05 et 150 kg.');
        }

        $day = ($measuredOn ?? $today)->format('Y-m-d');

        if ($day > $today->format('Y-m-d')) {
            throw new InvalidPortalRequestException('measuredOn', 'La date de pesée ne peut pas être dans le futur.');
        }

        return ['weightGrams' => $grams, 'measuredOn' => $day];
    }

    private static function sterilization(string $value): string
    {
        if (!\in_array($value, ['neutered', 'intact'], true)) {
            throw new InvalidPortalRequestException('proposedValue', 'La stérilisation doit valoir « neutered » ou « intact ».');
        }

        return $value;
    }

    private static function chip(string $value): string
    {
        if (1 !== preg_match('/^\d{15}\z/', $value)) {
            throw new InvalidPortalRequestException('proposedValue', 'Le numéro d\'identification doit compter 15 chiffres.');
        }

        return $value;
    }

    /**
     * @return array{}
     */
    private static function other(?string $note): array
    {
        if (null === $note) {
            throw new InvalidPortalRequestException('note', 'Précisez votre demande dans la note.');
        }

        return [];
    }
}
