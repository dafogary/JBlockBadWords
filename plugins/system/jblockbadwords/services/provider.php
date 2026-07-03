<?php

declare(strict_types=1);

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\System\JBlockBadWords\Extension\Jblockbadwords;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new Jblockbadwords(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('system', 'jblockbadwords')
                );
                $plugin->setApplication($container->get('application'));

                return $plugin;
            }
        );
    }
};
