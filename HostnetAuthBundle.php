<?php

namespace MauticPlugin\HostnetAuthBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Mautic\PluginBundle\Entity\Plugin;

class HostnetAuthBundle extends PluginBundleBase
{
    public static function onPluginInstall(
        Plugin $plugin,
        MauticFactory $factory,
        $metadata = null,
        $installedSchema = null
    ) {
    }

    public static function onPluginUpdate(
        Plugin $plugin,
        MauticFactory $factory,
        $metadata = null,
        Schema $installedSchema = null
    ) {
    }
}
