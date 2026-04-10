<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Consultation\Application\Query\GetConsultationDetails;

use App\Context\Consultation\Application\Query\GetConsultationDetails\ConsultationDetailsDTO;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetails;
use App\Context\Consultation\Application\Query\GetConsultationDetails\GetConsultationDetailsHandler;
use App\Context\Consultation\Domain\ValueObject\ConsultationStatus;
use App\Fixtures\Context\Consultation\Factory\ConsultationEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class GetConsultationDetailsHandlerTest extends KernelTestCase
{
    use Factories;

    public function testReturnsDtoWhenConsultationExists(): void
    {
        $consultationId = '11111111-1111-4111-8111-111111111111';
        $clinicId       = '22222222-2222-4222-8222-222222222222';
        $userId         = '55555555-5555-4555-8555-555555555555';

        ConsultationEntityFactory::new()
            ->withId($consultationId)
            ->withClinicId($clinicId)
            ->withPractitionerUserId($userId)
            ->withStatus(ConsultationStatus::OPEN)
            ->withOwnerId('66666666-6666-4666-8666-666666666666')
            ->withAnimalId('77777777-7777-4777-8777-777777777777')
            ->create([
                'chiefComplaint' => 'Limping',
                'startedAtUtc'   => new \DateTimeImmutable('2026-04-10 09:00:00'),
                'createdAtUtc'   => new \DateTimeImmutable('2026-04-10 09:00:00'),
                'updatedAtUtc'   => new \DateTimeImmutable('2026-04-10 09:00:00'),
                'weightKg'       => '12.500',
                'temperatureC'   => '38.20',
            ])
        ;

        $handler = self::getContainer()->get(GetConsultationDetailsHandler::class);
        \assert($handler instanceof GetConsultationDetailsHandler);

        $result = $handler(new GetConsultationDetails($consultationId));

        self::assertInstanceOf(ConsultationDetailsDTO::class, $result);
        self::assertSame($consultationId, $result->consultationId);
        self::assertSame($clinicId, $result->clinicId);
        self::assertSame($userId, $result->practitionerUserId);
        self::assertSame('OPEN', $result->status);
        self::assertSame('66666666-6666-4666-8666-666666666666', $result->ownerId);
        self::assertSame('77777777-7777-4777-8777-777777777777', $result->animalId);
        self::assertSame('Limping', $result->chiefComplaint);
        self::assertSame(['weightKg' => '12.500', 'temperatureC' => '38.20'], $result->vitals);
        self::assertSame([], $result->notes);
        self::assertSame([], $result->acts);
    }

    public function testThrowsWhenConsultationNotFound(): void
    {
        $handler = self::getContainer()->get(GetConsultationDetailsHandler::class);
        \assert($handler instanceof GetConsultationDetailsHandler);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Consultation "00000000-0000-4000-8000-000000000000" not found.');

        $handler(new GetConsultationDetails('00000000-0000-4000-8000-000000000000'));
    }
}
