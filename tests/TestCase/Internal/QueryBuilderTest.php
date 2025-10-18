<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Internal;

use Cake\TestSuite\TestCase;
use Mercure\Internal\QueryBuilder;

/**
 * QueryBuilder Test
 */
class QueryBuilderTest extends TestCase
{
    /**
     * Test build with simple key-value pairs
     */
    public function testBuildWithSimpleValues(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('key1=value1&key2=value2', $result);
    }

    /**
     * Test build with array values
     */
    public function testBuildWithArrayValues(): void
    {
        $data = [
            'topic' => ['/topic1', '/topic2', '/topic3'],
            'data' => 'test data',
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('topic=%2Ftopic1&topic=%2Ftopic2&topic=%2Ftopic3&data=test+data', $result);
    }

    /**
     * Test build skips null values
     */
    public function testBuildSkipsNullValues(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => null,
            'key3' => 'value3',
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('key1=value1&key3=value3', $result);
    }

    /**
     * Test build with special characters
     */
    public function testBuildWithSpecialCharacters(): void
    {
        $data = [
            'data' => 'hello world & special chars!',
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('data=hello+world+%26+special+chars%21', $result);
    }

    /**
     * Test build with empty array
     */
    public function testBuildWithEmptyArray(): void
    {
        $result = QueryBuilder::build([]);

        $this->assertSame('', $result);
    }

    /**
     * Test build with numeric values
     */
    public function testBuildWithNumericValues(): void
    {
        $data = [
            'retry' => 5000,
            'count' => 42,
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('retry=5000&count=42', $result);
    }

    /**
     * Test build with boolean values
     */
    public function testBuildWithBooleanValues(): void
    {
        $data = [
            'private' => 'on',
            'public' => 'off',
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('private=on&public=off', $result);
    }

    /**
     * Test build with mixed array and scalar values
     */
    public function testBuildWithMixedValues(): void
    {
        $data = [
            'topic' => ['/feed/1', '/feed/2'],
            'data' => '{"status":"completed"}',
            'private' => 'on',
            'id' => 'event-123',
            'retry' => 5000,
        ];

        $result = QueryBuilder::build($data);

        $this->assertStringContainsString('topic=%2Ffeed%2F1', $result);
        $this->assertStringContainsString('topic=%2Ffeed%2F2', $result);
        $this->assertStringContainsString('data=%7B%22status%22%3A%22completed%22%7D', $result);
        $this->assertStringContainsString('private=on', $result);
        $this->assertStringContainsString('id=event-123', $result);
        $this->assertStringContainsString('retry=5000', $result);
    }

    /**
     * Test build encodes URLs properly
     */
    public function testBuildEncodesUrlsProperly(): void
    {
        $data = [
            'topic' => ['/company/123/feeds', '/global/notifications'],
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('topic=%2Fcompany%2F123%2Ffeeds&topic=%2Fglobal%2Fnotifications', $result);
    }

    /**
     * Test build with JSON data
     */
    public function testBuildWithJsonData(): void
    {
        $data = [
            'data' => json_encode(['feed_id' => 123, 'status' => 'completed', 'progress' => 100]),
        ];

        $result = QueryBuilder::build($data);

        // Verify it's properly encoded and can be decoded back
        parse_str($result, $parsed);
        $this->assertArrayHasKey('data', $parsed);
        $this->assertIsString($parsed['data']);

        $decoded = json_decode($parsed['data'], true);
        $this->assertSame(123, $decoded['feed_id']);
        $this->assertSame('completed', $decoded['status']);
        $this->assertSame(100, $decoded['progress']);
    }

    /**
     * Test build preserves order
     */
    public function testBuildPreservesOrder(): void
    {
        $data = [
            'first' => 'value1',
            'second' => 'value2',
            'third' => 'value3',
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('first=value1&second=value2&third=value3', $result);
    }

    /**
     * Test build with empty string value
     */
    public function testBuildWithEmptyStringValue(): void
    {
        $data = [
            'key1' => '',
            'key2' => 'value',
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('key1=&key2=value', $result);
    }

    /**
     * Test build with only null values
     */
    public function testBuildWithOnlyNullValues(): void
    {
        $data = [
            'key1' => null,
            'key2' => null,
            'key3' => null,
        ];

        $result = QueryBuilder::build($data);

        $this->assertSame('', $result);
    }
}
