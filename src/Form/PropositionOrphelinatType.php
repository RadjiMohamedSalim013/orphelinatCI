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
use Symfony\Component\Validator\Constraints\File;

class PropositionOrphelinatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

            // =========================
            // INFORMATIONS ORPHELINAT
            // =========================

            ->add('nom', TextType::class, [
                'label' => 'Nom de l’orphelinat',
                'attr' => [
                    'placeholder' => 'Ex : Orphelinat Saint Joseph',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nom est requis.'),
                    new Length(max: 255),
                ],
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Présentation de l’orphelinat',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Présentez brièvement l’orphelinat, sa mission, son histoire et les enfants accueillis...',
                ],
                'constraints' => [
                    new NotBlank(message: 'La description est requise.'),
                    new Length(max: 2000),
                ],
            ])

            ->add('telephone', TextType::class, [
                'label' => 'Numéro de téléphone',
                'attr' => [
                    'placeholder' => '+225 07 00 00 00 00',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le téléphone est requis.'),
                ],
            ])

            ->add('email', TextType::class, [
                'label' => 'Adresse e-mail',
                'attr' => [
                    'placeholder' => 'contact@orphelinat.org',
                ],
                'constraints' => [
                    new NotBlank(message: 'L’email est requis.'),
                    new Email(message: 'Email invalide.'),
                ],
            ])

            // =========================
            // LOCALISATION
            // =========================

            ->add('pays', TextType::class, [
                'label' => 'Pays',
                'attr' => [
                    'placeholder' => 'Ex : Côte d’Ivoire',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le pays est requis.'),
                ],
            ])

            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr' => [
                    'placeholder' => 'Ex : Abidjan',
                ],
                'constraints' => [
                    new NotBlank(message: 'La ville est requise.'),
                ],
            ])

            ->add('quartier', TextType::class, [
                'label' => 'Quartier',
                'attr' => [
                    'placeholder' => 'Ex : Cocody, Yopougon...',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le quartier est requis.'),
                ],
            ])

            ->add('adresse', TextType::class, [
                'label' => 'Adresse complète',
                'attr' => [
                    'placeholder' => 'Adresse précise de l’orphelinat',
                ],
                'constraints' => [
                    new NotBlank(message: 'L’adresse est requise.'),
                ],
            ])

            // =========================
            // DOCUMENTS
            // =========================

            ->add('image', FileType::class, [
                'label' => 'Photo principale',
                'mapped' => false,
                'required' => false,
                'help' => 'Ajoutez une photo permettant d’identifier facilement l’orphelinat.',
                'constraints' => [
                    new NotBlank(groups: ['upload_image']),
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Le fichier image doit être de type JPG, PNG ou WEBP.',
                    ),
                ],
            ])

            ->add('document', FileType::class, [
                'label' => 'Document justificatif',
                'mapped' => false,
                'required' => false,
                'help' => 'Autorisation, récépissé ou document administratif.',
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: ['application/pdf'],
                        mimeTypesMessage: 'Le document doit être un PDF.',
                    ),
                ],
            ])

            // =========================
            // PROPOSANT
            // =========================

            ->add('nomProposant', TextType::class, [
                'label' => 'Votre nom complet',
                'attr' => [
                    'placeholder' => 'Ex : Mohamed Salim',
                ],
                'constraints' => [
                    new NotBlank(message: 'Votre nom est requis.'),
                ],
            ])

            ->add('contactProposant', TextType::class, [
                'label' => 'Téléphone ou e-mail',
                'attr' => [
                    'placeholder' => '+225 07 00 00 00 00 ou email@example.com',
                ],
                'constraints' => [
                    new NotBlank(message: 'Votre contact est requis.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropositionOrphelinat::class,
        ]);
    }
}