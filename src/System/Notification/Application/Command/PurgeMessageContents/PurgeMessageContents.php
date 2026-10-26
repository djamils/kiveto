<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\PurgeMessageContents;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Daily (GDPR retention): messages sent more than 24 months ago lose their
 * bodies, recipient and Notification-owned attachment files; the metadata
 * (purpose, channel, status, dates, segments, subject) stays.
 */
final readonly class PurgeMessageContents implements CommandInterface
{
}
