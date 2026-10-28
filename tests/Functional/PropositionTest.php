<?php

namespace App\Tests\Functional;

use App\Entity\Orphelinat;
use App\Entity\PropositionOrphelinat;
use App\Enum\PropositionStatut;

class PropositionTest extends AppWebTestCase
{
    private function proposer(array $valeurs): void
    {
        $crawler = $this->client->request('GET', '/proposer-orphelinat');
        $form = $crawler->selectButton('Envoyer la proposition')->form();
        $this->client->submit($form, $valeurs + $this->antiSpam('proposition_orphelinat'));
    }

    private function valeursValides(): array
    {
        return [
            'proposition_orphelinat[nom]' => 'Orphelinat Bon Berger',
            'proposition_orphelinat[commune]' => (string) $this->ref['cocody']->getId(),
            'proposition_orphelinat[quartier]' => 'Riviera 2',
            'proposition_orphelinat[adresse]' => 'Près de la pharmacie Riviera',
            'proposition_orphelinat[telephone]' => '+225 05 44 33 22 11',
            'proposition_orphelinat[source]' => 'Je suis bénévole dans cet établissement depuis 2022.',
            'proposition_orphelinat[nomProposant]' => 'Awa K.',
            'proposition_orphelinat[contactProposant]' => 'awa@example.org',
            'proposition_orphelinat[consentement]' => '1',
        ];
    }

    public function testParcoursCompletPropositionPuisAcceptationPuisPublication(): void
    {
        // 1. Le visiteur propose un établissement
        $this->proposer($this->valeursValides());
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Suivi de votre proposition');
        self::assertSelectorTextContains('body', 'Orphelinat Bon Berger');

        $proposition = $this->em->getRepository(PropositionOrphelinat::class)->findOneBy(['nom' => 'Orphelinat Bon Berger']);
        self::assertNotNull($proposition);
        self::assertSame(PropositionStatut::EnAttente, $proposition->getStatut());
        self::assertSame('Abidjan', $proposition->getVille(), 'Ville déduite de la commune choisie');
        self::assertSame('Côte d’Ivoire', $proposition->getPays());

        // 2. Elle n'est pas publiée automatiquement
        $this->client->request('GET', '/orphelinats?q=Bon+Berger');
        self::assertSelectorTextContains('body', 'Aucun établissement');

        // 3. Elle apparaît dans l'administration
        $this->loginAdmin();
        $this->client->request('GET', '/admin/propositions');
        self::assertSelectorTextContains('table', 'Orphelinat Bon Berger');

        // 4. L'administrateur la passe en vérification puis l'accepte
        $crawler = $this->client->request('GET', '/admin/propositions/'.$proposition->getId());
        $this->client->submit($crawler->selectButton('Passer en vérification')->form(['note' => 'Appel au responsable']));
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'En cours de vérification');

        $this->client->submit($crawler->selectButton('Accepter et créer la fiche')->form(['verifie' => '1']));
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Proposition acceptée');

        $proposition = $this->reload($proposition);
        self::assertSame(PropositionStatut::Acceptee, $proposition->getStatut());
        $orphelinat = $proposition->getOrphelinat();
        self::assertNotNull($orphelinat);
        self::assertTrue($orphelinat->isVerifie());
        self::assertSame('Riviera 2', $orphelinat->getQuartier()?->getNom());

        // 5. L'établissement apparaît dans l'annuaire public
        $this->client->request('GET', sprintf('/orphelinats?commune=%d', $this->ref['cocody']->getId()));
        self::assertSelectorTextContains('body', 'Orphelinat Bon Berger');

        // 6. Le proposant voit la décision réelle
        $this->client->request('GET', '/proposition/suivi/'.$proposition->getSuiviToken());
        self::assertSelectorTextContains('body', 'Publiée');
    }

    public function testUneSecondeAcceptationNeCreePasDeDoublon(): void
    {
        $this->proposer($this->valeursValides());
        $proposition = $this->em->getRepository(PropositionOrphelinat::class)->findOneBy(['nom' => 'Orphelinat Bon Berger']);
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/propositions/'.$proposition->getId());
        $form = $crawler->selectButton('Accepter et créer la fiche')->form();
        $this->client->submit($form);
        // Rejeu du même formulaire (double clic, onglet resté ouvert)
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', 'déjà traitée');
        self::assertSame(1, $this->em->getRepository(Orphelinat::class)->count(['nom' => 'Orphelinat Bon Berger']));
    }

    public function testRefusAvecMotifObligatoire(): void
    {
        $this->proposer($this->valeursValides());
        $proposition = $this->em->getRepository(PropositionOrphelinat::class)->findOneBy(['nom' => 'Orphelinat Bon Berger']);
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/propositions/'.$proposition->getId());
        $this->client->submit($crawler->selectButton('Refuser')->form(['motif' => '   ']));
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'motif du refus est obligatoire');
        self::assertSame(PropositionStatut::EnAttente, $this->reload($proposition)->getStatut());

        $crawler = $this->client->request('GET', '/admin/propositions/'.$proposition->getId());
        $this->client->submit($crawler->selectButton('Refuser')->form(['motif' => 'Établissement déjà répertorié.']));
        $proposition = $this->reload($proposition);
        self::assertSame(PropositionStatut::Refusee, $proposition->getStatut());

        $this->client->request('GET', '/proposition/suivi/'.$proposition->getSuiviToken());
        self::assertSelectorTextContains('body', 'Établissement déjà répertorié.');
    }

    public function testDoublonPotentielSignaleAuxAdministrateurs(): void
    {
        $valeurs = ['proposition_orphelinat[nom]' => 'Orphelinat St Joseph', 'proposition_orphelinat[telephone]' => '07 11 22 33 44'] + $this->valeursValides();
        $this->proposer($valeurs);
        $proposition = $this->em->getRepository(PropositionOrphelinat::class)->findOneBy(['nom' => 'Orphelinat St Joseph']);

        $this->loginAdmin();
        $this->client->request('GET', '/admin/propositions/'.$proposition->getId());
        self::assertSelectorTextContains('body', 'doublon possible');
        self::assertSelectorTextContains('body', 'Même numéro de téléphone');
    }

    public function testDonneesInvalidesRejetees(): void
    {
        $this->proposer([
            'proposition_orphelinat[nom]' => 'A',
            'proposition_orphelinat[adresse]' => '',
            'proposition_orphelinat[email]' => 'pas-un-email',
            'proposition_orphelinat[source]' => 'court',
            'proposition_orphelinat[nomProposant]' => '',
            'proposition_orphelinat[contactProposant]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        $texte = $this->client->getCrawler()->filter('main')->text();
        foreach (['au moins 3 caractères', 'adresse ou des repères', 'Adresse e-mail invalide', 'Choisissez une commune', 'Votre nom est requis', 'exactitude des informations'] as $message) {
            self::assertStringContainsString($message, $texte);
        }
        self::assertSame(0, $this->em->getRepository(PropositionOrphelinat::class)->count([]));
    }

    public function testCommuneHorsReferentielAcceptee(): void
    {
        $valeurs = $this->valeursValides();
        unset($valeurs['proposition_orphelinat[commune]']);
        $this->proposer($valeurs + [
            'proposition_orphelinat[communeAbsente]' => '1',
            'proposition_orphelinat[pays]' => 'Côte d’Ivoire',
            'proposition_orphelinat[ville]' => 'Korhogo',
            'proposition_orphelinat[communeSaisie]' => 'Korhogo',
        ]);

        self::assertResponseRedirects();
        $proposition = $this->em->getRepository(PropositionOrphelinat::class)->findOneBy([]);
        self::assertNull($proposition->getCommune());
        self::assertSame('Korhogo', $proposition->getCommuneSaisie());
    }

    public function testProtectionsAntiSpam(): void
    {
        // Champ piège rempli par un robot
        $crawler = $this->client->request('GET', '/proposer-orphelinat');
        $form = $crawler->selectButton('Envoyer la proposition')->form();
        $this->client->submit($form, ['proposition_orphelinat[site_internet]' => 'http://spam.example', 'proposition_orphelinat[rendu_le]' => (string) (time() - 30)] + $this->valeursValides());
        self::assertSelectorTextContains('body', 'n\'a pas pu être accepté');

        // Envoi instantané
        $crawler = $this->client->request('GET', '/proposer-orphelinat');
        $form = $crawler->selectButton('Envoyer la proposition')->form();
        $this->client->submit($form, ['proposition_orphelinat[rendu_le]' => (string) time()] + $this->valeursValides());
        self::assertSelectorTextContains('body', 'Envoi trop rapide');

        self::assertSame(0, $this->em->getRepository(PropositionOrphelinat::class)->count([]));
    }

    public function testJetonCsrfObligatoire(): void
    {
        $this->client->request('POST', '/proposer-orphelinat', ['proposition_orphelinat' => [
            'nom' => 'Sans jeton', 'commune' => $this->ref['cocody']->getId(), 'adresse' => 'x', 'source' => 'Source suffisamment longue',
            'nomProposant' => 'X', 'contactProposant' => 'x@example.org', 'consentement' => '1', 'rendu_le' => time() - 30,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(PropositionOrphelinat::class)->count([]));
    }

    public function testLienDeSuiviInconnu(): void
    {
        $this->client->request('GET', '/proposition/suivi/'.str_repeat('a', 32));
        self::assertResponseStatusCodeSame(404);
    }
}
