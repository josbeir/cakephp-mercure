<?php
declare(strict_types=1);

namespace Mercure\ServiceProvider;

use Cake\Container\ContainerInterface;
use Cake\Container\ServiceProvider\AbstractServiceProvider;
use Mercure\MercurePlugin;
use Mercure\Service\AuthorizationInterface;
use Mercure\Service\PublisherInterface;

/**
 * Mercure Service Provider
 *
 * Registers Mercure services in the application container.
 *
 * @deprecated Direct service registration in {@see \Mercure\MercurePlugin::services()}
 *     registers services in CakePHP's native container. This class remains only
 *     for backwards compatibility and will be removed in the next major release.
 */
class MercureServiceProvider extends AbstractServiceProvider
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
     * @param \Cake\Container\ContainerInterface $container The container to register services in
     */
    public function services(ContainerInterface $container): void
    {
        (new MercurePlugin())->services($container);
    }

    /**
     * @inheritDoc
     */
    public function provides(string $id): bool
    {
        return in_array($id, $this->provides, true);
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->services($this->getContainer());
    }
}
