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
            viewVars: [
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
            viewVars: [
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
            viewVars: ['title' => 'Test', 'content' => 'Content'],
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
            viewVars: ['title' => 'Private', 'content' => 'Secret'],
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
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: [
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
            viewVars: ['title' => 'Test', 'content' => 'Test'],
        );
    }

    /**
     * Test view update throws exception when both template and element specified
     *
     * Note: With the fluent builder, setting one clears the other, so we test
     * that neither template nor element is set when trying to build
     */
    public function testViewUpdateThrowsExceptionWhenBothTemplateAndElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either template or element must be specified');

        // Don't set template or element at all
        ViewUpdate::create(
            topics: '/test',
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: [],
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
            viewVars: [
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
            viewVars: ['title' => 'Test', 'content' => 'Content'],
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
        $this->expectExceptionMessage('At least one topic must be specified');

        ViewUpdate::create(
            topics: [],
            element: 'test_item',
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: ['title' => 'Complete Test', 'content' => 'All params'],
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
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: ['title' => 'Custom View', 'content' => 'Test'],
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
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: ['title' => 'Test', 'content' => 'Test'],
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
            viewVars: ['message' => 'Test', 'count' => 1],
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
            viewVars: [
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
            viewVars: [
                'title' => 123,
                'content' => 45.67,
            ],
        );

        $data = $update->getData();
        $this->assertStringContainsString('123', $data);
        $this->assertStringContainsString('45.67', $data);
    }

    /**
     * Test view update with viewOptions parameter
     */
    public function testViewUpdateWithViewOptions(): void
    {
        // Test that viewOptions() method works and update builds successfully
        $update = (new ViewUpdate('/test'))
            ->element('test_item')
            ->viewVars(['title' => 'Test With Options', 'content' => 'Content'])
            ->viewOptions(['theme' => 'CustomTheme', 'plugin' => 'TestPlugin'])
            ->build();

        $this->assertInstanceOf(Update::class, $update);
        $this->assertEquals(['/test'], $update->getTopics());
        $this->assertStringContainsString('Test With Options', $update->getData());
        $this->assertStringContainsString('Content', $update->getData());
    }

    /**
     * Test view update with empty viewOptions
     */
    public function testViewUpdateWithEmptyViewOptions(): void
    {
        $update = (new ViewUpdate('/test'))
            ->element('test_item')
            ->viewVars(['title' => 'Test', 'content' => 'Content'])
            ->viewOptions([])
            ->build();

        $this->assertInstanceOf(Update::class, $update);
        $this->assertStringContainsString('Test', $update->getData());
    }

    /**
     * Test fluent builder pattern
     */
    public function testFluentBuilderPattern(): void
    {
        $update = (new ViewUpdate('/books/1'))
            ->element('test_item')
            ->viewVars(['title' => 'Fluent Test', 'content' => 'Builder Pattern'])
            ->private()
            ->id('test-123')
            ->type('book.updated')
            ->retry(5000)
            ->build();

        $this->assertInstanceOf(Update::class, $update);
        $this->assertEquals(['/books/1'], $update->getTopics());
        $this->assertTrue($update->isPrivate());
        $this->assertEquals('test-123', $update->getId());
        $this->assertEquals('book.updated', $update->getType());
        $this->assertEquals(5000, $update->getRetry());
        $this->assertStringContainsString('Fluent Test', $update->getData());
    }

    /**
     * Test fluent builder with multiple topics
     */
    public function testFluentBuilderWithMultipleTopics(): void
    {
        $update = (new ViewUpdate(['/books/1', '/notifications']))
            ->element('test_item')
            ->viewVars(['title' => 'Multi', 'content' => 'Topics'])
            ->build();

        $this->assertEquals(['/books/1', '/notifications'], $update->getTopics());
    }

    /**
     * Test fluent builder with set() method
     */
    public function testFluentBuilderWithSetMethod(): void
    {
        $update = (new ViewUpdate('/test'))
            ->element('test_item')
            ->set('title', 'Set Method')
            ->set('content', 'Individual Values')
            ->build();

        $this->assertStringContainsString('Set Method', $update->getData());
        $this->assertStringContainsString('Individual Values', $update->getData());
    }

    /**
     * Test fluent builder switching between template and element
     *
     * When setting both template and element, the last one set should be used.
     */
    public function testFluentBuilderSwitchingTemplateAndElement(): void
    {
        // Setting template then element should use element
        $update1 = (new ViewUpdate('/test'))
            ->template('Test/view')
            ->element('test_item')
            ->viewVars(['title' => 'Test', 'content' => 'Test'])
            ->build();

        // Verify element was rendered (contains element markup)
        $this->assertStringContainsString('test-item', $update1->getData());
        $this->assertStringContainsString('Test', $update1->getData());

        // Setting element then template should use template
        $update2 = (new ViewUpdate('/test'))
            ->element('test_item')
            ->template('Test/view')
            ->viewVars(['message' => 'Test', 'count' => 1])
            ->build();

        // Verify template was rendered (contains template markup)
        $this->assertStringContainsString('test-template', $update2->getData());
        $this->assertStringContainsString('Test', $update2->getData());
    }

    /**
     * Test fluent builder throws exception when no template or element
     */
    public function testFluentBuilderThrowsExceptionWhenNoTemplateOrElement(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Either template or element must be specified');

        (new ViewUpdate('/test'))
            ->viewVars(['title' => 'Test'])
            ->build();
    }

    /**
     * Test fluent builder throws exception when no topics
     */
    public function testFluentBuilderThrowsExceptionWhenNoTopics(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one topic must be specified');

        (new ViewUpdate())
            ->element('test_item')
            ->viewVars(['title' => 'Test', 'content' => 'Test'])
            ->build();
    }
}
