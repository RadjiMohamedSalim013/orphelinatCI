<?php

namespace App\Form;

use App\Entity\Signalement;
use App\Enum\SignalementType as TypeSignalement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SignalementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => TypeSignalement::class,
                'label' => 'Quel est le problème ?',
                'choice_label' => static fn (TypeSignalement $t) => $t->label(),
                'expanded' => true,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Précisez l\'information correcte',
                'help' => 'Ex. : le numéro a changé, voici le nouveau ; l\'établissement a déménagé à…',
                'attr' => ['rows' => 5, 'maxlength' => 2000, 'minlength' => 10],
            ])
            ->add('contact', TextType::class, [
                'label' => 'Votre téléphone ou e-mail (facultatif)',
                'help' => 'Seulement si vous acceptez d\'être recontacté. Jamais publié.',
                'required' => false,
                'attr' => ['maxlength' => 180],
            ]);

        AntiSpamFields::add($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Signalement::class,
        ]);
    }
}
