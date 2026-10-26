<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Consultation\Application\Port\ClinicDocumentIdentityInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Document\DocumentClinic;

final readonly class QueryBusClinicDocumentIdentity implements ClinicDocumentIdentityInterface
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    public function forClinic(string $clinicId): ?DocumentClinic
    {
        $clinic = $this->queryBus->ask(new GetClinic($clinicId));

        return $clinic instanceof ClinicDto ? $clinic->toDocumentClinic() : null;
    }
}
