<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Form\Animal;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Uuid;

final class AnimalFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: "Le nom de l'animal est obligatoire."),
                    new Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser 255 caractères.'),
                ],
            ])
            ->add('species', ChoiceType::class, [
                'choices' => [
                    'Chien' => 'dog',
                    'Chat'  => 'cat',
                    'NAC'   => 'nac',
                    'Autre' => 'other',
                ],
                'placeholder' => '— Sélectionner une espèce —',
                'constraints' => [
                    new NotBlank(message: "L'espèce est obligatoire."),
                ],
            ])
            ->add('sex', ChoiceType::class, [
                'choices' => [
                    '♀ Femelle' => 'female',
                    '♂ Mâle'    => 'male',
                    'Inconnu'   => 'unknown',
                ],
                'data'        => 'unknown',
                'expanded'    => true,
                'constraints' => [
                    new NotBlank(message: 'Le sexe est obligatoire.'),
                ],
            ])
            ->add('reproductiveStatus', ChoiceType::class, [
                'choices' => [
                    'Oui'     => 'neutered',
                    'Non'     => 'intact',
                    'Inconnu' => 'unknown',
                ],
                'data'        => 'unknown',
                'expanded'    => true,
                'constraints' => [
                    new NotBlank(message: 'Le statut reproductif est obligatoire.'),
                ],
            ])
            ->add('breedName', TextType::class, [
                'required' => false,
            ])
            ->add('birthDate', DateType::class, [
                'required' => false,
                'widget'   => 'single_text',
            ])
            ->add('primaryOwnerClientId', HiddenType::class, [
                'constraints' => [
                    new NotBlank(message: 'Le propriétaire est obligatoire.'),
                    new Uuid(message: "L'identifiant du propriétaire est invalide."),
                ],
            ])
            ->add('_returnTo', HiddenType::class, [
                'mapped'   => false,
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'    => null,
            'csrf_token_id' => 'create_animal',
        ]);
    }
}
