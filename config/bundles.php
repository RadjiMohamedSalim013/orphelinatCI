<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    // Désactivé pour éviter des erreurs en prod lorsque symfony/debug-bundle n'est pas installé
    // Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true],

    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    // WebProfiler n'est utile que pour le dev/test
    // WebProfiler dépend de symfony/web-profiler-bundle (dev/test)
    // On le désactive ici pour éviter l'erreur après --no-dev.
    // Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],


    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\Turbo\TurboBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    Symfony\Bundle\MakerBundle\MakerBundle::class => ['dev' => true],
];
