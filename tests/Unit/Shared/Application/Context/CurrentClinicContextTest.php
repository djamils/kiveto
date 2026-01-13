<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application\Context;

use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Context\CurrentClinicContext;
use App\Shared\Application\Context\CurrentClinicContextInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CurrentClinicContextTest extends TestCase
{
    private CurrentClinicContextInterface $context;
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $requestStack  = $this->createStub(RequestStack::class);
        $requestStack->method('getSession')->willReturn($this->session);

        $this->context = new CurrentClinicContext($requestStack);
    }

    public function testSetAndGetCurrentClinicId(): void
    {
        $clinicId = ClinicId::fromString('11111111-1111-1111-1111-111111111111');

        $this->context->setCurrentClinicId($clinicId);
        $retrieved = $this->context->getCurrentClinicId();

        self::assertNotNull($retrieved);
        self::assertTrue($clinicId->equals($retrieved));
    }

    public function testGetCurrentClinicIdReturnsNullWhenNotSet(): void
    {
        $retrieved = $this->context->getCurrentClinicId();

        self::assertNull($retrieved);
    }

    public function testHasCurrentClinicReturnsTrueWhenSet(): void
    {
        $clinicId = ClinicId::fromString('11111111-1111-1111-1111-111111111111');

        $this->context->setCurrentClinicId($clinicId);

        self::assertTrue($this->context->hasCurrentClinic());
    }

    public function testHasCurrentClinicReturnsFalseWhenNotSet(): void
    {
        self::assertFalse($this->context->hasCurrentClinic());
    }

    public function testClearCurrentClinic(): void
    {
        $clinicId = ClinicId::fromString('11111111-1111-1111-1111-111111111111');

        $this->context->setCurrentClinicId($clinicId);
        self::assertTrue($this->context->hasCurrentClinic());

        $this->context->clearCurrentClinic();
        self::assertFalse($this->context->hasCurrentClinic());
        self::assertNull($this->context->getCurrentClinicId());
    }

    public function testSessionKeyIsConsistent(): void
    {
        $clinicId = ClinicId::fromString('11111111-1111-1111-1111-111111111111');

        $this->context->setCurrentClinicId($clinicId);

        // Vérifier que la clé de session est bien 'current_clinic_id'
        self::assertTrue($this->session->has('current_clinic_id'));
        self::assertSame('11111111-1111-1111-1111-111111111111', $this->session->get('current_clinic_id'));
    }
}
