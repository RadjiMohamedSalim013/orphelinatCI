<?php

namespace App\Service;

use App\Entity\PropositionOrphelinat;
use App\Enum\PropositionStatut;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Informe le proposant d'une décision, uniquement s'il a laissé une adresse
 * e-mail. Le message reprend le statut réellement enregistré.
 */
class PropositionNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFrom,
    ) {
    }

    public function notifyDecision(PropositionOrphelinat $proposition): bool
    {
        $to = $proposition->getContactProposantEmail();
        if (null === $to || $proposition->getStatut()->isOpen()) {
            return false;
        }

        $suivi = $this->urlGenerator->generate('proposition_suivi', ['token' => $proposition->getSuiviToken()], UrlGeneratorInterface::ABSOLUTE_URL);

        $lignes = [sprintf('Bonjour %s,', $proposition->getNomProposant()), ''];
        if (PropositionStatut::Acceptee === $proposition->getStatut()) {
            $lignes[] = sprintf('Votre proposition « %s » a été acceptée et l\'établissement est désormais répertorié sur OrphaMap CI.', $proposition->getNom());
            if ($orphelinat = $proposition->getOrphelinat()) {
                $lignes[] = 'Fiche : '.$this->urlGenerator->generate('orphelinat_show', ['id' => $orphelinat->getId(), 'slug' => $orphelinat->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        } else {
            $lignes[] = sprintf('Votre proposition « %s » n\'a pas été retenue.', $proposition->getNom());
            if ($proposition->getMotifRefus()) {
                $lignes[] = 'Motif : '.$proposition->getMotifRefus();
            }
        }
        array_push($lignes, '', 'Suivi de votre demande : '.$suivi, '', 'Merci pour votre contribution.', 'L\'équipe OrphaMap CI');

        $email = (new Email())
            ->from($this->mailerFrom)
            ->to($to)
            ->subject(sprintf('Votre proposition %s : %s', $proposition->getReference(), mb_strtolower($proposition->getStatut()->label())))
            ->text(implode("\n", $lignes));

        try {
            $this->mailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Notification de proposition non envoyée.', ['proposition' => $proposition->getId(), 'exception' => $e]);

            return false;
        }
    }
}
