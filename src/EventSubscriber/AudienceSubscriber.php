<?php

namespace App\EventSubscriber;

use App\Service\MesureAudience;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Enregistre la visite une fois la réponse envoyée, sans retarder l'affichage.
 */
class AudienceSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MesureAudience $mesure,
        private readonly Security $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->mesure->doitMesurer($request, $event->getResponse())) {
            return;
        }

        // Les visites de l'équipe ne faussent pas les chiffres.
        if ($request->hasPreviousSession() && $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $this->mesure->enregistrer($request);
    }
}
