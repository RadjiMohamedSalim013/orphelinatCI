<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mesure d'audience interne, sans cookie ni service tiers.
 *
 * Compte les pages publiques consultées par jour, les visiteurs uniques (via une
 * empreinte quotidienne non réversible), le type d'appareil et la source de
 * trafic. Sont exclus : robots, administrateurs, et navigateurs demandant à ne
 * pas être suivis (Do Not Track / Global Privacy Control).
 */
class MesureAudience
{
    /** Pages publiques mesurées (nom de route). */
    private const ROUTES = ['home', 'orphelinat_index', 'orphelinat_show', 'proposer_orphelinat'];

    private const ROBOTS = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|monitor|uptime|curl|wget|python|java\/|go-http|headless|lighthouse|pingdom|scrapy|httpclient|symfony/i';

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly string $secret,
    ) {
    }

    public function doitMesurer(Request $request, Response $response): bool
    {
        $route = (string) $request->attributes->get('_route');
        $agent = (string) $request->headers->get('User-Agent');

        return $request->isMethod('GET')
            && 200 === $response->getStatusCode()
            && in_array($route, self::ROUTES, true)
            && '' !== $agent
            && !preg_match(self::ROBOTS, $agent)
            && '1' !== $request->headers->get('DNT')
            && '1' !== $request->headers->get('Sec-GPC');
    }

    public function enregistrer(Request $request, ?\DateTimeImmutable $jour = null): void
    {
        $jour ??= new \DateTimeImmutable('today');
        $date = $jour->format('Y-m-d');

        try {
            $empreinte = hash_hmac('sha256', $request->getClientIp().'|'.$request->headers->get('User-Agent').'|'.$date, $this->secret);
            $nouveau = 1 === $this->connection->executeStatement(
                'INSERT IGNORE INTO stat_visiteur (jour, empreinte) VALUES (?, ?)',
                [$date, $empreinte],
            );

            $appareil = $this->appareil((string) $request->headers->get('User-Agent'));
            $this->connection->executeStatement(
                'INSERT INTO stat_jour (jour, vues, visiteurs, mobile, tablette, ordinateur) VALUES (?, 1, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE vues = vues + 1, visiteurs = visiteurs + VALUES(visiteurs),
                     mobile = mobile + VALUES(mobile), tablette = tablette + VALUES(tablette), ordinateur = ordinateur + VALUES(ordinateur)',
                [$date, (int) $nouveau, (int) ($nouveau && 'mobile' === $appareil), (int) ($nouveau && 'tablette' === $appareil), (int) ($nouveau && 'ordinateur' === $appareil)],
            );

            foreach ($this->cles($request, $nouveau) as $cle) {
                $this->connection->executeStatement(
                    'INSERT INTO stat_compteur (jour, cle, total) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE total = total + 1',
                    [$date, mb_substr($cle, 0, 150)],
                );
            }

            // Les empreintes ne servent qu'à dédoublonner la journée : purge régulière.
            if (1 === random_int(1, 50)) {
                $this->connection->executeStatement('DELETE FROM stat_visiteur WHERE jour < ?', [$jour->modify('-2 days')->format('Y-m-d')]);
            }
        } catch (\Throwable $e) {
            // La mesure ne doit jamais perturber la navigation.
            $this->logger->warning('Mesure d\'audience non enregistrée.', ['exception' => $e]);
        }
    }

    /**
     * @return list<string>
     */
    private function cles(Request $request, bool $nouveauVisiteur): array
    {
        $route = (string) $request->attributes->get('_route');
        $cles = match ($route) {
            'orphelinat_show' => ['fiche:'.(int) $request->attributes->get('id')],
            'orphelinat_index' => array_filter([
                'page:orphelinat_index',
                is_numeric($c = $request->query->all()['commune'] ?? null) ? 'commune:'.(int) $c : null,
            ]),
            default => ['page:'.$route],
        };

        // Source comptée une fois par visiteur et par jour (arrivée sur le site).
        if ($nouveauVisiteur) {
            $cles[] = 'source:'.$this->source($request);
        }

        return array_values($cles);
    }

    private function source(Request $request): string
    {
        $hote = parse_url((string) $request->headers->get('Referer'), PHP_URL_HOST);
        if (!is_string($hote) || '' === $hote || $hote === $request->getHost()) {
            return 'direct';
        }

        return preg_replace('/^(www|m|l|lm)\./', '', strtolower($hote));
    }

    private function appareil(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/i', $agent) => 'tablette',
            (bool) preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile/i', $agent) => 'mobile',
            default => 'ordinateur',
        };
    }
}
