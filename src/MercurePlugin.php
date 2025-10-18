<?php
declare(strict_types=1);

namespace Mercure;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Mercure\ServiceProvider\MercureServiceProvider;

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
    }

    /**
     * Register application container services.
     *
     * @param \Cake\Core\ContainerInterface $container The Container to update.
     */
    public function services(ContainerInterface $container): void
    {
        $container->addServiceProvider(new MercureServiceProvider());
    }
}
