<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Mercure\Update;

/**
 * Update Test
 */
class UpdateTest extends TestCase
{
    /**
     * Test constructor with string topic
     */
    public function testConstructorWithStringTopic(): void
    {
        $update = new Update(
            topics: '/test/topic',
            data: '{"message": "Hello"}',
        );

        $this->assertSame(['/test/topic'], $update->getTopics());
        $this->assertSame('{"message": "Hello"}', $update->getData());
        $this->assertFalse($update->isPrivate());
        $this->assertNull($update->getId());
        $this->assertNull($update->getType());
        $this->assertNull($update->getRetry());
    }

    /**
     * Test constructor with array of topics
     */
    public function testConstructorWithArrayTopics(): void
    {
        $topics = ['/topic1', '/topic2', '/topic3'];
        $update = new Update(
            topics: $topics,
            data: 'test data',
        );

        $this->assertSame($topics, $update->getTopics());
    }

    /**
     * Test constructor with all parameters
     */
    public function testConstructorWithAllParameters(): void
    {
        $update = new Update(
            topics: '/test/topic',
            data: 'test data',
            private: true,
            id: 'event-123',
            type: 'custom-type',
            retry: 5000,
        );

        $this->assertTrue($update->isPrivate());
        $this->assertSame('event-123', $update->getId());
        $this->assertSame('custom-type', $update->getType());
        $this->assertSame(5000, $update->getRetry());
    }

    /**
     * Test validation fails with empty topics array
     */
    public function testValidationFailsWithEmptyTopics(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one topic must be provided');

        new Update(
            topics: [],
            data: 'test data',
        );
    }

    /**
     * Test validation fails with empty topic string
     */
    public function testValidationFailsWithEmptyTopicString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('All topics must be non-empty strings');

        new Update(
            topics: [''],
            data: 'test data',
        );
    }

    /**
     * Test validation fails with negative retry value
     */
    public function testValidationFailsWithNegativeRetry(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry value must be a positive integer');

        new Update(
            topics: '/test/topic',
            data: 'test data',
            retry: -100,
        );
    }

    /**
     * Test toArray method with minimal data
     */
    public function testToArrayWithMinimalData(): void
    {
        $update = new Update(
            topics: '/test/topic',
            data: 'test data',
        );

        $array = $update->toArray();

        $this->assertArrayHasKey('topic', $array);
        $this->assertArrayHasKey('data', $array);
        $this->assertSame(['/test/topic'], $array['topic']);
        $this->assertSame('test data', $array['data']);
        $this->assertArrayNotHasKey('private', $array);
        $this->assertArrayNotHasKey('id', $array);
        $this->assertArrayNotHasKey('type', $array);
        $this->assertArrayNotHasKey('retry', $array);
    }

    /**
     * Test toArray method with all data
     */
    public function testToArrayWithAllData(): void
    {
        $update = new Update(
            topics: ['/topic1', '/topic2'],
            data: 'test data',
            private: true,
            id: 'event-123',
            type: 'custom-type',
            retry: 5000,
        );

        $array = $update->toArray();

        $this->assertSame(['/topic1', '/topic2'], $array['topic']);
        $this->assertSame('test data', $array['data']);
        $this->assertSame('on', $array['private']);
        $this->assertSame('event-123', $array['id']);
        $this->assertSame('custom-type', $array['type']);
        $this->assertSame('5000', $array['retry']);
    }

    /**
     * Test toArray converts retry to string
     */
    public function testToArrayConvertsRetryToString(): void
    {
        $update = new Update(
            topics: '/test/topic',
            data: 'test data',
            retry: 1000,
        );

        $array = $update->toArray();

        $this->assertIsString($array['retry']);
        $this->assertSame('1000', $array['retry']);
    }

    /**
     * Test multiple topics in array
     */
    public function testMultipleTopics(): void
    {
        $topics = [
            '/company/123/feeds',
            '/company/123/projects',
            '/global/notifications',
        ];

        $update = new Update(
            topics: $topics,
            data: '{"status": "updated"}',
        );

        $this->assertCount(3, $update->getTopics());
        $this->assertSame($topics, $update->getTopics());
    }
}
