<?php

namespace App\Form;

use App\Entity\Commune;
use App\Entity\PropositionOrphelinat;
use App\Repository\CommuneRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * Formulaire public de proposition. La commune est choisie dans le référentiel
 * (listes en cascade pays > ville > commune) ou saisie si elle n'y figure pas.
 */
class PropositionOrphelinatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'établissement',
                'attr' => ['placeholder' => 'Ex. : Orphelinat Sainte-Famille', 'maxlength' => 255, 'minlength' => 3],
            ])
            ->add('commune', EntityType::class, [
                'label' => 'Commune',
                'class' => Commune::class,
                'query_builder' => static fn (CommuneRepository $r) => $r->createOrderedQueryBuilder(),
                'choice_label' => 'nom',
                'choice_attr' => static fn (Commune $c) => ['data-ville' => $c->getVille()?->getId()],
                'group_by' => static fn (Commune $c) => $c->getVille()?->getNom(),
                'placeholder' => 'Choisissez une commune',
                'required' => false,
            ])
            ->add('communeAbsente', CheckboxType::class, [
                'label' => 'Ma ville ou ma commune n\'est pas dans la liste',
                'mapped' => false,
                'required' => false,
            ])
            ->add('pays', TextType::class, [
                'label' => 'Pays',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : Côte d\'Ivoire'],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : Bouaké'],
            ])
            ->add('communeSaisie', TextType::class, [
                'label' => 'Commune',
                'required' => false,
                'attr' => ['placeholder' => 'Nom de la commune'],
            ])
            ->add('quartier', TextType::class, [
                'label' => 'Quartier',
                'required' => false,
                'attr' => ['placeholder' => 'Ex. : Riviera 2'],
            ])
            ->add('adresse', TextType::class, [
                'label' => 'Adresse ou repères',
                'attr' => ['placeholder' => 'Rue, lot, repère connu (près de…)', 'maxlength' => 255],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone de l\'établissement',
                'help' => 'Indispensable pour que l’équipe puisse vérifier l’établissement.',
                'attr' => ['placeholder' => '+225 07 00 00 00 00', 'inputmode' => 'tel', 'autocomplete' => 'tel'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail de l\'établissement',
                'required' => false,
                'attr' => ['placeholder' => 'contact@exemple.org'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Présentation',
                'required' => false,
                'help' => 'Mission, public accueilli, activités. Ne mentionnez aucun enfant nommément.',
                'attr' => ['rows' => 4, 'maxlength' => 2000],
            ])
            ->add('source', TextareaType::class, [
                'label' => 'Comment connaissez-vous cet établissement ?',
                'help' => 'Ex. : je suis bénévole sur place, page officielle, article de presse (lien), numéro d\'agrément…',
                'attr' => ['rows' => 3, 'maxlength' => 1000, 'minlength' => 10],
            ])
            ->add('image', FileType::class, [
                'label' => 'Photo de la façade (facultatif)',
                'mapped' => false,
                'required' => false,
                'help' => 'JPG, PNG ou WEBP, 5 Mo maximum. Pas de photo d\'enfants.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'La photo doit être au format JPG, PNG ou WEBP.',
                    ),
                ],
            ])
            ->add('document', FileType::class, [
                'label' => 'Justificatif (facultatif)',
                'mapped' => false,
                'required' => false,
                'help' => 'Agrément, récépissé… PDF ou image, 10 Mo maximum. Consulté uniquement par l\'équipe de vérification.',
                'attr' => ['accept' => 'application/pdf,image/jpeg,image/png,image/webp'],
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Le justificatif doit être un PDF ou une image.',
                    ),
                ],
            ])
            ->add('nomProposant', TextType::class, [
                'label' => 'Votre nom',
                'attr' => ['autocomplete' => 'name', 'maxlength' => 255],
            ])
            ->add('contactProposant', TextType::class, [
                'label' => 'Votre téléphone ou e-mail',
                'help' => 'Utilisé uniquement pour vérifier la proposition et vous informer de la décision. Jamais publié.',
                'attr' => ['maxlength' => 255],
            ])
            ->add('consentement', CheckboxType::class, [
                'label' => 'J\'atteste que ces informations sont exactes à ma connaissance.',
                'mapped' => false,
                'constraints' => [new IsTrue(message: 'Merci de confirmer l\'exactitude des informations.')],
            ]);

        AntiSpamFields::add($builder);

        // Une seule source de vérité pour la localisation : la commune choisie
        // impose pays et ville ; sinon on garde la saisie libre.
        $builder->addEventListener(FormEvents::SUBMIT, static function (FormEvent $event): void {
            /** @var PropositionOrphelinat $proposition */
            $proposition = $event->getData();
            $form = $event->getForm();

            if ($form->get('communeAbsente')->getData()) {
                $proposition->setCommune(null);
            }

            if ($commune = $proposition->getCommune()) {
                $proposition
                    ->setVille($commune->getVille()?->getNom())
                    ->setPays($commune->getVille()?->getPays()?->getNom())
                    ->setCommuneSaisie(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PropositionOrphelinat::class,
        ]);
    }
}
