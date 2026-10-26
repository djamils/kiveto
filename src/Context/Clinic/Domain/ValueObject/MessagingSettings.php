<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidMessagingSettingsException;

/**
 * How the clinic writes to its clients: the short name its SMS come from
 * (an alphanumeric sender: at most 11 letters, digits or spaces, at least
 * one letter) and how many segments beyond the monthly SMS quota it accepts
 * before the non-critical SMS are held back.
 */
final readonly class MessagingSettings
{
    public const int DEFAULT_OVERAGE_CAP = 500;
    public const int MAX_OVERAGE_CAP     = 100000;
    public const int SENDER_MAX_LENGTH   = 11;

    /** The sender used when the clinic name gives nothing usable. */
    public const string FALLBACK_SENDER = 'Kiveto';

    private const string SENDER = '/^(?=.*[A-Za-z])[A-Za-z0-9 ]{1,11}$/';

    private function __construct(
        public string $smsSender,
        public int $overageCapSegments,
    ) {
    }

    public static function create(string $smsSender, int $overageCapSegments): self
    {
        $sender = mb_trim(preg_replace('/ {2,}/', ' ', $smsSender) ?? $smsSender);

        if (1 !== preg_match(self::SENDER, $sender)) {
            throw InvalidMessagingSettingsException::malformedSender($sender);
        }

        if ($overageCapSegments < 0 || $overageCapSegments > self::MAX_OVERAGE_CAP) {
            throw InvalidMessagingSettingsException::implausibleOverageCap($overageCapSegments);
        }

        return new self($sender, $overageCapSegments);
    }

    /**
     * What a clinic that has set nothing uses: its name reduced to the
     * characters an SMS sender may carry (« Clinique Véto » → « Clinique Ve »),
     * and the default overage cap.
     */
    public static function defaultFor(string $clinicName): self
    {
        return new self(self::senderFrom($clinicName), self::DEFAULT_OVERAGE_CAP);
    }

    public static function senderFrom(string $clinicName): string
    {
        $decomposed = \Normalizer::normalize($clinicName, \Normalizer::FORM_D);
        $plain      = preg_replace('/\p{Mn}+/u', '', \is_string($decomposed) ? $decomposed : $clinicName) ?? $clinicName;
        $plain      = preg_replace('/[^A-Za-z0-9 ]+/', ' ', $plain) ?? $plain;
        $plain      = preg_replace('/ {2,}/', ' ', $plain) ?? $plain;
        $sender     = mb_rtrim(mb_substr(mb_trim($plain), 0, self::SENDER_MAX_LENGTH));

        return 1 === preg_match(self::SENDER, $sender) ? $sender : self::FALLBACK_SENDER;
    }

    public function equals(self $other): bool
    {
        return $this->smsSender === $other->smsSender
            && $this->overageCapSegments === $other->overageCapSegments;
    }
}
