<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Admission\Infrastructure\Adapter;

use App\Context\Admission\Infrastructure\Adapter\Consultation\CommandBusConsultationPatientTransferAdapter;
use App\Context\Consultation\Application\Command\TransferConsultationsToPatient\TransferConsultationsToPatient;
use App\Shared\Application\Bus\CommandBusInterface;
use PHPUnit\Framework\TestCase;

final class CommandBusConsultationPatientTransferAdapterTest extends TestCase
{
    public function testTransferConsultationsDispatchesTheConsultationCommand(): void
    {
        $commandBus = $this->createMock(CommandBusInterface::class);
        $commandBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (mixed $command): bool {
                return $command instanceof TransferConsultationsToPatient
                    && 'clinic' === $command->clinicId
                    && 'source' === $command->sourcePatientId
                    && 'target' === $command->targetPatientId;
            }))
        ;

        (new CommandBusConsultationPatientTransferAdapter($commandBus))->transferConsultations('clinic', 'source', 'target');
    }
}
