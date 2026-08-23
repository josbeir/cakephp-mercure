<?php
declare(strict_types=1);

namespace Mercure\ServiceProvider;

use Cake\Core\ContainerInterface;
use Cake\Core\ServiceProvider;
use Mercure\Authorization;
use Mercure\Publisher;
use Mercure\Service\AuthorizationInterface;
use Mercure\Service\PublisherInterface;

/**
 * Mercure Service Provider
 *
 * Registers Mercure services in the application container.
 *
 * @deprecated Direct service registration in {@see \Mercure\MercurePlugin::services()}
 *     supports both CakePHP container implementations. This class remains only
 *     for backwards compatibility and will be removed in the next major release.
 */
class MercureServiceProvider extends ServiceProvider
{
    /**
     * @var array<string>
     */
    protected array $provides = [
        PublisherInterface::class,
        AuthorizationInterface::class,
    ];

    /**
     * Register services in the container
     *
     * @param \Cake\Core\ContainerInterface $container The container to register services in
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
