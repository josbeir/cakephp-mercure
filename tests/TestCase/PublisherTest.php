<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Mercure\Exception\MercureException;
use Mercure\Publisher;
use Mercure\Service\PublisherService;
use Mercure\Test\Fixture\CustomTokenFactory;
use Mercure\Test\Fixture\CustomTokenProvider;
use Mercure\Test\Fixture\InvalidTokenFactory;
use Mercure\Test\Fixture\InvalidTokenProvider;
use Mercure\TestSuite\MockPublisher;
use Mercure\Update\Update;

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

        $instance = Publisher::create();

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

        $instance1 = Publisher::create();
        $instance2 = Publisher::create();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test setInstance allows custom instance
     */
    public function testSetInstanceAllowsCustomInstance(): void
    {
        $mock = new MockPublisher();
        Publisher::setInstance($mock);

        $instance = Publisher::create();

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

        $instance1 = Publisher::create();
        Publisher::clear();
        $instance2 = Publisher::create();

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

        $instance1 = Publisher::create();

        $mock = new MockPublisher();
        Publisher::setInstance($mock);

        $instance2 = Publisher::create();

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
        $staticInstance = Publisher::create();

        // Simulate getting from DI container (what ServiceProvider does)
        $diInstance = Publisher::create();

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

        $instance = Publisher::create();

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

        $instance = Publisher::create();

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

        Publisher::create();
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

        Publisher::create();
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

        $instance = Publisher::create();

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
        $diInstance = Publisher::create();
        $diInstance->publish(new Update(topics: '/test2', data: 'data2'));

        // Both should have been recorded in the same mock instance
        $this->assertCount(2, $mock->getUpdates());
        $this->assertSame('data1', $mock->getUpdates()[0]->getData());
        $this->assertSame('data2', $mock->getUpdates()[1]->getData());
    }

    /**
     * Test getInstance with custom token provider class
     */
    public function testGetInstanceWithCustomTokenProvider(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'provider' => CustomTokenProvider::class,
            ],
        ]);

        $instance = Publisher::create();

        $this->assertInstanceOf(PublisherService::class, $instance);
    }

    /**
     * Test getInstance throws exception with non-existent provider class
     */
    public function testGetInstanceThrowsExceptionWithNonExistentProvider(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'provider' => 'NonExistent\TokenProvider',
            ],
        ]);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage("Token provider class 'NonExistent\\TokenProvider' not found");

        Publisher::create();
    }

    /**
     * Test getInstance throws exception when provider doesn't implement interface
     */
    public function testGetInstanceThrowsExceptionWithInvalidProvider(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'provider' => InvalidTokenProvider::class,
            ],
        ]);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Token provider must implement TokenProviderInterface');

        Publisher::create();
    }

    /**
     * Test getInstance with custom token factory class
     */
    public function testGetInstanceWithCustomTokenFactory(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
                'factory' => CustomTokenFactory::class,
                'algorithm' => 'HS256',
            ],
        ]);

        $instance = Publisher::create();

        $this->assertInstanceOf(PublisherService::class, $instance);
    }

    /**
     * Test getInstance throws exception with non-existent factory class
     */
    public function testGetInstanceThrowsExceptionWithNonExistentFactory(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
                'factory' => 'NonExistent\TokenFactory',
            ],
        ]);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage("Token factory class 'NonExistent\\TokenFactory' not found");

        Publisher::create();
    }

    /**
     * Test getInstance throws exception when factory doesn't implement interface
     */
    public function testGetInstanceThrowsExceptionWithInvalidFactory(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
                'factory' => InvalidTokenFactory::class,
            ],
        ]);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Token factory must implement TokenFactoryInterface');

        Publisher::create();
    }

    /**
     * Test getInstance with additional JWT claims
     */
    public function testGetInstanceWithAdditionalJwtClaims(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
                'algorithm' => 'HS256',
                'publish' => ['https://example.com/*'],
                'subscribe' => ['https://example.com/feeds/*'],
                'additional_claims' => [
                    'aud' => 'my-app',
                    'iss' => 'my-service',
                ],
            ],
        ]);

        $instance = Publisher::create();

        $this->assertInstanceOf(PublisherService::class, $instance);
    }

    /**
     * Test getInstance with http_client configuration
     */
    public function testGetInstanceWithHttpClientConfig(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
            ],
            'http_client' => [
                'timeout' => 30,
                'verify' => false,
            ],
        ]);

        $instance = Publisher::create();

        $this->assertInstanceOf(PublisherService::class, $instance);
    }

    /**
     * Test getInstance with empty JWT secret
     */
    public function testGetInstanceThrowsExceptionWithEmptyJwtSecret(): void
    {
        Configure::write('Mercure', [
            'url' => 'http://localhost:3000/.well-known/mercure',
            'jwt' => [
                'secret' => '',
            ],
        ]);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('JWT secret or token must be configured');

        Publisher::create();
    }

    /**
     * Test getPublicUrl returns public_url when configured
     */
    public function testGetPublicUrlReturnsPublicUrlWhenConfigured(): void
    {
        Configure::write('Mercure.url', 'http://internal.mercure:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://mercure.example.com/.well-known/mercure');

        $publicUrl = Publisher::getPublicUrl();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $publicUrl);
    }

    /**
     * Test getPublicUrl falls back to url when public_url not set
     */
    public function testGetPublicUrlFallsBackToUrlWhenPublicUrlNotSet(): void
    {
        Configure::write('Mercure.url', 'http://localhost:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', null);

        $publicUrl = Publisher::getPublicUrl();
        $this->assertEquals('http://localhost:3000/.well-known/mercure', $publicUrl);
    }

    /**
     * Test getPublicUrl throws exception when neither url nor public_url set
     */
    public function testGetPublicUrlThrowsExceptionWhenNotConfigured(): void
    {
        Configure::write('Mercure.url', '');
        Configure::write('Mercure.public_url', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        Publisher::getPublicUrl();
    }
}
