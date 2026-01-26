<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('site_name', TextType::class, [
                'label' => 'Nom du site',
                'required' => true,
                'attr' => [
                    'placeholder' => 'EFA SMART FINANCE'
                ]
            ])
            ->add('contact_email', TextType::class, [
                'label' => 'Email de contact',
                'required' => true,
                'attr' => [
                    'placeholder' => 'contact@efasmartfinance.com'
                ]
            ])
            ->add('items_per_page', IntegerType::class, [
                'label' => 'Éléments par page',
                'required' => true,
                'attr' => [
                    'min' => 1,
                    'max' => 100
                ]
            ])
            ->add('maintenance_mode', CheckboxType::class, [
                'label' => 'Mode maintenance',
                'required' => false,
                'label_attr' => ['class' => 'font-medium text-gray-700'],
                'help' => 'Activez cette option pour mettre le site en mode maintenance. Seuls les administrateurs pourront y accéder.',
                'help_attr' => ['class' => 'text-gray-500 text-sm']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
