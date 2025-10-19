<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\TestSuite\TestCase;
use InvalidArgumentException;
use Mercure\Update\SimpleUpdate;
use Mercure\Update\Update;

/**
 * SimpleUpdate Test Case
 *
 * Tests the SimpleUpdate class for creating updates with raw string data.
 */
class SimpleUpdateTest extends TestCase
{
    /**
     * Test basic simple update with string data
     */
    public function testBasicSimpleUpdateWithString(): void
    {
        $data = 'Book is out of stock';

        $update = SimpleUpdate::create(
            topics: '/books/1',
            data: $data,
        );

        $this->assertEquals(['/books/1'], $update->getTopics());
        $this->assertEquals($data, $update->getData());
        $this->assertFalse($update->isPrivate());
    }

    /**
     * Test simple update with multiple topics
     */
    public function testSimpleUpdateWithMultipleTopics(): void
    {
        $data = 'Update message';

        $update = SimpleUpdate::create(
            topics: ['/books/1', '/notifications'],
            data: $data,
        );

        $this->assertEquals(['/books/1', '/notifications'], $update->getTopics());
        $this->assertEquals($data, $update->getData());
    }

    /**
     * Test simple update with private flag
     */
    public function testSimpleUpdateWithPrivateFlag(): void
    {
        $data = 'Private message';

        $update = SimpleUpdate::create(
            topics: '/users/123/private',
            data: $data,
            private: true,
        );

        $this->assertTrue($update->isPrivate());
        $this->assertEquals($data, $update->getData());
    }

    /**
     * Test simple update with event ID
     */
    public function testSimpleUpdateWithEventId(): void
    {
        $data = 'Test message';

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $data,
            id: 'event-123',
        );

        $this->assertEquals('event-123', $update->getId());
        $this->assertEquals($data, $update->getData());
    }

    /**
     * Test simple update with event type
     */
    public function testSimpleUpdateWithEventType(): void
    {
        $data = 'Test message';

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $data,
            type: 'message.new',
        );

        $this->assertEquals('message.new', $update->getType());
        $this->assertEquals($data, $update->getData());
    }

    /**
     * Test simple update with retry
     */
    public function testSimpleUpdateWithRetry(): void
    {
        $data = 'Test message';

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $data,
            retry: 5000,
        );

        $this->assertEquals(5000, $update->getRetry());
        $this->assertEquals($data, $update->getData());
    }

    /**
     * Test simple update with pre-encoded JSON
     */
    public function testSimpleUpdateWithPreEncodedJson(): void
    {
        $json = json_encode(['status' => 'available', 'quantity' => 10]);
        assert($json !== false);

        $update = SimpleUpdate::create(
            topics: '/books/1',
            data: $json,
        );

        $this->assertEquals($json, $update->getData());
        $decoded = json_decode($update->getData(), true);
        $this->assertEquals('available', $decoded['status']);
        $this->assertEquals(10, $decoded['quantity']);
    }

    /**
     * Test simple update with HTML data
     */
    public function testSimpleUpdateWithHtmlData(): void
    {
        $data = '<div class="alert">Alert message</div>';

        $update = SimpleUpdate::create(
            topics: '/notifications',
            data: $data,
        );

        $this->assertEquals($data, $update->getData());
        $this->assertStringContainsString('alert', $update->getData());
    }

    /**
     * Test simple update with empty string is allowed
     */
    public function testSimpleUpdateWithEmptyStringIsAllowed(): void
    {
        $update = SimpleUpdate::create(
            topics: '/test',
            data: '',
        );

        $this->assertEquals(['/test'], $update->getTopics());
        $this->assertEquals('', $update->getData());
    }

    /**
     * Test simple update inherits validation from parent
     */
    public function testSimpleUpdateInheritsValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one topic must be specified');

        SimpleUpdate::create(
            topics: [],
            data: 'Test data',
        );
    }

    /**
     * Test simple update with invalid retry value
     */
    public function testSimpleUpdateWithInvalidRetryValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry value must be a positive integer');

        SimpleUpdate::create(
            topics: '/test',
            data: 'Test data',
            retry: -100,
        );
    }

    /**
     * Test simple update with all parameters
     */
    public function testSimpleUpdateWithAllParameters(): void
    {
        $data = 'Complete test message';

        $update = SimpleUpdate::create(
            topics: ['/books/123', '/notifications'],
            data: $data,
            private: true,
            id: 'book-123',
            type: 'book.updated',
            retry: 3000,
        );

        $this->assertEquals(['/books/123', '/notifications'], $update->getTopics());
        $this->assertEquals($data, $update->getData());
        $this->assertTrue($update->isPrivate());
        $this->assertEquals('book-123', $update->getId());
        $this->assertEquals('book.updated', $update->getType());
        $this->assertEquals(3000, $update->getRetry());
    }

    /**
     * Test simple update is instance of Update
     */
    public function testSimpleUpdateIsInstanceOfUpdate(): void
    {
        $update = SimpleUpdate::create(
            topics: '/test',
            data: 'Test data',
        );

        $this->assertInstanceOf(Update::class, $update);
    }

    /**
     * Test simple update toArray includes data
     */
    public function testSimpleUpdateToArrayIncludesData(): void
    {
        $data = 'Test message';

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $data,
            private: true,
        );

        $array = $update->toArray();

        $this->assertEquals(['/test'], $array['topic']);
        $this->assertEquals($data, $array['data']);
        $this->assertEquals('on', $array['private']);
    }

    /**
     * Test fluent builder pattern
     */
    public function testFluentBuilderPattern(): void
    {
        $update = (new SimpleUpdate('/books/1'))
            ->data('Out of stock')
            ->private()
            ->id('book-123')
            ->type('book.updated')
            ->retry(5000)
            ->build();

        $this->assertInstanceOf(Update::class, $update);
        $this->assertEquals(['/books/1'], $update->getTopics());
        $this->assertEquals('Out of stock', $update->getData());
        $this->assertTrue($update->isPrivate());
        $this->assertEquals('book-123', $update->getId());
        $this->assertEquals('book.updated', $update->getType());
        $this->assertEquals(5000, $update->getRetry());
    }

    /**
     * Test fluent builder with multiple topics
     */
    public function testFluentBuilderWithMultipleTopics(): void
    {
        $update = (new SimpleUpdate(['/books/1', '/notifications']))
            ->data('Update message')
            ->build();

        $this->assertEquals(['/books/1', '/notifications'], $update->getTopics());
        $this->assertEquals('Update message', $update->getData());
    }

    /**
     * Test fluent builder with constructor
     */
    public function testFluentBuilderWithConstructor(): void
    {
        $update = (new SimpleUpdate('/test'))
            ->data('Simple data')
            ->build();

        $this->assertEquals(['/test'], $update->getTopics());
        $this->assertEquals('Simple data', $update->getData());
    }

    /**
     * Test fluent builder throws exception when no topics
     */
    public function testFluentBuilderThrowsExceptionWhenNoTopics(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one topic must be specified');

        (new SimpleUpdate())
            ->data('Test data')
            ->build();
    }

    /**
     * Test fluent builder with default empty data
     */
    public function testFluentBuilderWithDefaultEmptyData(): void
    {
        $update = (new SimpleUpdate('/test'))
            ->build();

        $this->assertEquals(['/test'], $update->getTopics());
        $this->assertEquals('', $update->getData());
    }

    /**
     * Test fluent builder method chaining
     */
    public function testFluentBuilderMethodChaining(): void
    {
        $builder = new SimpleUpdate('/test');
        $builder->data('Initial data');
        $builder->private(true);
        $builder->id('test-id');
        $builder->type('test.type');
        $builder->retry(1000);

        $update = $builder->build();

        $this->assertEquals('Initial data', $update->getData());
        $this->assertTrue($update->isPrivate());
        $this->assertEquals('test-id', $update->getId());
        $this->assertEquals('test.type', $update->getType());
        $this->assertEquals(1000, $update->getRetry());
    }

    /**
     * Test simple update with special characters
     */
    public function testSimpleUpdateWithSpecialCharacters(): void
    {
        $data = 'Message with "quotes" and & ampersands';

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $data,
        );

        $this->assertEquals($data, $update->getData());
        $this->assertStringContainsString('"quotes"', $update->getData());
        $this->assertStringContainsString('&', $update->getData());
    }

    /**
     * Test simple update with newlines
     */
    public function testSimpleUpdateWithNewlines(): void
    {
        $data = "Line 1\nLine 2\nLine 3";

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $data,
        );

        $this->assertEquals($data, $update->getData());
        $this->assertStringContainsString("\n", $update->getData());
    }

    /**
     * Test simple update with unicode characters
     */
    public function testSimpleUpdateWithUnicodeCharacters(): void
    {
        $data = 'Message with émojis 🎉 and spëcial çharacters';

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $data,
        );

        $this->assertEquals($data, $update->getData());
        $this->assertStringContainsString('🎉', $update->getData());
        $this->assertStringContainsString('émojis', $update->getData());
    }

    /**
     * Test simple update with XML data
     */
    public function testSimpleUpdateWithXmlData(): void
    {
        $data = '<?xml version="1.0"?><message>Test</message>';

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $data,
        );

        $this->assertEquals($data, $update->getData());
        $this->assertStringContainsString('<message>', $update->getData());
    }

    /**
     * Test simple update data is not modified
     */
    public function testSimpleUpdateDataIsNotModified(): void
    {
        $original = 'Original data with <html> & special chars';

        $update = SimpleUpdate::create(
            topics: '/test',
            data: $original,
        );

        // Data should be passed as-is without any encoding or modification
        $this->assertSame($original, $update->getData());
    }
}
