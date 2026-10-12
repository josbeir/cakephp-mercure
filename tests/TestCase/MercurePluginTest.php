<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\Container\Container;
use Cake\Container\ContainerInterface;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mercure\Authorization;
use Mercure\MercurePlugin;
use Mercure\Publisher;
use Mercure\Service\AuthorizationInterface;
use Mercure\Service\PublisherInterface;
use Mercure\ServiceProvider\MercureServiceProvider;

/**
 * Plugin service registration.
 *
 * Services resolve through CakePHP 6's native container, either through the
 * plugin or through the retained service provider.
 */
class MercurePluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Publisher::clear();
        Authorization::clear();
        Configure::write('Mercure', [
            'url' => 'https://mercure.example.com/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key-with-32-bytes-minimum!!',
                'algorithm' => 'HS256',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Publisher::clear();
        Authorization::clear();
    }

    public function testServicesAreRegisteredInTheCakePhpContainer(): void
    {
        $container = new Container();

        (new MercurePlugin())->services($container);

        $this->assertServicesResolve($container);
    }

    public function testServiceProviderRegistersServices(): void
    {
        $container = new Container();
        $provider = new MercureServiceProvider();
        $container->addServiceProvider($provider);

        $this->assertTrue($provider->provides(PublisherInterface::class));
        $this->assertTrue($provider->provides(AuthorizationInterface::class));
        $this->assertFalse($provider->provides('unknown-service'));

        $this->assertServicesResolve($container);
    }

    public function testServiceProviderServicesMethodRegistersServices(): void
    {
        $container = new Container();
        (new MercureServiceProvider())->services($container);

        $this->assertServicesResolve($container);
    }

    protected function assertServicesResolve(ContainerInterface $container): void
    {
        $this->assertTrue($container->has(PublisherInterface::class));
        $this->assertTrue($container->has(AuthorizationInterface::class));
        $this->assertInstanceOf(PublisherInterface::class, $container->get(PublisherInterface::class));
        $this->assertInstanceOf(AuthorizationInterface::class, $container->get(AuthorizationInterface::class));
    }
}
