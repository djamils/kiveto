<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use ApiPlatform\Metadata\ApiProperty;
use App\Context\ClientPortal\Application\Query\ListPortalConsentHistory\PortalConsentHistoryItem;
use App\Presentation\Api\Portal\Support\ApiDate;

/** One change of a consent; `at` in UTC. */
final readonly class ConsentHistoryItemView
{
    public function __construct(
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['appointment_reminders', 'vaccination_reminders', 'clinical_documents', 'billing', 'marketing']])]
        public string $kind,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['sms', 'email']])]
        public string $channel,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['granted', 'withdrawn']])]
        public string $action,
        #[ApiProperty(schema: ['type' => 'string', 'format' => 'date-time'])]
        public string $at,
        #[ApiProperty(schema: ['type' => 'string', 'enum' => ['portal', 'clinic', 'system']])]
        public string $source,
        public string $author,
        public bool $approximate,
    ) {
    }

    public static function of(PortalConsentHistoryItem $item): self
    {
        return new self($item->kind, $item->channel, $item->action, ApiDate::format($item->at), $item->source, $item->author, $item->approximate);
    }
}
