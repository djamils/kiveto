<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Port;

use App\System\Notification\Application\Exception\InvalidNotificationRequestException;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\System\Notification\Domain\Message\Channel;
use App\System\Notification\Domain\Message\MessagePurpose;
use App\System\Notification\Domain\Message\RenderedContent;

/**
 * Renders the content of a message from Kiveto's templates: subject, HTML
 * and text of an e-mail (CSS inlined, clinic logo referenced as
 * `cid:` . RenderedContent::INLINE_LOGO_CID), or the text of an SMS
 * (variable data transliterated to GSM-7) with its encoding and segments.
 *
 * Every clinic value printed (name, city, address, phone, colour, logo) comes
 * from the clinic messaging profile, never from the parameters.
 */
interface MessageRendererInterface
{
    /**
     * @param array<string, scalar|list<scalar>> $parameters exactly the parameters the template of the purpose declares
     * @param ?ClinicMessagingProfile            $profile    required for the purposes sent on behalf of a clinic
     *
     * @throws InvalidNotificationRequestException
     */
    public function render(MessagePurpose $purpose, Channel $channel, array $parameters, ?ClinicMessagingProfile $profile): RenderedContent;
}
