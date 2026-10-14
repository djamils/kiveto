<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Stock;

use App\Context\Clinic\Application\Port\StaffProfileReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\ListAllMemberships;
use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\MembershipCollection;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * One line of the trail as the stock page draws it: the mock-up's sense (in,
 * out, adjustment), its wording, the signed quantity, the balance after it and
 * who did it — initials and agenda colour, as on the avatars of the shell.
 */
final readonly class StockMovementPresenter
{
    public const array REASONS = [
        'RECEIPT_SUPPLIER'           => 'Réception',
        'RECEIPT_OPENING'            => 'Entrée hors fournisseur',
        'RECEIPT_RETURN_FROM_CLIENT' => 'Retour client',
        'CONSUMPTION_CONSULTATION'   => 'Consultation',
        'CONSUMPTION_INTERNAL'       => 'Utilisé hors consultation',
        'SALE_RETAIL'                => 'Vente au comptoir',
        'LOSS_EXPIRY'                => 'Perte — péremption',
        'LOSS_BREAKAGE'              => 'Perte — casse',
        'LOSS_THEFT'                 => 'Perte — vol',
        'RETURN_TO_SUPPLIER'         => 'Retour fournisseur',
        'RECALL_RETURN_TO_SUPPLIER'  => 'Retour fournisseur — rappel',
        'PHYSICAL_INVENTORY'         => 'Inventaire',
        'CORRECTION'                 => 'Correction',
    ];

    private const string TIMEZONE = 'Europe/Paris';

    private const array PALETTE = ['#4f86c6', '#d97706', '#16a34a', '#7c3aed', '#0891b2', '#db2777'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private StaffProfileReadRepositoryInterface $profiles,
    ) {
    }

    /**
     * The clinic's team by user id.
     *
     * @return array<string, array{n: string, ini: string, c: string}>
     */
    public function staff(string $clinicId): array
    {
        $memberships = $this->queryBus->ask(new ListAllMemberships(clinicId: $clinicId));

        if (!$memberships instanceof MembershipCollection) {
            return [];
        }

        $byMembership = [];

        foreach ($memberships->memberships as $membership) {
            $byMembership[$membership->membershipId] = $membership;
        }

        $staff = [];

        // The batch lookup of profiles does not carry the user: the membership does.
        foreach ($this->profiles->findByMembershipIds(array_keys($byMembership)) as $membershipId => $profile) {
            $title = ['DR' => 'Dr ', 'PR' => 'Pr '][$profile->professionalTitle ?? ''] ?? '';

            $staff[$byMembership[$membershipId]->userId] = [
                'n'   => $title . $profile->displayName,
                'ini' => self::initials($profile->displayName),
                'c'   => '' !== $profile->agendaColor ? $profile->agendaColor : self::PALETTE[\count($staff) % \count(self::PALETTE)],
            ];
        }

        return $staff;
    }

    /**
     * @param array<string, array{n: string, ini: string, c: string}> $staff
     *
     * @return array<string, mixed>
     */
    public function row(MovementRow $row, array $staff): array
    {
        $quantity = (float) $row->quantity;
        $signed   = match ($row->type) {
            'IN'    => abs($quantity),
            'OUT'   => -abs($quantity),
            default => $quantity,
        };

        $at = $row->occurredAt->setTimezone(new \DateTimeZone(self::TIMEZONE));

        return [
            'id'    => $row->id,
            'a'     => $row->articleId,
            'd'     => $at->format('Y-m-d'),
            'h'     => $at->format('H:i'),
            't'     => ['IN' => 'in', 'OUT' => 'out'][$row->type] ?? 'adj',
            'motif' => self::REASONS[$row->reason] ?? $row->reason,
            'q'     => $signed,
            'solde' => null !== $row->balance ? (float) $row->balance : null,
            'lot'   => $row->lotNumber,
            'ref'   => $row->reference,
            'note'  => $row->note,
            'cons'  => 'CONSUMPTION_CONSULTATION' === $row->reason && null !== $row->reference,
            'par'   => null !== $row->performedBy ? ($staff[$row->performedBy] ?? null) : null,
        ];
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/[\s-]+/u', mb_trim($name), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $first = mb_substr($words[0] ?? '?', 0, 1);
        $last  = \count($words) > 1 ? mb_substr($words[\count($words) - 1], 0, 1) : mb_substr($words[0] ?? '', 1, 1);

        return mb_strtoupper($first . $last);
    }
}
