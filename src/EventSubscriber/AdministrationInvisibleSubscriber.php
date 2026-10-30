<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sans session administrateur, /admin répond exactement comme une adresse inexistante.
 *
 * La 404 est levée avant le pare-feu (priorité 8) : sinon celui-ci ouvre une session
 * pour retenir la page demandée, et le cookie ainsi créé distinguerait /admin d'une
 * vraie page introuvable.
 */
class AdministrationInvisibleSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 16]];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !preg_match('#^/admin(/|$)#', $request->getPathInfo())) {
            return;
        }

        // Session existante contenant une authentification : le pare-feu décide ensuite (rôle, expiration).
        if ($request->hasPreviousSession() && $request->getSession()->has('_security_main')) {
            return;
        }

        throw new NotFoundHttpException();
    }
}
