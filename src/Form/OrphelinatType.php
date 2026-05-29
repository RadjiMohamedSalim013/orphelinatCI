<?php

namespace App\Form;

use App\Entity\Orphelinat;
use App\Entity\Quartier;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class OrphelinatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'orphelinat',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 5],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse complète',
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('image', FileType::class, [
                'label' => 'Photo (JPG, PNG)',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => 'image/*'],
                'constraints' => [
                    new File([
                        'maxSize' => '5m',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG).',
                    ])
                ],
            ])
            ->add('document', FileType::class, [
                'label' => 'Document (PDF)',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => '.pdf'],
                'constraints' => [
                    new File([
                        'maxSize' => '10m',
                        'mimeTypes' => ['application/pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un PDF valide.',
                    ])
                ],
            ])
            ->add('quartier', EntityType::class, [
                'class' => Quartier::class,
                'choice_label' => function (Quartier $q) {
                    return $q->getNom() . ' (' . $q->getCommune()->getNom() . ', ' . $q->getCommune()->getVille()->getNom() . ', ' . $q->getCommune()->getVille()->getPays()->getNom() . ')';
                },
                'placeholder' => '-- Choisir un quartier existant --',
                'required' => false,
            ])
            ->add('nouveau_pays', TextType::class, [
                'label' => 'Nouveau pays',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Côte d\'Ivoire'],
            ])
            ->add('nouvelle_ville', TextType::class, [
                'label' => 'Nouvelle ville',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Abidjan'],
            ])
            ->add('nouvelle_commune', TextType::class, [
                'label' => 'Nouvelle commune',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Cocody'],
            ])
            ->add('nouveau_quartier', TextType::class, [
                'label' => 'Nouveau quartier',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Deux-Plateaux'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Orphelinat::class,
        ]);
    }
}
