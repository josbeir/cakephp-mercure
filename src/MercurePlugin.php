<?php
declare(strict_types=1);

namespace Mercure;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
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
     * @phpstan-ignore-next-line missingType.generics
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
     * Registered directly rather than through {@see \Mercure\ServiceProvider\MercureServiceProvider},
     * because `add()` is on both container implementations while a service
     * PROVIDER is not portable between them. CakePHP 5.4 added an opt-in
     * container (`App.container = 'cake'`) whose bridge accepts only
     * `Cake\Container\ServiceProvider\ServiceProviderInterface`, and
     * `Cake\Core\ServiceProvider` is a League `AbstractServiceProvider` - so
     * handing it one threw on boot, before any request ran.
     *
     * The provider class stays for anyone registering it themselves.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
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
