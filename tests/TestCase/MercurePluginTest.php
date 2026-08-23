<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\Container\Container as CakeContainer;
use Cake\Core\CakeContainerBridge;
use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\Core\ContainerInterface;
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
 * The plugin has to register the same two services whichever container the
 * application selected. CakePHP 5.4 added an opt-in container
 * (`App.container = 'cake'`) whose bridge accepts only
 * `Cake\Container\ServiceProvider\ServiceProviderInterface`, so registering
 * through `Cake\Core\ServiceProvider` - a League `AbstractServiceProvider` -
 * threw on boot for those applications.
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

    public function testServicesAreRegisteredInTheLeagueContainer(): void
    {
        $container = new Container();

        (new MercurePlugin())->services($container);

        $this->assertServicesResolve($container);
    }

    public function testMercureServiceProviderIsDeprecated(): void
    {
        $this->deprecated(function (): void {
            new MercureServiceProvider();
        });
    }

    public function testServicesAreRegisteredInTheCakePhpContainer(): void
    {
        if (!class_exists(CakeContainerBridge::class)) {
            $this->markTestSkipped('The CakePHP container arrived in CakePHP 5.4.');
        }

        $container = new CakeContainerBridge(new CakeContainer());

        // This is the call that used to throw: a League service provider is not
        // a `Cake\Container` service provider, and the bridge says so.
        (new MercurePlugin())->services($container);

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
