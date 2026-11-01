<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\Scheduling;

use App\Context\ClientPortal\Application\Port\BookingMotifsInterface;
use App\Context\ClientPortal\Application\Port\OnlineMotif;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\BookingMotifView;
use App\Context\Scheduling\Application\Query\ListBookingMotifs\ListBookingMotifs;
use App\Shared\Application\Bus\QueryBusInterface;

/** Scheduling's ListBookingMotifs (archived ones excluded), kept when open online. */
final readonly class QueryBusBookingMotifs implements BookingMotifsInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function onlineMotifs(string $clinicId): array
    {
        $views = $this->queryBus->ask(new ListBookingMotifs($clinicId));

        if (!\is_array($views)) {
            return [];
        }

        $motifs = [];

        foreach ($views as $view) {
            \assert($view instanceof BookingMotifView);

            if ($view->onlineEnabled && null === $view->archivedAt) {
                $motifs[] = new OnlineMotif($view->id, $view->label, $view->description, $view->durationMin, $view->preventiveCareKind);
            }
        }

        return $motifs;
    }
}
