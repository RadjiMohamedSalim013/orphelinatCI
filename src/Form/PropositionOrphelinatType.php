<?php

namespace App\Form;

use App\Entity\PropositionOrphelinat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PropositionOrphelinatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'orphelinat',
                'constraints' => [
                    new NotBlank(message: 'Le nom est requis.'),
                    new Length(max: 255),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'attr' => ['rows' => 5],
                'constraints' => [
                    new NotBlank(message: 'La description est requise.'),
                    new Length(max: 2000),
                ],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse complète',
                'constraints' => [new NotBlank(message: 'L\'adresse est requise.')],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'constraints' => [new NotBlank(message: 'Le téléphone est requis.')],
            ])
            ->add('email', TextType::class, [
                'label' => 'Email de contact',
                'constraints' => [
                    new NotBlank(message: 'L\'email est requis.'),
                    new Email(message: 'Email invalide.'),
                ],
            ])
            ->add('image', FileType::class, [
                'label' => 'Photo de l\'orphelinat (JPG/PNG, max 2Mo)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new NotBlank(groups: ['upload_image']),
                ],
            ])
            ->add('document', FileType::class, [
                'label' => 'Document justificatif (PDF, max 5Mo)',
                'mapped' => false,
                'required' => false,
            ])
            ->add('pays', TextType::class, [
                'label' => 'Pays',
                'constraints' => [new NotBlank(message: 'Le pays est requis.')],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'constraints' => [new NotBlank(message: 'La ville est requise.')],
            ])
            ->add('quartier', TextType::class, [
                'label' => 'Quartier',
                'constraints' => [new NotBlank(message: 'Le quartier est requis.')],
            ])
            ->add('nomProposant', TextType::class, [
                'label' => 'Votre nom (proposant)',
                'constraints' => [new NotBlank(message: 'Votre nom est requis.')],
            ])
            ->add('contactProposant', TextType::class, [
                'label' => 'Votre contact téléphone/email',
                'constraints' => [new NotBlank(message: 'Votre contact est requis.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropositionOrphelinat::class,
        ]);
    }
}

