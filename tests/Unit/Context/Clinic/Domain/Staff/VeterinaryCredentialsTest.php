<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff;

use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalRegistrationNumber;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Domain\Staff\ValueObject\SignatureImageKey;
use App\Context\Clinic\Domain\Staff\VeterinaryCredentials;
use PHPUnit\Framework\TestCase;

final class VeterinaryCredentialsTest extends TestCase
{
    public function testConstructWithAllFields(): void
    {
        $credentials = new VeterinaryCredentials(
            registrationNumber: ProfessionalRegistrationNumber::fromString('FR-12345'),
            professionalTitle: ProfessionalTitle::DR,
            signatureImageKey: SignatureImageKey::fromString('signatures/vet.png'),
        );

        self::assertSame('FR-12345', $credentials->registrationNumber()->toString());
        self::assertSame(ProfessionalTitle::DR, $credentials->professionalTitle());
        self::assertNotNull($credentials->signatureImageKey());
        self::assertSame('signatures/vet.png', $credentials->signatureImageKey()->toString());
    }

    public function testConstructWithoutSignatureImageKey(): void
    {
        $credentials = new VeterinaryCredentials(
            registrationNumber: ProfessionalRegistrationNumber::fromString('FR-12345'),
            professionalTitle: ProfessionalTitle::NONE,
            signatureImageKey: null,
        );

        self::assertSame('FR-12345', $credentials->registrationNumber()->toString());
        self::assertSame(ProfessionalTitle::NONE, $credentials->professionalTitle());
        self::assertNull($credentials->signatureImageKey());
    }
}
