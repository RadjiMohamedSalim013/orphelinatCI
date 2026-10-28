<?php

namespace App\Form;

use App\Entity\Commune;
use App\Entity\Orphelinat;
use App\Entity\Quartier;
use App\Enum\OrphelinatStatut;
use App\Enum\VerificationStatut;
use App\Repository\CommuneRepository;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Fiche d'établissement côté administration.
 */
class OrphelinatType extends AbstractType
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom officiel',
            ])
            ->add('commune', EntityType::class, [
                'label' => 'Commune',
                'class' => Commune::class,
                'query_builder' => static fn (CommuneRepository $r) => $r->createOrderedQueryBuilder(),
                'choice_label' => 'nom',
                'group_by' => static fn (Commune $c) => sprintf('%s (%s)', $c->getVille()?->getNom(), $c->getVille()?->getPays()?->getNom()),
                'placeholder' => 'Choisir une commune',
            ])
            ->add('quartier', EntityType::class, [
                'label' => 'Quartier existant',
                'class' => Quartier::class,
                'query_builder' => static fn (EntityRepository $r) => $r->createQueryBuilder('q')->orderBy('q.nom', 'ASC'),
                'choice_label' => 'nom',
                'choice_attr' => static fn (Quartier $q) => ['data-commune' => $q->getCommune()?->getId()],
                'placeholder' => 'Aucun',
                'required' => false,
            ])
            ->add('nouveauQuartier', TextType::class, [
                'label' => 'ou nouveau quartier',
                'mapped' => false,
                'required' => false,
                'constraints' => [new Length(max: 255)],
                'attr' => ['placeholder' => 'Créé dans la commune choisie'],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse ou repères',
            ])
            ->add('latitude', TextType::class, [
                'label' => 'Latitude',
                'required' => false,
                'attr' => ['inputmode' => 'decimal', 'placeholder' => 'Ex. : 5.3364'],
            ])
            ->add('longitude', TextType::class, [
                'label' => 'Longitude',
                'required' => false,
                'attr' => ['inputmode' => 'decimal', 'placeholder' => 'Ex. : -4.0267'],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'required' => false,
            ])
            ->add('siteWeb', UrlType::class, [
                'label' => 'Site internet',
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('reseauSocial', UrlType::class, [
                'label' => 'Page officielle (réseau social)',
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Présentation',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('services', TextareaType::class, [
                'label' => 'Services et accompagnement',
                'required' => false,
                'help' => 'Uniquement des informations vérifiées (scolarisation, santé, accueil…).',
                'attr' => ['rows' => 3],
            ])
            ->add('verification', EnumType::class, [
                'class' => VerificationStatut::class,
                'label' => 'Vérification',
                'choice_label' => static fn (VerificationStatut $v) => $v->label(),
                'help' => 'Ne cochez « Vérifié » qu\'après confirmation directe (visite, appel, document officiel).',
            ])
            ->add('statut', EnumType::class, [
                'class' => OrphelinatStatut::class,
                'label' => 'Situation',
                'choice_label' => static fn (OrphelinatStatut $s) => $s->label(),
            ])
            ->add('image', FileType::class, [
                'label' => 'Photo principale',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => implode(',', self::IMAGE_MIMES)],
                'constraints' => [
                    new File(maxSize: '5M', mimeTypes: self::IMAGE_MIMES, mimeTypesMessage: 'Image JPG, PNG ou WEBP attendue.'),
                ],
            ])
            ->add('photos', FileType::class, [
                'label' => 'Ajouter des photos à la galerie',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => ['accept' => implode(',', self::IMAGE_MIMES)],
                'constraints' => [
                    new Count(max: 10, maxMessage: '10 photos maximum par envoi.'),
                    new All([new File(maxSize: '5M', mimeTypes: self::IMAGE_MIMES, mimeTypesMessage: 'Image JPG, PNG ou WEBP attendue.')]),
                ],
            ])
            ->add('document', FileType::class, [
                'label' => 'Document officiel public (PDF)',
                'mapped' => false,
                'required' => false,
                'help' => 'Visible par tous sur la fiche. Ne pas y mettre de justificatif privé.',
                'attr' => ['accept' => 'application/pdf'],
                'constraints' => [
                    new File(maxSize: '10M', mimeTypes: ['application/pdf'], mimeTypesMessage: 'PDF attendu.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Orphelinat::class,
        ]);
    }
}
