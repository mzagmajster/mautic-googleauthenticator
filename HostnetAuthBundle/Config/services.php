<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [
        'Helper',
        'Config'
    ];

    $services->load(
        'MauticPlugin\\HostnetAuthBundle\\',
        __DIR__.'/../'
    )
        ->exclude('../{'.implode(',', array_merge([], $excludes)).'}');

    /*$services->set('plugin.hostnetauth.repository.authbrowser')
        ->class(\MauticPlugin\HostnetAuthBundle\Entity\AuthBrowserRepository::class)
        ->tag('doctrine.repository_service', ['alias' => 'hostnetauth']);*/

};