<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Rendering;

use App\Shared\Domain\Storage\FileStorageInterface;
use App\System\Notification\Application\Port\MessageRendererInterface;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\RenderedContent;
use Twig\Environment;

/**
 * Renders the notification templates (templates/notification/**).
 *
 * E-mails: the subject is the `subject` block of the HTML template, the HTML
 * has its CSS inlined by the layout (`inline_css`), the text part is the
 * `.txt.twig` sibling. The clinic logo is referenced as `cid:logo` when its
 * stored file can be read — the dispatch embeds it from the content's
 * `inlineLogoKey` — else the clinic initial is shown.
 *
 * SMS: string parameters and the clinic name / phone are transliterated to
 * GSM-7, whitespace is collapsed into single spaces, then the encoding and
 * the billed segments are computed.
 */
final readonly class TwigMessageRenderer implements MessageRendererInterface
{
    private const string DEFAULT_ACCENT = '#1e1b4b';

    public function __construct(
        private Environment $twig,
        private FileStorageInterface $storage,
    ) {
    }

    public function render(MessagePurpose $purpose, Channel $channel, array $parameters, ?ClinicMessagingProfile $profile): RenderedContent
    {
        $parameters = TemplateCatalog::validate($purpose, $channel, $parameters);

        if (!$purpose->isSentByKiveto() && null === $profile) {
            throw new \LogicException(\sprintf('%s is written on behalf of a clinic: its messaging profile is required.', $purpose->value));
        }

        $profile = $purpose->isSentByKiveto() ? null : $profile;
        $name    = TemplateCatalog::templateName($purpose);

        return Channel::EMAIL === $channel
            ? $this->email($name, $parameters, $profile)
            : $this->sms($name, $parameters, $profile);
    }

    /**
     * @param array<string, scalar|list<scalar>> $parameters
     */
    private function email(string $name, array $parameters, ?ClinicMessagingProfile $profile): RenderedContent
    {
        $logoKey = null !== $profile && null !== $profile->logoStorageKey && $this->readable($profile->logoStorageKey) ? $profile->logoStorageKey : null;
        $context = [...$parameters, 'clinic' => null !== $profile ? self::clinic($profile, $logoKey) : null];

        $template = $this->twig->load('notification/email/' . $name . '.html.twig');
        $subject  = html_entity_decode($template->renderBlock('subject', $context), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return RenderedContent::email(
            self::collapse($subject),
            $template->render($context),
            mb_trim($this->twig->render('notification/email/' . $name . '.txt.twig', $context)),
            $logoKey,
        );
    }

    /**
     * @param array<string, scalar|list<scalar>> $parameters
     */
    private function sms(string $name, array $parameters, ?ClinicMessagingProfile $profile): RenderedContent
    {
        $context = array_map(
            static fn (mixed $value): mixed => \is_array($value) ? array_map(self::gsm7(...), $value) : self::gsm7($value),
            $parameters,
        );
        $context['clinic'] = null !== $profile ? ['name' => Gsm7::transliterate($profile->name), 'phone' => Gsm7::transliterate($profile->phoneDisplay)] : null;

        $text                  = self::collapse($this->twig->render('notification/sms/' . $name . '.txt.twig', $context));
        [$encoding, $segments] = SmsSegmentCounter::count($text);

        return RenderedContent::sms($text, $encoding, $segments);
    }

    /**
     * @return array{name: string, initial: string, city: string, addressLine: string, phone: string, accentColor: string, logoCid: ?string, footerLine: string}
     */
    private static function clinic(ClinicMessagingProfile $profile, ?string $logoKey): array
    {
        return [
            'name'        => $profile->name,
            'initial'     => $profile->initial(),
            'city'        => $profile->city,
            'addressLine' => $profile->addressLine,
            'phone'       => $profile->phoneDisplay,
            'accentColor' => $profile->brandColor ?? self::DEFAULT_ACCENT,
            'logoCid'     => null !== $logoKey ? RenderedContent::INLINE_LOGO_CID : null,
            'footerLine'  => implode(' · ', array_filter([$profile->name, $profile->addressLine, $profile->phoneDisplay], static fn (string $part): bool => '' !== $part)),
        ];
    }

    private function readable(string $key): bool
    {
        try {
            $this->storage->read($key);

            return true;
        } catch (\RuntimeException) {
            // A logo whose file went missing is replaced by the initial.
            return false;
        }
    }

    private static function gsm7(bool|float|int|string $value): bool|float|int|string
    {
        return \is_string($value) ? Gsm7::transliterate($value) : $value;
    }

    private static function collapse(string $text): string
    {
        return mb_trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
