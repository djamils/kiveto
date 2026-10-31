<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Consultation;

use App\Context\ClientPortal\Application\Query\GetReportShareState\GetReportShareState;
use App\Context\ClientPortal\Application\Query\GetReportShareState\ReportShareState;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * « Partager sur le Portal » in the documents panel: the endpoint, whether
 * the report can be shared (else the French reason) and the last share in
 * the clinic's time zone.
 */
final readonly class PortalShareViewBuilder
{
    /** The refusals as the vet reads them (same words as the endpoint). */
    public const array REASONS = [
        ReportShareState::NOT_CLOSED       => 'La consultation doit être clôturée.',
        ReportShareState::NO_ANIMAL        => 'Le patient n\'est rattaché à aucun animal.',
        ReportShareState::NO_PRIMARY_OWNER => 'L\'animal n\'a pas de propriétaire principal.',
        ReportShareState::UNKNOWN          => 'Consultation introuvable.',
    ];

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return array{url: string, shareable: bool, reason: ?string, sharedAt: ?string}
     */
    public function build(string $clinicId, string $consultationId, string $timeZone): array
    {
        $state = $this->queryBus->ask(new GetReportShareState($clinicId, $consultationId));
        \assert($state instanceof ReportShareState);

        return [
            'url'       => $this->urls->generate('clinic_consultation_portal_share', ['id' => $consultationId]),
            'shareable' => $state->shareable,
            'reason'    => null === $state->reason ? null : (self::REASONS[$state->reason] ?? self::REASONS[ReportShareState::UNKNOWN]),
            'sharedAt'  => null === $state->sharedAt ? null : self::local($state->sharedAt, $timeZone),
        ];
    }

    /** « 31/10/2026 à 14:05 » in the clinic's time zone. */
    private static function local(\DateTimeImmutable $at, string $timeZone): string
    {
        $local = $at->setTimezone(new \DateTimeZone($timeZone));

        return $local->format('d/m/Y') . ' à ' . $local->format('H:i');
    }
}
