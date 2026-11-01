<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\GetPortalConsents\PortalConsent;
use App\Presentation\Api\Portal\Support\ApiDate;

/** One consent kind; `channels: []` = not consented. `grantedAt` in UTC. */
final readonly class ConsentView
{
    /**
     * @param list<string> $channels
     */
    public function __construct(
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['appointment_reminders', 'vaccination_reminders', 'clinical_documents', 'billing', 'marketing']])]
        public string $kind,
        #[ApiProperty(schema: ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['sms', 'email']]])]
        public array $channels,
        #[ApiProperty(schema: ['type' => ['string', 'null'], 'format' => 'date-time'])]
        public ?string $grantedAt,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['send_control', 'channel_preference']])]
        public string $effect,
    ) {
    }

    public static function of(PortalConsent $consent): self
    {
        return new self($consent->kind, $consent->channels, ApiDate::formatNullable($consent->grantedAt), $consent->effect);
    }
}
