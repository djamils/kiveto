<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\Form\Animal;

use App\Presentation\Clinic\Form\Animal\AnimalFormType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\Validation;

#[AllowMockObjectsWithoutExpectations]
final class AnimalFormTypeTest extends TypeTestCase
{
    public function testValidSubmission(): void
    {
        $form = $this->factory->create(AnimalFormType::class);

        $form->submit([
            'name'                 => 'Rex',
            'species'              => 'dog',
            'sex'                  => 'male',
            'breedName'            => 'Berger',
            'birthDate'            => '2020-05-12',
            'primaryOwnerClientId' => '12345678-1234-4abc-9def-1234567890ab',
        ]);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());

        $data = $form->getData();
        self::assertIsArray($data);
        self::assertSame('Rex', $data['name']);
        self::assertSame('dog', $data['species']);
        self::assertSame('male', $data['sex']);
    }

    public function testEmptyNameYieldsFrenchError(): void
    {
        $form = $this->factory->create(AnimalFormType::class);

        $form->submit([
            'name'                 => '',
            'species'              => 'dog',
            'sex'                  => 'male',
            'primaryOwnerClientId' => '12345678-1234-4abc-9def-1234567890ab',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            "Le nom de l'animal est obligatoire.",
            (string) $form->get('name')->getErrors(),
        );
    }

    public function testMissingOwnerYieldsFrenchError(): void
    {
        $form = $this->factory->create(AnimalFormType::class);

        $form->submit([
            'name'                 => 'Rex',
            'species'              => 'dog',
            'sex'                  => 'male',
            'primaryOwnerClientId' => '',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            'Le propriétaire est obligatoire.',
            (string) $form->getErrors(true),
        );
    }

    public function testInvalidUuidYieldsFrenchError(): void
    {
        $form = $this->factory->create(AnimalFormType::class);

        $form->submit([
            'name'                 => 'Rex',
            'species'              => 'dog',
            'sex'                  => 'male',
            'primaryOwnerClientId' => 'not-a-uuid',
        ]);

        self::assertFalse($form->isValid());
        self::assertStringContainsString(
            "L'identifiant du propriétaire est invalide.",
            (string) $form->getErrors(true),
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
