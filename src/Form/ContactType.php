<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Votre nom',
                'attr' => [
                    'class' => 'form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring focus:ring-green-700 focus:ring-opacity-50',
                    'placeholder' => 'Votre nom complet'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Votre email',
                'attr' => [
                    'class' => 'form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring focus:ring-green-700 focus:ring-opacity-50',
                    'placeholder' => 'votre@email.com'
                ]
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone (optionnel)',
                'required' => false,
                'attr' => [
                    'class' => 'form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring focus:ring-green-700 focus:ring-opacity-50',
                    'placeholder' => '+33 6 12 34 56 78'
                ]
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'attr' => [
                    'class' => 'form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring focus:ring-green-700 focus:ring-opacity-50',
                    'placeholder' => 'Sujet de votre message'
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Votre message',
                'attr' => [
                    'class' => 'form-textarea mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-700 focus:ring focus:ring-green-700 focus:ring-opacity-50',
                    'rows' => 6,
                    'placeholder' => 'Décrivez votre demande en détail...'
                ]
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'J\'accepte la politique de confidentialité',
                'required' => true,
                'mapped' => false,
                'attr' => [
                    'class' => 'rounded border-gray-300 text-green-700 shadow-sm focus:border-green-700 focus:ring focus:ring-green-700 focus:ring-opacity-50'
                ]
            ])
           ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
