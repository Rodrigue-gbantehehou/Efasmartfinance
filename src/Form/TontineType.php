<?php

namespace App\Form;

use App\Entity\Tontine;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TontineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('tontineCode')
            ->add('amountPerPoint')
            ->add('totalPoints')
            ->add('frequency')
            ->add('startDate')
            ->add('nextDueDate')
            ->add('reminderEnabled')
            ->add('statut')
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('utilisateur', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tontine::class,
        ]);
    }
}
