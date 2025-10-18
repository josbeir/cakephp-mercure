<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\TestSuite;

use Cake\TestSuite\TestCase;
use Mercure\TestSuite\MockPublisher;
use Mercure\Update;

/**
 * MockPublisher Test
 */
class MockPublisherTest extends TestCase
{
    /**
     * Test constructor with default hub URL
     */
    public function testConstructorWithDefaultHubUrl(): void
    {
        $mock = new MockPublisher();
        $this->assertSame('http://localhost:3000/.well-known/mercure', $mock->getHubUrl());
    }

    /**
     * Test constructor with custom hub URL
     */
    public function testConstructorWithCustomHubUrl(): void
    {
        $mock = new MockPublisher('http://example.com/.well-known/mercure');
        $this->assertSame('http://example.com/.well-known/mercure', $mock->getHubUrl());
    }

    /**
     * Test publish stores update
     */
    public function testPublishStoresUpdate(): void
    {
        $mock = new MockPublisher();
        $update = new Update(topics: '/test', data: 'test data');

        $result = $mock->publish($update);

        $this->assertTrue($result);
        $this->assertCount(1, $mock->getUpdates());
        $this->assertSame($update, $mock->getUpdates()[0]);
    }

    /**
     * Test multiple publishes
     */
    public function testMultiplePublishes(): void
    {
        $mock = new MockPublisher();

        $update1 = new Update(topics: '/test1', data: 'data1');
        $update2 = new Update(topics: '/test2', data: 'data2');
        $update3 = new Update(topics: '/test3', data: 'data3');

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
     * Test getUpdates returns empty array initially
     */
    public function testGetUpdatesReturnsEmptyArrayInitially(): void
    {
        $mock = new MockPublisher();
        $this->assertSame([], $mock->getUpdates());
    }

    /**
     * Test reset clears all updates
     */
    public function testResetClearsAllUpdates(): void
    {
        $mock = new MockPublisher();

        $mock->publish(new Update(topics: '/test1', data: 'data1'));
        $mock->publish(new Update(topics: '/test2', data: 'data2'));

        $this->assertCount(2, $mock->getUpdates());

        $mock->reset();

        $this->assertCount(0, $mock->getUpdates());
        $this->assertSame([], $mock->getUpdates());
    }

    /**
     * Test publish always returns true
     */
    public function testPublishAlwaysReturnsTrue(): void
    {
        $mock = new MockPublisher();

        $result1 = $mock->publish(new Update(topics: '/test1', data: 'data1'));
        $result2 = $mock->publish(new Update(topics: '/test2', data: 'data2'));
        $result3 = $mock->publish(new Update(topics: '/test3', data: 'data3'));

        $this->assertTrue($result1);
        $this->assertTrue($result2);
        $this->assertTrue($result3);
    }

    /**
     * Test stores update with all properties
     */
    public function testStoresUpdateWithAllProperties(): void
    {
        $mock = new MockPublisher();

        $jsonData = json_encode(['key' => 'value']);
        $this->assertIsString($jsonData);

        $update = new Update(
            topics: ['/topic1', '/topic2'],
            data: $jsonData,
            private: true,
            id: 'event-123',
            type: 'custom.type',
            retry: 5000,
        );

        $mock->publish($update);

        $stored = $mock->getUpdates()[0];
        $this->assertSame(['/topic1', '/topic2'], $stored->getTopics());
        $this->assertSame(json_encode(['key' => 'value']), $stored->getData());
        $this->assertTrue($stored->isPrivate());
        $this->assertSame('event-123', $stored->getId());
        $this->assertSame('custom.type', $stored->getType());
        $this->assertSame(5000, $stored->getRetry());
    }
}
