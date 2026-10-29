<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\System\Notification\Application\Port\MessageRendererInterface;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\RenderedContent;
use App\System\Notification\Domain\Message\SmsEncoding;
use App\System\Notification\Infrastructure\Rendering\TemplateCatalog;

/**
 * Checks the parameters like the real renderer and returns a recognisable
 * content; SMS are billed $segments segments.
 */
final class FakeRenderer implements MessageRendererInterface
{
    /** @var list<array{MessagePurpose, Channel, ?ClinicMessagingProfile}> */
    public array $calls = [];

    public function __construct(public int $segments = 1)
    {
    }

    public function render(MessagePurpose $purpose, Channel $channel, array $parameters, ?ClinicMessagingProfile $profile): RenderedContent
    {
        TemplateCatalog::validate($purpose, $channel, $parameters);
        $this->calls[] = [$purpose, $channel, $profile];

        return Channel::SMS === $channel
            ? RenderedContent::sms($purpose->value . ' SMS', SmsEncoding::GSM_7, $this->segments)
            : RenderedContent::email($purpose->value . ' subject', '<p>' . $purpose->value . '</p>', $purpose->value, $profile?->logoStorageKey);
    }
}
