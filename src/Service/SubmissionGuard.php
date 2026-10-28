<?php

namespace App\Service;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Protection des formulaires publics contre le spam :
 * champ piège invisible (honeypot), délai minimal de remplissage et
 * limitation du nombre d'envois par adresse IP.
 */
class SubmissionGuard
{
    public const HONEYPOT_FIELD = 'site_internet';
    public const TIMESTAMP_FIELD = 'rendu_le';
    private const MIN_SECONDS = 3;

    public function __construct(
        private readonly RateLimiterFactoryInterface $publicSubmissionLimiter,
    ) {
    }

    /**
     * Retourne un message d'erreur si l'envoi doit être rejeté, sinon null.
     * Le quota n'est consommé que pour un envoi par ailleurs valide.
     */
    public function check(Request $request, FormInterface $form): ?string
    {
        if ($form->has(self::HONEYPOT_FIELD) && '' !== trim((string) $form->get(self::HONEYPOT_FIELD)->getData())) {
            return 'Votre envoi n\'a pas pu être accepté.';
        }

        if ($form->has(self::TIMESTAMP_FIELD)) {
            $renduLe = (int) $form->get(self::TIMESTAMP_FIELD)->getData();
            if ($renduLe > 0 && time() - $renduLe < self::MIN_SECONDS) {
                return 'Envoi trop rapide. Vérifiez vos informations puis renvoyez le formulaire.';
            }
        }

        $limit = $this->publicSubmissionLimiter->create($request->getClientIp() ?? 'anonyme')->consume();
        if (!$limit->isAccepted()) {
            return 'Trop d\'envois depuis votre connexion. Merci de réessayer dans une heure.';
        }

        return null;
    }
}
