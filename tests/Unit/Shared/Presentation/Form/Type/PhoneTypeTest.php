<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Presentation\Form\Type;

use App\Shared\Presentation\Form\Type\PhoneType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
final class PhoneTypeTest extends TypeTestCase
{
    public function testValidE164IsAccepted(): void
    {
        $form = $this->factory->create(PhoneType::class);

        $form->submit('+33612345678');

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('+33612345678', $form->getData());
    }

    public function testEmptyOptionalIsAccepted(): void
    {
        $form = $this->factory->create(PhoneType::class, null, [
            'required' => false,
        ]);

        $form->submit('');

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('', $form->getData());
    }

    public function testInvalidStringIsRejected(): void
    {
        $form = $this->factory->create(PhoneType::class);

        $form->submit('not-a-phone');

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            'Le numéro de téléphone est invalide.',
            (string) $form->getErrors(),
        );
    }

    public function testLocalFormatIsRejected(): void
    {
        $form = $this->factory->create(PhoneType::class);

        $form->submit('0612345678');

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            'Le numéro de téléphone est invalide.',
            (string) $form->getErrors(),
        );
    }

    public function testWhitespaceIsAccepted(): void
    {
        $form = $this->factory->create(PhoneType::class);

        $form->submit('+33 6 12 34 56 78');

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame('+33 6 12 34 56 78', $form->getData());
    }

    protected function getExtensions(): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator()
        ;

        return [
            new ValidatorExtension($validator),
        ];
    }
}
