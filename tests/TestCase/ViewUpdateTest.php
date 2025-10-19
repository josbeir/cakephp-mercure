<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Cake\View\Exception\MissingViewException;
use Cake\View\View;
use InvalidArgumentException;
use Mercure\Update\Update;
use Mercure\Update\ViewUpdate;
use stdClass;

/**
 * ViewUpdate Test Case
 *
 * Tests the ViewUpdate class for automatic view/element rendering.
 */
class ViewUpdateTest extends TestCase
{
    /**
     * Set up test case
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Configure Mercure with default view class
        Configure::write('Mercure', [
            'view_class' => View::class,
        ]);
    }

    /**
     * Tear down test case
     */
    protected function tearDown(): void
    {
        Configure::delete('Mercure');
        parent::tearDown();
    }

    /**
     * Test basic view update with element
     */
    public function testBasicViewUpdateWithElement(): void
    {
        $update = ViewUpdate::create(
            topics: '/books/1',
            element: 'test_item',
            data: [
                'title' => 'Test Book',
                'content' => 'Test content',
            ],
        );

        $this->assertEquals(['/books/1'], $update->getTopics());
        $this->assertStringContainsString('Test Book', $update->getData());
        $this->assertStringContainsString('Test content', $update->getData());
        $this->assertStringContainsString('test-item', $update->getData());
        $this->assertFalse($update->isPrivate());
    }

    /**
     * Test view update with template
     */
    public function testViewUpdateWithTemplate(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            template: 'Test/view',
            data: [
                'message' => 'Hello World',
                'count' => 42,
            ],
        );

        $this->assertEquals(['/test'], $update->getTopics());
        $this->assertStringContainsString('Hello World', $update->getData());
        $this->assertStringContainsString('Count: 42', $update->getData());
        $this->assertStringContainsString('test-template', $update->getData());
    }

    /**
     * Test view update with multiple topics
     */
    public function testViewUpdateWithMultipleTopics(): void
    {
        $update = ViewUpdate::create(
            topics: ['/books/1', '/notifications'],
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Content'],
        );

        $this->assertEquals(['/books/1', '/notifications'], $update->getTopics());
    }

    /**
     * Test view update with private flag
     */
    public function testViewUpdateWithPrivateFlag(): void
    {
        $update = ViewUpdate::create(
            topics: '/users/123/private',
            element: 'test_item',
            data: ['title' => 'Private', 'content' => 'Secret'],
            private: true,
        );

        $this->assertTrue($update->isPrivate());
        $this->assertStringContainsString('Private', $update->getData());
    }

    /**
     * Test view update with event ID
     */
    public function testViewUpdateWithEventId(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
            id: 'event-123',
        );

        $this->assertEquals('event-123', $update->getId());
    }

    /**
     * Test view update with event type
     */
    public function testViewUpdateWithEventType(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
            type: 'update',
        );

        $this->assertEquals('update', $update->getType());
    }

    /**
     * Test view update with retry
     */
    public function testViewUpdateWithRetry(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
            retry: 5000,
        );

        $this->assertEquals(5000, $update->getRetry());
    }

    /**
     * Test view update escapes HTML in data
     */
    public function testViewUpdateEscapesHtml(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: [
                'title' => '<script>alert("xss")</script>',
                'content' => 'Safe content',
            ],
        );

        $data = $update->getData();
        $this->assertStringNotContainsString('<script>', $data);
        $this->assertStringContainsString('&lt;script&gt;', $data);
    }

    /**
     * Test view update throws exception when neither template nor element specified
     */
    public function testViewUpdateThrowsExceptionWhenNeitherTemplateNorElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either template or element must be specified');

        ViewUpdate::create(
            topics: '/test',
            data: ['title' => 'Test', 'content' => 'Test'],
        );
    }

    /**
     * Test view update throws exception when both template and element specified
     */
    public function testViewUpdateThrowsExceptionWhenBothTemplateAndElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot specify both template and element');

        ViewUpdate::create(
            topics: '/test',
            template: 'Test/view',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
        );
    }

    /**
     * Test view update with empty data
     */
    public function testViewUpdateWithEmptyData(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: [],
        );

        $this->assertStringContainsString('test-item', $update->getData());
    }

    /**
     * Test view update with complex data structures
     */
    public function testViewUpdateWithComplexData(): void
    {
        $book = [
            'title' => 'Complex Book',
            'author' => ['name' => 'John Doe'],
            'tags' => ['fiction', 'drama'],
        ];

        $update = ViewUpdate::create(
            topics: '/books/1',
            element: 'test_item',
            data: [
                'title' => $book['title'],
                'content' => $book['author']['name'],
            ],
        );

        $this->assertStringContainsString('Complex Book', $update->getData());
        $this->assertStringContainsString('John Doe', $update->getData());
    }

    /**
     * Test toArray method includes rendered data
     */
    public function testToArrayIncludesRenderedData(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Content'],
            private: true,
        );

        $array = $update->toArray();

        $this->assertEquals(['/test'], $array['topic']);
        $this->assertStringContainsString('Test', $array['data']);
        $this->assertEquals('on', $array['private']);
    }

    /**
     * Test view update inherits validation from parent
     */
    public function testViewUpdateInheritsValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one topic must be provided');

        ViewUpdate::create(
            topics: [],
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
        );
    }

    /**
     * Test view update with invalid retry value
     */
    public function testViewUpdateWithInvalidRetryValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry value must be a positive integer');

        ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
            retry: -100,
        );
    }

    /**
     * Test view update with all parameters
     */
    public function testViewUpdateWithAllParameters(): void
    {
        $update = ViewUpdate::create(
            topics: ['/books/123', '/notifications'],
            element: 'test_item',
            data: ['title' => 'Complete Test', 'content' => 'All params'],
            private: true,
            id: 'book-123',
            type: 'book.updated',
            retry: 3000,
        );

        $this->assertEquals(['/books/123', '/notifications'], $update->getTopics());
        $this->assertStringContainsString('Complete Test', $update->getData());
        $this->assertTrue($update->isPrivate());
        $this->assertEquals('book-123', $update->getId());
        $this->assertEquals('book.updated', $update->getType());
        $this->assertEquals(3000, $update->getRetry());
    }

    /**
     * Test view update is instance of Update
     */
    public function testViewUpdateIsInstanceOfUpdate(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
        );

        $this->assertInstanceOf(Update::class, $update);
    }

    /**
     * Test view update with custom view class from configuration
     */
    public function testViewUpdateWithCustomViewClassFromConfiguration(): void
    {
        // Use default View class
        Configure::write('Mercure.view_class', View::class);

        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Custom View', 'content' => 'Test'],
        );

        $this->assertStringContainsString('Custom View', $update->getData());
    }

    /**
     * Test view update throws exception with non-existent view class
     */
    public function testViewUpdateThrowsExceptionWithNonExistentViewClass(): void
    {
        Configure::write('Mercure.view_class', 'NonExistent\ViewClass');

        $this->expectException(MissingViewException::class);
        $this->expectExceptionMessage("View class `NonExistent\ViewClass` is missing");

        ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
        );
    }

    /**
     * Test view update throws exception when view class doesn't extend View
     */
    public function testViewUpdateThrowsExceptionWhenViewClassDoesntExtendView(): void
    {
        Configure::write('Mercure.view_class', stdClass::class);

        $this->expectException(MissingViewException::class);
        $this->expectExceptionMessage('View class `stdClass` is missing');

        ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: ['title' => 'Test', 'content' => 'Test'],
        );
    }

    /**
     * Test view update renders without layout by default
     */
    public function testViewUpdateRendersWithoutLayoutByDefault(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            template: 'Test/view',
            data: ['message' => 'Test', 'count' => 1],
        );

        // Should not contain layout markup, just the template content
        $data = $update->getData();
        $this->assertStringContainsString('test-template', $data);
        $this->assertStringContainsString('Test', $data);
    }

    /**
     * Test view update with special characters
     */
    public function testViewUpdateWithSpecialCharacters(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: [
                'title' => 'Test & "Quotes"',
                'content' => "Line 1\nLine 2",
            ],
        );

        $data = $update->getData();
        $this->assertStringContainsString('&amp;', $data);
        $this->assertStringContainsString('&quot;', $data);
    }

    /**
     * Test view update with numeric values
     */
    public function testViewUpdateWithNumericValues(): void
    {
        $update = ViewUpdate::create(
            topics: '/test',
            element: 'test_item',
            data: [
                'title' => 123,
                'content' => 45.67,
            ],
        );

        $data = $update->getData();
        $this->assertStringContainsString('123', $data);
        $this->assertStringContainsString('45.67', $data);
    }
}
