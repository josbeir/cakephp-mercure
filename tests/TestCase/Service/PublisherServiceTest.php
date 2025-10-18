<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Service;

use Cake\TestSuite\TestCase;
use Mercure\Exception\MercureException;
use Mercure\Jwt\StaticTokenProvider;
use Mercure\Service\PublisherService;
use Mercure\TestSuite\MockPublisher;
use Mercure\Update;

/**
 * PublisherService Test
 */
class PublisherServiceTest extends TestCase
{
    /**
     * Test constructor succeeds with valid configuration
     */
    public function testConstructorSucceedsWithValidConfig(): void
    {
        $tokenProvider = new StaticTokenProvider('test-token');
        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
        );

        $this->assertInstanceOf(PublisherService::class, $service);
        $this->assertSame('http://localhost:3000/.well-known/mercure', $service->getHubUrl());
    }

    /**
     * Test constructor with HTTP client config
     */
    public function testConstructorWithHttpClientConfig(): void
    {
        $tokenProvider = new StaticTokenProvider('test-token');
        $httpConfig = [
            'ssl_verify_peer' => false,
            'timeout' => 30,
        ];

        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
            $httpConfig,
        );

        $this->assertInstanceOf(PublisherService::class, $service);
    }

    /**
     * Test JWT validation rejects empty token
     */
    public function testPublishThrowsExceptionWithEmptyJwt(): void
    {
        $tokenProvider = new StaticTokenProvider('');
        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
        );

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('JWT token cannot be empty');

        $update = new Update(topics: '/test', data: 'test');
        $service->publish($update);
    }

    /**
     * Test JWT validation rejects malformed token (missing signature)
     */
    public function testPublishThrowsExceptionWithMalformedJwtMissingSignature(): void
    {
        $tokenProvider = new StaticTokenProvider('header.payload');
        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
        );

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('The provided JWT is not valid');

        $update = new Update(topics: '/test', data: 'test');
        $service->publish($update);
    }

    /**
     * Test JWT validation rejects malformed token (too many parts)
     */
    public function testPublishThrowsExceptionWithMalformedJwtTooManyParts(): void
    {
        $tokenProvider = new StaticTokenProvider('header.payload.signature.extra');
        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
        );

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('The provided JWT is not valid');

        $update = new Update(topics: '/test', data: 'test');
        $service->publish($update);
    }

    /**
     * Test JWT validation rejects token with invalid characters
     */
    public function testPublishThrowsExceptionWithInvalidCharacters(): void
    {
        $tokenProvider = new StaticTokenProvider('header@invalid.payload.signature');
        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
        );

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('The provided JWT is not valid');

        $update = new Update(topics: '/test', data: 'test');
        $service->publish($update);
    }

    /**
     * Test JWT validation rejects token with spaces
     */
    public function testPublishThrowsExceptionWithSpaces(): void
    {
        $tokenProvider = new StaticTokenProvider('header .payload.signature');
        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
        );

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('The provided JWT is not valid');

        $update = new Update(topics: '/test', data: 'test');
        $service->publish($update);
    }

    /**
     * Test JWT validation accepts valid token structure
     */
    public function testPublishAcceptsValidJwtStructure(): void
    {
        // Create a valid-looking JWT (even though it won't work with a real hub)
        $validJwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXJlIjp7InB1Ymxpc2giOlsiKiJdfX0.test';
        $tokenProvider = new StaticTokenProvider($validJwt);
        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
        );

        // This will fail at HTTP request stage, but JWT validation should pass
        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Error publishing to Mercure hub');

        $update = new Update(topics: '/test', data: 'test');
        $service->publish($update);
    }

    /**
     * Test JWT validation accepts token without signature (valid format)
     */
    public function testPublishAcceptsJwtWithoutSignature(): void
    {
        // JWT can have empty signature part (header.payload.)
        $validJwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJtZXJjdXJlIjp7InB1Ymxpc2giOlsiKiJdfX0.';
        $tokenProvider = new StaticTokenProvider($validJwt);
        $service = new PublisherService(
            'http://localhost:3000/.well-known/mercure',
            $tokenProvider,
        );

        // This will fail at HTTP request stage, but JWT validation should pass
        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Error publishing to Mercure hub');

        $update = new Update(topics: '/test', data: 'test');
        $service->publish($update);
    }

    /**
     * Test using MockPublisher for testing
     */
    public function testWithMockPublisher(): void
    {
        $mock = new MockPublisher();

        $update = new Update(
            topics: '/test/topic',
            data: '{"message": "Hello"}',
        );

        $result = $mock->publish($update);

        $this->assertTrue($result);
        $this->assertCount(1, $mock->getUpdates());
        $this->assertSame($update, $mock->getUpdates()[0]);
    }

    /**
     * Test MockPublisher with multiple updates
     */
    public function testMockPublisherWithMultipleUpdates(): void
    {
        $mock = new MockPublisher();

        $update1 = new Update(topics: '/topic1', data: 'data1');
        $update2 = new Update(topics: '/topic2', data: 'data2');
        $update3 = new Update(topics: '/topic3', data: 'data3');

        $mock->publish($update1);
        $mock->publish($update2);
        $mock->publish($update3);

        $updates = $mock->getUpdates();
        $this->assertCount(3, $updates);
        $this->assertSame($update1, $updates[0]);
        $this->assertSame($update2, $updates[1]);
        $this->assertSame($update3, $updates[2]);
    }

    /**
     * Test MockPublisher reset
     */
    public function testMockPublisherReset(): void
    {
        $mock = new MockPublisher();

        $mock->publish(new Update(topics: '/test', data: 'test'));
        $this->assertCount(1, $mock->getUpdates());

        $mock->reset();
        $this->assertCount(0, $mock->getUpdates());
    }

    /**
     * Test MockPublisher returns hub URL
     */
    public function testMockPublisherReturnsHubUrl(): void
    {
        $mock = new MockPublisher('http://example.com/.well-known/mercure');
        $this->assertSame('http://example.com/.well-known/mercure', $mock->getHubUrl());
    }

    /**
     * Test MockPublisher with private update
     */
    public function testMockPublisherWithPrivateUpdate(): void
    {
        $mock = new MockPublisher();

        $update = new Update(
            topics: '/private/topic',
            data: 'sensitive data',
            private: true,
        );

        $mock->publish($update);

        $this->assertCount(1, $mock->getUpdates());
        $this->assertTrue($mock->getUpdates()[0]->isPrivate());
    }

    /**
     * Test MockPublisher with multiple topics
     */
    public function testMockPublisherWithMultipleTopics(): void
    {
        $mock = new MockPublisher();

        $topics = ['/topic1', '/topic2', '/topic3'];
        $update = new Update(topics: $topics, data: 'test data');

        $mock->publish($update);

        $this->assertCount(1, $mock->getUpdates());
        $this->assertSame($topics, $mock->getUpdates()[0]->getTopics());
    }

    /**
     * Test MockPublisher with all update options
     */
    public function testMockPublisherWithAllOptions(): void
    {
        $mock = new MockPublisher();

        $update = new Update(
            topics: '/test/topic',
            data: 'test data',
            private: true,
            id: 'event-123',
            type: 'custom-type',
            retry: 5000,
        );

        $mock->publish($update);

        $updates = $mock->getUpdates();
        $this->assertCount(1, $updates);

        $published = $updates[0];
        $this->assertTrue($published->isPrivate());
        $this->assertSame('event-123', $published->getId());
        $this->assertSame('custom-type', $published->getType());
        $this->assertSame(5000, $published->getRetry());
    }
}
