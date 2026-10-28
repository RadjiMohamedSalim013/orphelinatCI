<?php

namespace App\Tests\Functional;

use App\Entity\Orphelinat;
use App\Entity\Signalement;
use App\Enum\OrphelinatStatut;
use App\Enum\SignalementStatut;
use App\Enum\SignalementType;

class SignalementTest extends AppWebTestCase
{
    public function testSignalementPuisTraitementParLAdministration(): void
    {
        $orphelinat = $this->ref['espoir'];

        // Le visiteur signale une fermeture depuis la fiche
        $crawler = $this->fiche($orphelinat);
        $crawler = $this->client->click($crawler->selectLink('Signaler une erreur')->link());
        $form = $crawler->selectButton('Envoyer le signalement')->form();
        $this->client->submit($form, [
            'signalement[type]' => 'ferme',
            'signalement[message]' => 'L\'établissement a fermé ses portes en septembre.',
        ] + $this->antiSpam('signalement'));

        self::assertResponseRedirects('/orphelinats/'.$orphelinat->getId().'-foyer-espoir');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Votre signalement a été transmis');

        // La fiche n'est pas modifiée par le public
        self::assertSame(OrphelinatStatut::Actif, $this->reload($orphelinat)->getStatut());
        $signalement = $this->em->getRepository(Signalement::class)->findOneBy([]);
        self::assertSame(SignalementStatut::Nouveau, $signalement->getStatut());

        // L'administrateur le voit et le traite en confirmant la fermeture
        $this->loginAdmin();
        $this->client->request('GET', '/admin');
        self::assertSelectorTextContains('body', 'Signalements à traiter');

        $crawler = $this->client->request('GET', '/admin/signalements/'.$signalement->getId());
        $form = $crawler->selectButton('Marquer comme traité')->form(['decision' => 'Fermeture confirmée par téléphone.', 'marquer_ferme' => '1']);
        $this->client->submit($form);

        $signalement = $this->reload($signalement);
        self::assertSame(SignalementStatut::Traite, $signalement->getStatut());
        self::assertNotNull($signalement->getTraiteAt());
        self::assertSame(OrphelinatStatut::Ferme, $signalement->getOrphelinat()->getStatut());

        // L'établissement fermé sort des résultats de recherche
        $this->client->request('GET', '/orphelinats?q=Espoir');
        self::assertSelectorTextContains('body', 'Aucun établissement');
    }

    public function testRejetExigeUnCommentaire(): void
    {
        $signalement = (new Signalement())
            ->setOrphelinat($this->em->find(Orphelinat::class, $this->ref['espoir']->getId()))
            ->setType(SignalementType::Autre)
            ->setMessage('Message de test assez long');
        $this->em->persist($signalement);
        $this->em->flush();

        $this->loginAdmin();
        $crawler = $this->client->request('GET', '/admin/signalements/'.$signalement->getId());
        $this->client->submit($crawler->selectButton('Rejeter (commentaire requis)')->form());
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'Indiquez pourquoi');
        self::assertSame(SignalementStatut::Nouveau, $this->reload($signalement)->getStatut());
    }

    public function testMessageTropCourtRefuse(): void
    {
        $crawler = $this->client->request('GET', '/orphelinats/'.$this->ref['espoir']->getId().'/signaler');
        $this->client->submit($crawler->selectButton('Envoyer le signalement')->form(), [
            'signalement[type]' => 'contact',
            'signalement[message]' => 'faux',
        ] + $this->antiSpam('signalement'));

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Signalement::class)->count([]));
    }
}
