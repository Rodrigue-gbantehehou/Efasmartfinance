<?php

namespace App\Form;

use App\Entity\Tontine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WithdrawalRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $tontine = $options["tontine"];
        $showFeeWarning = $options['show_fee_warning'] ?? false;

        $builder
            ->add('withdrawal_type', ChoiceType::class, [
                'label' => 'Type de retrait',
                'choices' => [
                    'Retrait du montant total' => 'tontine',
                    'Montant personnalisé' => 'custom',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'tontine',
                'attr' => [
                    'class' => 'withdrawal-type-radio',
                ],
            ])

            ->add('custom_amount', NumberType::class, [
                'label' => 'Montant à retirer',
                'required' => false,
                'attr' => [
                    'class' => 'custom-amount-field',
                    'placeholder' => 'Entrez le montant à retirer',
                    'min' => 1000,
                    'step' => 10
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer un montant à retirer.',
                        'groups' => ['custom_amount'],
                    ]),
                    new GreaterThan([
                        'value' => 0,
                        'message' => 'Le montant doit être supérieur à 0.',
                        'groups' => ['custom_amount'],
                    ]),
                ],
            ])
            ->add('withdrawal_method', ChoiceType::class, [
                'label' => 'Mode de retrait',
                'choices' => [
                    'Mobile Money' => 'mobile_money',
                    'Retrait en agence' => 'in_person'
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'mobile_money',
                'attr' => [
                    'class' => 'withdrawal-method-radio',
                ],
            ])
            ->add('phone_number', TextType::class, [
                'label' => 'Numéro de téléphone',
                'required' => false,
                'attr' => [
                    'class' => 'phone-number-field',
                    'title' => 'Entrez un numéro de téléphone valide '
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre numéro de téléphone pour le Mobile Money.',
                        'groups' => ['mobile_money'],
                    ]),
              
                ],

            ]);

            if ($options['tontine']->isFraisPreleves() === false) {
                $builder->add('fee_payment_method', ChoiceType::class, [
                'label' => 'Paiement des frais',
                'mapped' => false, // IMPORTANT : ce n'est pas une propriété de l'entité
                'required' => true,
                'choices' => [
                    'Payer en ligne ' => 'online',
                    'Payer plus tard' => 'later',
                ],
                'expanded' => true,
            ]);
            }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'tontine' => null,
            'show_fee_warning' => false,
            'validation_groups' => function ($form) {
                $data = $form->getData();
                $groups = ['Default'];

                if ($data) {
                    if (isset($data['withdrawal_type']) && $data['withdrawal_type'] === 'custom') {
                        $groups[] = 'custom_amount';
                    }
                    if (isset($data['withdrawal_method']) && $data['withdrawal_method'] === 'mobile_money') {
                        $groups[] = 'mobile_money';
                    }
                }

                return $groups;
            },
        ]);

        $resolver->setRequired('tontine');
        $resolver->setAllowedTypes('tontine', Tontine::class);
        $resolver->setAllowedTypes('show_fee_warning', 'bool');
    }
}
