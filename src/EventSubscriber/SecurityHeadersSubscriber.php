<?php

namespace App\EventSubscriber;

use App\Security\NonceCsp;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité ajoutés à chaque réponse.
 *
 * - CSP : scripts du site uniquement (fichiers servis par le site, ou balises portant
 *   le nonce de la requête), aucun cadre, formulaires envoyés au site seulement.
 *   En mode debug, elle est « Report-Only » : les écarts s'affichent dans la console
 *   du navigateur sans casser les outils de développement.
 * - HSTS en HTTPS hors debug : le navigateur refuse ensuite tout accès en HTTP.
 */
class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NonceCsp $nonce,
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
        #[Autowire('%env(MAP_TILE_URL)%')] private readonly string $tuiles,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        $headers = $response->headers;
        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self)',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        foreach ($defaults as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }

        // Seulement sur les pages réellement servies : une 404 de /admin doit rester identique
        // à celle d'une adresse inexistante (voir AdministrationInvisibleSubscriber).
        if ($response->getStatusCode() < 400 && (str_starts_with($request->getPathInfo(), '/admin') || 'app_login' === $request->attributes->get('_route'))) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure() && !$this->debug) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        if (str_contains((string) $headers->get('Content-Type', 'text/html'), 'text/html')) {
            $headers->set($this->debug ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', $this->politique($request->isSecure()));
        }
    }

    private function politique(bool $https): string
    {
        $directives = [
            "default-src 'self'",
            sprintf("script-src 'self' 'nonce-%s'", $this->nonce->valeur()),
            // Attributs style="--i: …" des gabarits et positionnement de la carte
            "style-src 'self' 'unsafe-inline'",
            // blob: aperçu des photos avant envoi ; data: images intégrées des bibliothèques
            "img-src 'self' data: blob: ".$this->origineTuiles(),
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];
        if ($https) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    /** Origine du serveur de tuiles (« {s}.tile… » devient « *.tile… »). */
    private function origineTuiles(): string
    {
        $url = parse_url(str_replace('{s}', 'x', $this->tuiles));
        if (!isset($url['scheme'], $url['host'])) {
            return '';
        }
        $hote = str_contains($this->tuiles, '{s}.') ? '*.'.substr($url['host'], 2) : $url['host'];

        return $url['scheme'].'://'.$hote;
    }
}
