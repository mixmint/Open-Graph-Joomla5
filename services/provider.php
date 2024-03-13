<?php

/**
 * @package         Joomla.Plugin
 * @subpackage      System.opengraph
 *
 * @copyright       (C) 2024 ProASK Networks, Mirosław Majka. <https://proask.pl>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

// use Joomla\CMS\Extension\PluginInterface;
// use Joomla\CMS\Plugin\PluginHelper;
// use Joomla\Database\DatabaseInterface;
// use Joomla\DI\Container;
// use Joomla\DI\ServiceProviderInterface;
// use Joomla\Event\DispatcherInterface;


use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

use Joomla\Plugin\System\OpenGraph\Extension\OpenGraph;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   __DEPLOY_VERSION__
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $plugin = new OpenGraph(
                    $container->get(DispatcherInterface::class),
                    (array) PluginHelper::getPlugin('system', 'opengraph')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            }
        );
    }
};
