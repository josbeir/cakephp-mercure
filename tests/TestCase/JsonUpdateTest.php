<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Mercure\Update\JsonUpdate;
use Mercure\Update\Update;
use stdClass;

/**
 * JsonUpdate Test Case
 *
 * Tests the JsonUpdate class for automatic JSON encoding.
 */
class JsonUpdateTest extends TestCase
{
    /**
     * Test basic JSON update with array data
     */
    public function testBasicJsonUpdateWithArray(): void
    {
        $data = ['status' => 'available', 'quantity' => 10];

        $update = JsonUpdate::create(
            topics: '/books/1',
            data: $data,
        );

        $this->assertEquals(['/books/1'], $update->getTopics());
        $this->assertEquals('{"status":"available","quantity":10}', $update->getData());
        $this->assertFalse($update->isPrivate());
    }

    /**
     * Test JSON update with multiple topics
     */
    public function testJsonUpdateWithMultipleTopics(): void
    {
        $data = ['message' => 'Update'];

        $update = JsonUpdate::create(
            topics: ['/books/1', '/notifications'],
            data: $data,
        );

        $this->assertEquals(['/books/1', '/notifications'], $update->getTopics());
        $this->assertEquals('{"message":"Update"}', $update->getData());
    }

    /**
     * Test JSON update with private flag
     */
    public function testJsonUpdateWithPrivateFlag(): void
    {
        $data = ['secret' => 'data'];

        $update = JsonUpdate::create(
            topics: '/users/123/private',
            data: $data,
            private: true,
        );

        $this->assertTrue($update->isPrivate());
        $this->assertEquals('{"secret":"data"}', $update->getData());
    }

    /**
     * Test JSON update with event ID
     */
    public function testJsonUpdateWithEventId(): void
    {
        $data = ['test' => 'data'];

        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
            id: 'event-123',
        );

        $this->assertEquals('event-123', $update->getId());
    }

    /**
     * Test JSON update with event type
     */
    public function testJsonUpdateWithEventType(): void
    {
        $data = ['test' => 'data'];

        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
            type: 'update',
        );

        $this->assertEquals('update', $update->getType());
    }

    /**
     * Test JSON update with retry
     */
    public function testJsonUpdateWithRetry(): void
    {
        $data = ['test' => 'data'];

        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
            retry: 5000,
        );

        $this->assertEquals(5000, $update->getRetry());
    }

    /**
     * Test JSON update with nested arrays
     */
    public function testJsonUpdateWithNestedArrays(): void
    {
        $data = [
            'user' => [
                'id' => 123,
                'name' => 'John',
                'tags' => ['admin', 'user'],
            ],
        ];

        $update = JsonUpdate::create(
            topics: '/users/123',
            data: $data,
        );

        $expected = '{"user":{"id":123,"name":"John","tags":["admin","user"]}}';
        $this->assertEquals($expected, $update->getData());
    }

    /**
     * Test JSON update with object data
     */
    public function testJsonUpdateWithObjectData(): void
    {
        $object = new stdClass();
        $object->name = 'Test';
        $object->value = 42;

        $update = JsonUpdate::create(
            topics: '/test',
            data: $object,
        );

        $this->assertEquals('{"name":"Test","value":42}', $update->getData());
    }

    /**
     * Test JSON update with null data
     */
    public function testJsonUpdateWithNullData(): void
    {
        $update = JsonUpdate::create(
            topics: '/test',
            data: null,
        );

        $this->assertEquals('null', $update->getData());
    }

    /**
     * Test JSON update with boolean data
     */
    public function testJsonUpdateWithBooleanData(): void
    {
        $update = JsonUpdate::create(
            topics: '/test',
            data: true,
        );

        $this->assertEquals('true', $update->getData());
    }

    /**
     * Test JSON update with numeric data
     */
    public function testJsonUpdateWithNumericData(): void
    {
        $update = JsonUpdate::create(
            topics: '/test',
            data: 42,
        );

        $this->assertEquals('42', $update->getData());
    }

    /**
     * Test JSON update with string data
     */
    public function testJsonUpdateWithStringData(): void
    {
        $update = JsonUpdate::create(
            topics: '/test',
            data: 'simple string',
        );

        $this->assertEquals('"simple string"', $update->getData());
    }

    /**
     * Test JSON update with custom encoding options
     */
    public function testJsonUpdateWithCustomEncodingOptions(): void
    {
        $data = ['url' => 'https://example.com/path', 'title' => 'Test & Title'];

        // Default includes JSON_UNESCAPED_SLASHES
        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
        );

        $this->assertStringContainsString('https://example.com/path', $update->getData());

        // With pretty print
        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
            jsonOptions: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $this->assertStringContainsString("\n", $update->getData());
    }

    /**
     * Test JSON update with Unicode characters
     */
    public function testJsonUpdateWithUnicodeCharacters(): void
    {
        $data = ['text' => 'Hello 世界 🌍'];

        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
            jsonOptions: JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $this->assertStringContainsString('世界', $update->getData());
        $this->assertStringContainsString('🌍', $update->getData());
    }

    /**
     * Test JSON update with empty array
     */
    public function testJsonUpdateWithEmptyArray(): void
    {
        $update = JsonUpdate::create(
            topics: '/test',
            data: [],
        );

        $this->assertEquals('[]', $update->getData());
    }

    /**
     * Test JSON update with associative array
     */
    public function testJsonUpdateWithAssociativeArray(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];

        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
        );

        $this->assertEquals('{"key1":"value1","key2":"value2"}', $update->getData());
    }

    /**
     * Test JSON update with indexed array
     */
    public function testJsonUpdateWithIndexedArray(): void
    {
        $data = ['value1', 'value2', 'value3'];

        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
        );

        $this->assertEquals('["value1","value2","value3"]', $update->getData());
    }

    /**
     * Test toArray method includes JSON-encoded data
     */
    public function testToArrayIncludesJsonEncodedData(): void
    {
        $data = ['status' => 'active'];

        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
            private: true,
        );

        $array = $update->toArray();

        $this->assertEquals(['/test'], $array['topic']);
        $this->assertEquals('{"status":"active"}', $array['data']);
        $this->assertEquals('on', $array['private']);
    }

    /**
     * Test JSON update inherits validation from parent
     */
    public function testJsonUpdateInheritsValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one topic must be specified');

        JsonUpdate::create(
            topics: [],
            data: ['test' => 'data'],
        );
    }

    /**
     * Test JSON update with invalid retry value
     */
    public function testJsonUpdateWithInvalidRetryValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry value must be a positive integer');

        JsonUpdate::create(
            topics: '/test',
            data: ['test' => 'data'],
            retry: -100,
        );
    }

    /**
     * Test JSON update with all parameters
     */
    public function testJsonUpdateWithAllParameters(): void
    {
        $data = ['status' => 'completed', 'progress' => 100];

        $update = JsonUpdate::create(
            topics: ['/jobs/123', '/notifications'],
            data: $data,
            private: true,
            id: 'job-123',
            type: 'job.completed',
            retry: 3000,
            jsonOptions: JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $this->assertEquals(['/jobs/123', '/notifications'], $update->getTopics());
        $this->assertEquals('{"status":"completed","progress":100}', $update->getData());
        $this->assertTrue($update->isPrivate());
        $this->assertEquals('job-123', $update->getId());
        $this->assertEquals('job.completed', $update->getType());
        $this->assertEquals(3000, $update->getRetry());
    }

    /**
     * Test JSON update with special characters
     */
    public function testJsonUpdateWithSpecialCharacters(): void
    {
        $data = [
            'html' => '<div class="test">Content</div>',
            'quote' => 'He said "Hello"',
            'backslash' => 'Path\\to\\file',
        ];

        $update = JsonUpdate::create(
            topics: '/test',
            data: $data,
        );

        $decoded = json_decode($update->getData(), true);
        $this->assertEquals($data['html'], $decoded['html']);
        $this->assertEquals($data['quote'], $decoded['quote']);
        $this->assertEquals($data['backslash'], $decoded['backslash']);
    }

    /**
     * Test JSON update is instance of Update
     */
    public function testJsonUpdateIsInstanceOfUpdate(): void
    {
        $update = JsonUpdate::create(
            topics: '/test',
            data: ['test' => 'data'],
        );

        $this->assertInstanceOf(Update::class, $update);
    }

    /**
     * Test fluent builder pattern
     */
    public function testFluentBuilderPattern(): void
    {
        $update = (new JsonUpdate('/books/1'))
            ->data(['status' => 'OutOfStock', 'quantity' => 0])
            ->private()
            ->id('book-123')
            ->type('book.updated')
            ->retry(3000)
            ->build();

        $this->assertInstanceOf(Update::class, $update);
        $this->assertEquals(['/books/1'], $update->getTopics());
        $this->assertTrue($update->isPrivate());
        $this->assertEquals('book-123', $update->getId());
        $this->assertEquals('book.updated', $update->getType());
        $this->assertEquals(3000, $update->getRetry());
        $this->assertEquals('{"status":"OutOfStock","quantity":0}', $update->getData());
    }

    /**
     * Test fluent builder with multiple topics
     */
    public function testFluentBuilderWithMultipleTopics(): void
    {
        $update = (new JsonUpdate(['/books/1', '/notifications']))
            ->data(['message' => 'Update'])
            ->build();

        $this->assertEquals(['/books/1', '/notifications'], $update->getTopics());
        $this->assertEquals('{"message":"Update"}', $update->getData());
    }

    /**
     * Test fluent builder with constructor
     */
    public function testFluentBuilderWithConstructor(): void
    {
        $update = (new JsonUpdate('/test'))
            ->data(['test' => 'data'])
            ->build();

        $this->assertEquals(['/test'], $update->getTopics());
        $this->assertEquals('{"test":"data"}', $update->getData());
    }

    /**
     * Test fluent builder with custom JSON options
     */
    public function testFluentBuilderWithJsonOptions(): void
    {
        $update = (new JsonUpdate('/test'))
            ->data(['html' => '<div>Test</div>', 'path' => '/some/path'])
            ->jsonOptions(JSON_HEX_TAG | JSON_UNESCAPED_SLASHES)
            ->build();

        $this->assertStringContainsString('\u003Cdiv\u003E', $update->getData());
        $this->assertStringContainsString('/some/path', $update->getData());
    }

    /**
     * Test fluent builder throws exception when no topics
     */
    public function testFluentBuilderThrowsExceptionWhenNoTopics(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one topic must be specified');

        (new JsonUpdate())
            ->data(['test' => 'data'])
            ->build();
    }

    /**
     * Test fluent builder with null data
     */
    public function testFluentBuilderWithNullData(): void
    {
        $update = (new JsonUpdate('/test'))
            ->data(null)
            ->build();

        $this->assertEquals('null', $update->getData());
    }

    /**
     * Test fluent builder with object data
     */
    public function testFluentBuilderWithObjectData(): void
    {
        $obj = new stdClass();
        $obj->name = 'Test';
        $obj->value = 123;

        $update = (new JsonUpdate('/test'))
            ->data($obj)
            ->build();

        $decoded = json_decode($update->getData(), true);
        $this->assertEquals('Test', $decoded['name']);
        $this->assertEquals(123, $decoded['value']);
    }

    /**
     * Test fluent builder method chaining
     */
    public function testFluentBuilderMethodChaining(): void
    {
        $builder = new JsonUpdate('/test');
        $builder->data(['initial' => 'data']);
        $builder->private(true);
        $builder->id('test-id');
        $builder->type('test.type');
        $builder->retry(1000);

        $update = $builder->build();

        $this->assertTrue($update->isPrivate());
        $this->assertEquals('test-id', $update->getId());
        $this->assertEquals('test.type', $update->getType());
        $this->assertEquals(1000, $update->getRetry());
    }
}
