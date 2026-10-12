<?php
declare(strict_types=1);

namespace Mercure;

use Cake\Container\ContainerInterface;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Mercure\Service\AuthorizationInterface;
use Mercure\Service\PublisherInterface;

/**
 * Plugin for Mercure
 */
class MercurePlugin extends BasePlugin
{
    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        // Load plugin configuration
        Configure::load('Mercure.mercure');

        // Load app specific config file.
        if (file_exists(ROOT . DS . 'config' . DS . 'app_mercure.php')) {
            Configure::load('app_mercure');
        }
    }

    /**
     * Register application container services.
     *
     * Register factories directly in CakePHP's native container.
     * The retained {@see \Mercure\ServiceProvider\MercureServiceProvider}
     * delegates to this method for applications registering it themselves.
     *
     * @param \Cake\Container\ContainerInterface $container The Container to update.
     */
    public function services(ContainerInterface $container): void
    {
        $container->add(PublisherInterface::class, function (): PublisherInterface {
            return Publisher::create();
        });

        $container->add(AuthorizationInterface::class, function (): AuthorizationInterface {
            return Authorization::create();
        });
    }
}
