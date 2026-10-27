<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Clinic;

/**
 * What Notification knows of a clinic to write on its behalf — a read-only
 * copy pushed by Clinic, the source of truth: the sender name and e-mail
 * local part, the reply-to address, the SMS sender, the overage cap and the
 * coordinates printed in the e-mail layout.
 */
final readonly class ClinicMessagingProfile
{
    private function __construct(
        public string $clinicId,
        public string $name,
        public string $emailLocalPart,
        public ?string $replyTo,
        public string $smsSender,
        public int $overageCapSegments,
        public string $addressLine,
        public string $city,
        public string $phoneDisplay,
        public ?string $brandColor,
        public ?string $logoStorageKey,
    ) {
    }

    public static function of(
        string $clinicId,
        string $name,
        string $emailLocalPart,
        ?string $replyTo,
        string $smsSender,
        int $overageCapSegments,
        string $addressLine,
        string $city,
        string $phoneDisplay,
        ?string $brandColor,
        ?string $logoStorageKey,
    ): self {
        $name           = mb_trim($name);
        $emailLocalPart = mb_strtolower(mb_trim($emailLocalPart));
        $smsSender      = mb_trim($smsSender);
        $replyTo        = null !== $replyTo && '' !== mb_trim($replyTo) ? mb_trim($replyTo) : null;
        $brandColor     = null !== $brandColor && '' !== mb_trim($brandColor) ? mb_trim($brandColor) : null;

        if ('' === $name) {
            throw new \InvalidArgumentException('A clinic messaging profile needs the clinic name.');
        }

        // A dot-atom: no leading, trailing or doubled dot.
        if (1 !== preg_match('/^(?!.*\.\.)[a-z0-9](?:[a-z0-9.-]{0,62}[a-z0-9])?$/', $emailLocalPart)) {
            throw new \InvalidArgumentException(\sprintf('Invalid e-mail local part "%s".', $emailLocalPart));
        }

        if (null !== $replyTo && false === filter_var($replyTo, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(\sprintf('Invalid reply-to address "%s".', $replyTo));
        }

        // Clinic's rule (MessagingSettings): an alphanumeric sender has a
        // letter — operators refuse an all-digit one. Empty: Kiveto's sender.
        if (1 !== preg_match('/^(?:(?=.*[A-Za-z])[A-Za-z0-9 ]{1,11})?$/', $smsSender)) {
            throw new \InvalidArgumentException(\sprintf('Invalid SMS sender "%s" (11 letters, digits or spaces at most, at least one letter).', $smsSender));
        }

        if ($overageCapSegments < 0) {
            throw new \InvalidArgumentException('The overage cap cannot be negative.');
        }

        if (null !== $brandColor && 1 !== preg_match('/^#[0-9A-Fa-f]{6}$/', $brandColor)) {
            throw new \InvalidArgumentException(\sprintf('Invalid brand colour "%s".', $brandColor));
        }

        return new self(
            $clinicId,
            $name,
            $emailLocalPart,
            $replyTo,
            $smsSender,
            $overageCapSegments,
            mb_trim($addressLine),
            mb_trim($city),
            mb_trim($phoneDisplay),
            $brandColor,
            null !== $logoStorageKey && '' !== mb_trim($logoStorageKey) ? $logoStorageKey : null,
        );
    }

    /**
     * The letter shown in the clinic card when there is no logo: the first
     * letter of the last word, so that « Clinique Montmartre » shows « M ».
     */
    public function initial(): string
    {
        $words = preg_split("/[\\s'’]+/u", $this->name, -1, \PREG_SPLIT_NO_EMPTY) ?: [$this->name];

        return mb_strtoupper(mb_substr($words[array_key_last($words)], 0, 1));
    }
}
