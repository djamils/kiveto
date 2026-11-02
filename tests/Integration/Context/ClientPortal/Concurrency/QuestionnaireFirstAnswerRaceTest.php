<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\ClientPortal\Concurrency;

use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaire;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaireHandler;
use App\Context\ClientPortal\Application\Command\SubmitQuestionnaire\SubmitQuestionnaireOutcome;
use App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository\DbalPreVisitQuestionnaireRepository;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Tests\Unit\Context\ClientPortal\Support\PetWorld as P;
use App\Tests\Unit\Context\ClientPortal\Support\RequestWorld;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * S6-05, outside the test transaction, on two real connections: a first
 * answer takes no gap lock (no FOR UPDATE on a missing row), so two first
 * answers of the clinic insert side by side instead of waiting for each
 * other (and deadlocking once both insert); two first answers of the same
 * appointment end on one row, the last one winning.
 */
final class QuestionnaireFirstAnswerRaceTest extends KernelTestCase
{
    private const string FIRST_RDV  = '01960000-0000-7000-8000-0000000b7ef1';
    private const string SECOND_RDV = '01960000-0000-7000-8000-0000000b7ef2';
    private const string FIRST      = '01960000-0000-7000-8000-0000000e7ef1';
    private const string SECOND     = '01960000-0000-7000-8000-0000000e7ef2';

    private Connection $first;
    private Connection $second;

    protected function setUp(): void
    {
        $default = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($default instanceof Connection);

        $this->first  = DriverManager::getConnection($default->getParams());
        $this->second = DriverManager::getConnection($default->getParams());
        $this->second->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        foreach ([$this->first, $this->second] as $connection) {
            while ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }

        $this->cleanUp();
        $this->first->close();
        $this->second->close();

        parent::tearDown();
    }

    public function testTwoFirstAnswersOfTheClinicDoNotWaitForEachOther(): void
    {
        $this->first->beginTransaction();
        $this->second->beginTransaction();

        self::assertSame(SubmitQuestionnaireOutcome::SUBMITTED, $this->submit($this->first, self::FIRST, self::FIRST_RDV, 'Premier'));

        try {
            self::assertSame(SubmitQuestionnaireOutcome::SUBMITTED, $this->submit($this->second, self::SECOND, self::SECOND_RDV, 'Second'));
        } catch (LockWaitTimeoutException) {
            self::fail('The second first answer waited for the gap lock of the first one.');
        }

        $this->first->commit();
        $this->second->commit();

        self::assertSame([self::FIRST_RDV => 'Premier', self::SECOND_RDV => 'Second'], $this->committedNotes());
    }

    public function testTwoFirstAnswersOfTheSameAppointmentEndOnOneRow(): void
    {
        $this->first->beginTransaction();
        $this->second->beginTransaction();

        $this->submit($this->first, self::FIRST, self::FIRST_RDV, 'Premier');

        try {
            $this->submit($this->second, self::SECOND, self::FIRST_RDV, 'Second');
            self::fail('The second writer should wait for the first transaction.');
        } catch (LockWaitTimeoutException) {
            self::assertTrue($this->second->isTransactionActive());
        }

        $this->first->commit();
        self::assertSame(SubmitQuestionnaireOutcome::SUBMITTED, $this->submit($this->second, self::SECOND, self::FIRST_RDV, 'Second'));
        $this->second->commit();

        self::assertSame([self::FIRST_RDV => 'Second'], $this->committedNotes());
    }

    private function submit(Connection $connection, string $id, string $appointmentId, string $notes): SubmitQuestionnaireOutcome
    {
        $world = new RequestWorld();
        $world->appointment($appointmentId);

        $handler = new SubmitQuestionnaireHandler(
            $world->access(),
            new DbalPreVisitQuestionnaireRepository($connection),
            new class($id) implements UuidGeneratorInterface {
                public function __construct(private readonly string $id)
                {
                }

                public function generate(): string
                {
                    return $this->id;
                }
            },
            $world->pets->documents->clock,
        );

        return $handler(new SubmitQuestionnaire(P::USER, P::CLINIC, $appointmentId, 1, ['cough'], false, null, 'calm', $notes));
    }

    /**
     * @return array<string, string> notes by appointment
     */
    private function committedNotes(): array
    {
        $fresh = DriverManager::getConnection($this->first->getParams());

        try {
            $notes = [];

            foreach ($fresh->fetchAllAssociative('SELECT appointment_id, answers FROM ' . DbalPreVisitQuestionnaireRepository::TABLE . ' WHERE appointment_id IN (?, ?) ORDER BY appointment_id', [Uuid::fromString(self::FIRST_RDV)->toBinary(), Uuid::fromString(self::SECOND_RDV)->toBinary()]) as $row) {
                $answers = json_decode(\is_string($row['answers']) ? $row['answers'] : '', true, 8, \JSON_THROW_ON_ERROR);
                \assert(\is_array($answers) && \is_string($answers['notes'] ?? null));
                $notes[Uuid::fromBinary(\is_string($row['appointment_id']) ? $row['appointment_id'] : '')->toRfc4122()] = $answers['notes'];
            }

            return $notes;
        } finally {
            $fresh->close();
        }
    }

    private function cleanUp(): void
    {
        $this->first->executeStatement(
            'DELETE FROM ' . DbalPreVisitQuestionnaireRepository::TABLE . ' WHERE appointment_id IN (?, ?)',
            [Uuid::fromString(self::FIRST_RDV)->toBinary(), Uuid::fromString(self::SECOND_RDV)->toBinary()],
        );
    }
}
