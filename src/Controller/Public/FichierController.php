<?php

namespace App\Controller\Public;

use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Appelé seulement quand le serveur web ne trouve pas un fichier public sur le
 * disque (redémarrage d'un hébergement au disque éphémère) : le fichier est
 * restauré depuis la base, puis servi directement par le serveur web ensuite.
 */
class FichierController extends AbstractController
{
    #[Route('/uploads/{dossier}/{filename}', name: 'fichier_public', methods: ['GET'], requirements: ['dossier' => 'images|documents', 'filename' => '[A-Za-z0-9][A-Za-z0-9._-]*\.(webp|jpg|jpeg|png|pdf)'])]
    public function servir(string $dossier, string $filename, FileUploader $uploader): Response
    {
        $chemin = $uploader->restaurerPublic($dossier, $filename) ?? throw $this->createNotFoundException();

        $response = new BinaryFileResponse($chemin);
        $response->setPublic();
        $response->setMaxAge(2592000);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
