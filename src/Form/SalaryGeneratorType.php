<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SalaryGeneratorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La date de début est obligatoire']),
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La date de fin est obligatoire']),
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('baseSalary', MoneyType::class, [
                'label' => 'Salaire de base (optionnel)',
                'required' => false,
                'currency' => 'EUR',
                'help' => 'Si vide, le dernier salaire connu sera utilisé comme base',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Laisser vide pour utiliser le dernier salaire',
                ],
            ])
            ->add('overwrite', CheckboxType::class, [
                'label' => 'Écraser les valeurs existantes',
                'required' => false,
                'help' => 'Cochez pour remplacer les fiches de paie existantes pour cette période',
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('useAverage', CheckboxType::class, [
                'label' => 'Utiliser la moyenne des salaires de base',
                'required' => false,
                'help' => 'Cochez pour calculer la moyenne des 3 dernières fiches de paie',
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Générer les fiches de paie',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}