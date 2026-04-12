<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\Form\Client;

use App\Presentation\Clinic\Form\Client\ClientFormType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
final class ClientFormTypeTest extends TypeTestCase
{
    public function testValidSubmissionMapsToArray(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => 'Sophie',
            'lastName'  => 'Martin',
            'email'     => 'sophie@example.com',
            'phone'     => '+33612345678',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        $data = $form->getData();
        self::assertIsArray($data);
        self::assertSame('Sophie', $data['firstName']);
        self::assertSame('Martin', $data['lastName']);
        self::assertSame('sophie@example.com', $data['email']);
        self::assertSame('+33612345678', $data['phone']);
    }

    public function testEmptyFirstNameYieldsFrenchError(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => '',
            'lastName'  => 'Martin',
            'email'     => 'sophie@example.com',
            'phone'     => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            'Le prénom est obligatoire.',
            (string) $form->get('firstName')->getErrors(),
        );
    }

    public function testInvalidEmailYieldsFrenchError(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => 'Sophie',
            'lastName'  => 'Martin',
            'email'     => 'not-an-email',
            'phone'     => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            "Cette adresse email n'est pas valide.",
            (string) $form->get('email')->getErrors(),
        );
    }

    public function testEmptyPhoneAndEmailTriggersCallback(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => 'Sophie',
            'lastName'  => 'Martin',
            'email'     => '',
            'phone'     => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            'Au moins un moyen de contact (téléphone ou email) est requis.',
            (string) $form->getErrors(),
        );
    }

    public function testPhoneAloneIsValid(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => 'Sophie',
            'lastName'  => 'Martin',
            'email'     => '',
            'phone'     => '+33612345678',
        ]);

        self::assertTrue($form->isValid());
    }

    public function testEmailAloneIsValid(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => 'Sophie',
            'lastName'  => 'Martin',
            'email'     => 'sophie@example.com',
            'phone'     => '',
        ]);

        self::assertTrue($form->isValid());
    }

    public function testReturnToFieldIsUnmapped(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => 'Sophie',
            'lastName'  => 'Martin',
            'email'     => 'sophie@example.com',
            'phone'     => '',
            '_returnTo' => 'clinic_scheduling_agenda|date=2026-04-11',
        ]);

        $data = $form->getData();
        self::assertIsArray($data);
        self::assertArrayNotHasKey('_returnTo', $data);

        self::assertSame(
            'clinic_scheduling_agenda|date=2026-04-11',
            $form->get('_returnTo')->getData(),
        );
    }

    public function testInvalidPhoneTriggersPhoneTypeValidation(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => 'Sophie',
            'lastName'  => 'Martin',
            'email'     => '',
            'phone'     => '0612345678',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            'Le numéro de téléphone est invalide.',
            (string) $form->get('phone')->getErrors(),
        );
    }

    public function testPhoneWithLettersTriggersValidation(): void
    {
        $form = $this->factory->create(ClientFormType::class);

        $form->submit([
            'firstName' => 'Sophie',
            'lastName'  => 'Martin',
            'email'     => '',
            'phone'     => 'abc',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            'Le numéro de téléphone est invalide.',
            (string) $form->get('phone')->getErrors(),
        );
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
