<?php

namespace App\Form;

use App\Service\SubmissionGuard;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Champs non mappés vérifiés par SubmissionGuard.
 */
final class AntiSpamFields
{
    public static function add(FormBuilderInterface $builder): void
    {
        $builder
            ->add(SubmissionGuard::HONEYPOT_FIELD, TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Ne pas remplir ce champ',
                'row_attr' => ['class' => 'om-hp', 'aria-hidden' => 'true'],
                'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            ])
            ->add(SubmissionGuard::TIMESTAMP_FIELD, HiddenType::class, [
                'mapped' => false,
                'data' => (string) time(),
            ]);
    }
}
