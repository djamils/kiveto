<?php

declare(strict_types=1);

namespace App\System\Notification\Domain\Message;

/**
 * The content of a message, frozen when it is requested: subject, HTML and
 * text bodies of an e-mail, or the text of an SMS with its encoding and the
 * number of segments it is billed.
 *
 * An e-mail whose HTML shows the clinic logo references it as
 * `cid:` . INLINE_LOGO_CID; the dispatch embeds the stored file
 * `inlineLogoKey` under that content id.
 */
final readonly class RenderedContent
{
    public const string INLINE_LOGO_CID = 'logo';

    private function __construct(
        public ?string $subject,
        public ?string $html,
        public ?string $text,
        public ?string $inlineLogoKey,
        public ?string $smsText,
        public ?SmsEncoding $encoding,
        public int $segments,
    ) {
    }

    public static function email(string $subject, string $html, string $text, ?string $inlineLogoKey = null): self
    {
        return new self($subject, $html, $text, $inlineLogoKey, null, null, 0);
    }

    public static function sms(string $text, SmsEncoding $encoding, int $segments): self
    {
        if ($segments < 1) {
            throw new \InvalidArgumentException('An SMS is billed at least one segment.');
        }

        return new self(null, null, null, null, $text, $encoding, $segments);
    }

    public function isSms(): bool
    {
        return null !== $this->smsText;
    }
}
