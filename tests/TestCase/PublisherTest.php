<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mercure\Exception\MercureException;
use Mercure\Publisher;
use Mercure\Service\PublisherService;
use Mercure\TestSuite\MockPublisher;
use Mercure\Update;

/**
 * Publisher Test
 */
class PublisherTest extends TestCase
{
    /**
     * setUp method
     */
    protected function setUp(): void
    {
        parent::setUp();
        Publisher::clear();
    }

    /**
     * tearDown method
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Publisher::clear();
        Configure::delete('Mercure');
    }

    /**
     * Test getInstance creates instance from configuration
     */
    public function testGetInstanceCreatesInstanceFromConfig(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
            ],
        ]);

        $instance = Publisher::getInstance();

        $this->assertInstanceOf(PublisherService::class, $instance);
        $this->assertSame('http://localhost:3000/.well-known/mercure', $instance->getHubUrl());
    }

    /**
     * Test getInstance returns same instance (singleton)
     */
    public function testGetInstanceReturnsSameInstance(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
            ],
        ]);

        $instance1 = Publisher::getInstance();
        $instance2 = Publisher::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test setInstance allows custom instance
     */
    public function testSetInstanceAllowsCustomInstance(): void
    {
        $mock = new MockPublisher();
        Publisher::setInstance($mock);

        $instance = Publisher::getInstance();

        $this->assertSame($mock, $instance);
    }

    /**
     * Test clear resets instance
     */
    public function testClearResetsInstance(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
            ],
        ]);

        $instance1 = Publisher::getInstance();
        Publisher::clear();
        $instance2 = Publisher::getInstance();

        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * Test publish delegates to service
     */
    public function testPublishDelegatesToService(): void
    {
        $mock = new MockPublisher();
        Publisher::setInstance($mock);

        $update = new Update(topics: '/test', data: 'test data');
        $result = Publisher::publish($update);

        $this->assertTrue($result);
        $this->assertCount(1, $mock->getUpdates());
        $this->assertSame($update, $mock->getUpdates()[0]);
    }

    /**
     * Test getHubUrl returns configured URL
     */
    public function testGetHubUrlReturnsConfiguredUrl(): void
    {
        Configure::write('Mercure.url', 'http://example.com/.well-known/mercure');

        $hubUrl = Publisher::getHubUrl();

        $this->assertSame('http://example.com/.well-known/mercure', $hubUrl);
    }

    /**
     * Test static publish with multiple updates
     */
    public function testStaticPublishWithMultipleUpdates(): void
    {
        $mock = new MockPublisher();
        Publisher::setInstance($mock);

        Publisher::publish(new Update(topics: '/test1', data: 'data1'));
        Publisher::publish(new Update(topics: '/test2', data: 'data2'));
        Publisher::publish(new Update(topics: '/test3', data: 'data3'));

        $this->assertCount(3, $mock->getUpdates());
    }

    /**
     * Test using Publisher without explicit setInstance
     */
    public function testUsingPublisherWithoutExplicitSetInstance(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
            ],
        ]);

        // First call creates the instance
        $hubUrl = Publisher::getHubUrl();
        $this->assertSame('http://localhost:3000/.well-known/mercure', $hubUrl);

        // Subsequent calls use the same instance
        $hubUrl2 = Publisher::getHubUrl();
        $this->assertSame($hubUrl, $hubUrl2);
    }

    /**
     * Test setInstance overwrites existing instance
     */
    public function testSetInstanceOverwritesExisting(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
            ],
        ]);

        $instance1 = Publisher::getInstance();

        $mock = new MockPublisher();
        Publisher::setInstance($mock);

        $instance2 = Publisher::getInstance();

        $this->assertNotSame($instance1, $instance2);
        $this->assertSame($mock, $instance2);
    }

    /**
     * Test that ServiceProvider returns the singleton instance
     */
    public function testServiceProviderReturnsSingletonInstance(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
            ],
        ]);

        // Get instance via static accessor
        $staticInstance = Publisher::getInstance();

        // Simulate getting from DI container (what ServiceProvider does)
        $diInstance = Publisher::getInstance();

        // Should be the same instance
        $this->assertSame($staticInstance, $diInstance);
    }

    /**
     * Test getInstance with static JWT token value
     */
    public function testGetInstanceWithStaticJwtValue(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'value' => 'static.jwt.token',
            ],
        ]);

        $instance = Publisher::getInstance();

        $this->assertInstanceOf(PublisherService::class, $instance);
    }

    /**
     * Test getInstance with JWT secret and custom algorithm
     */
    public function testGetInstanceWithJwtSecretAndAlgorithm(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
                'algorithm' => 'HS384',
                'publish' => ['https://example.com/feeds/{id}'],
                'subscribe' => ['https://example.com/books/{id}'],
            ],
        ]);

        $instance = Publisher::getInstance();

        $this->assertInstanceOf(PublisherService::class, $instance);
    }

    /**
     * Test getInstance throws exception when hub URL is missing
     */
    public function testGetInstanceThrowsExceptionWhenHubUrlMissing(): void
    {
        Configure::write('Mercure', [
            'jwt' => [
                'secret' => 'test-secret-key',
            ],
        ]);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        Publisher::getInstance();
    }

    /**
     * Test getInstance throws exception when JWT is not configured
     */
    public function testGetInstanceThrowsExceptionWhenJwtMissing(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
        ]);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('JWT secret or token must be configured');

        Publisher::getInstance();
    }

    /**
     * Test backward compatibility with publisher_jwt config
     */
    public function testBackwardCompatibilityWithPublisherJwt(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'publisher_jwt' => 'backward-compat-secret',
        ]);

        $instance = Publisher::getInstance();

        $this->assertInstanceOf(PublisherService::class, $instance);
    }

    /**
     * Test that static access and DI share the same instance
     */
    public function testStaticAccessAndDiShareInstance(): void
    {
        $mock = new MockPublisher();
        Publisher::setInstance($mock);

        // Publish via static accessor
        Publisher::publish(new Update(topics: '/test1', data: 'data1'));

        // Get via "DI" (simulated)
        $diInstance = Publisher::getInstance();
        $diInstance->publish(new Update(topics: '/test2', data: 'data2'));

        // Both should have been recorded in the same mock instance
        $this->assertCount(2, $mock->getUpdates());
        $this->assertSame('data1', $mock->getUpdates()[0]->getData());
        $this->assertSame('data2', $mock->getUpdates()[1]->getData());
    }
}
