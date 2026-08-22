<?php

declare(strict_types=1);

namespace App\Context\Prescription\Domain\ValueObject;

use App\Context\Prescription\Domain\Exception\InvalidPrescriberException;

/**
 * The practitioner who signs, snapshotted at draft time.
 *
 * The registration number is what makes the document a prescription rather than
 * a note: without it the aggregate refuses to issue.
 */
final class Prescriber
{
    private function __construct(
        private readonly PrescriberId $userId,
        private readonly string $displayName,
        private readonly ?string $registrationNumber,
        private readonly ?string $professionalTitle,
    ) {
    }

    public static function create(
        PrescriberId $userId,
        string $displayName,
        ?string $registrationNumber = null,
        ?string $professionalTitle = null,
    ): self {
        $displayName = mb_trim($displayName);

        if ('' === $displayName) {
            throw new InvalidPrescriberException('The prescriber must be named.');
        }

        return new self(
            $userId,
            $displayName,
            self::clean($registrationNumber),
            self::clean($professionalTitle),
        );
    }

    public function userId(): PrescriberId
    {
        return $this->userId;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function registrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function professionalTitle(): ?string
    {
        return $this->professionalTitle;
    }

    public function canSign(): bool
    {
        return null !== $this->registrationNumber;
    }

    /**
     * How the signature block reads: "Dr Rousseau" rather than "Rousseau".
     */
    public function printedName(): string
    {
        return null !== $this->professionalTitle
            ? mb_convert_case(mb_strtolower($this->professionalTitle), \MB_CASE_TITLE) . ' ' . $this->displayName
            : $this->displayName;
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
