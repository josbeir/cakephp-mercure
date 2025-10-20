<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\View\Helper;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Mercure\Authorization;
use Mercure\Exception\MercureException;
use Mercure\View\Helper\MercureHelper;

/**
 * MercureHelper Test Case
 *
 * Tests the MercureHelper view helper for hub URL generation and topic management.
 */
class MercureHelperTest extends TestCase
{
    private View $view;

    private MercureHelper $helper;

    /**
     * Set up test case
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear singleton before each test
        Authorization::clear();

        // Configure Mercure for tests
        Configure::write('Mercure', [
            'url' => 'https://mercure.example.com/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
                'algorithm' => 'HS256',
            ],
        ]);

        $this->view = new View();
        $this->view->setResponse(new Response());

        $this->helper = new MercureHelper($this->view, ['defaultTopics' => []]);
    }

    /**
     * Tear down test case
     */
    protected function tearDown(): void
    {
        Authorization::clear();
        Configure::delete('Mercure');
        parent::tearDown();
    }

    /**
     * Test url returns configured URL
     */
    public function testUrlReturnsConfiguredUrl(): void
    {
        $url = $this->helper->url();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test url throws exception when not configured
     */
    public function testUrlThrowsExceptionWhenNotConfigured(): void
    {
        Configure::write('Mercure.url', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        $this->helper->url();
    }

    /**
     * Test url with single topic
     */
    public function testUrlWithSingleTopic(): void
    {
        $url = $this->helper->url(['/feeds/123']);
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . urlencode('/feeds/123');

        $this->assertEquals($expected, $url);
    }

    /**
     * Test url with multiple topics
     */
    public function testUrlWithMultipleTopics(): void
    {
        $url = $this->helper->url(['/feeds/123', '/notifications/*']);
        $expectedTopic1 = urlencode('/feeds/123');
        $expectedTopic2 = urlencode('/notifications/*');
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . $expectedTopic1 . '&topic=' . $expectedTopic2;

        $this->assertEquals($expected, $url);
    }

    /**
     * Test url handles existing query string
     */
    public function testUrlHandlesExistingQueryString(): void
    {
        Configure::write('Mercure.url', 'https://mercure.example.com/.well-known/mercure?existing=param');

        $url = $this->helper->url(['/feeds/123']);
        $this->assertStringContainsString('existing=param', $url);
        $this->assertStringContainsString('&topic=', $url);
    }

    /**
     * Test URL encoding handles special characters
     */
    public function testUrlEncodingHandlesSpecialCharacters(): void
    {
        $url = $this->helper->url(['/feeds/{id}', '/user/name@example.com']);

        $this->assertStringContainsString(urlencode('/feeds/{id}'), $url);
        $this->assertStringContainsString(urlencode('/user/name@example.com'), $url);
    }

    /**
     * Test url returns public_url when configured
     */
    public function testUrlReturnsPublicUrlWhenConfigured(): void
    {
        Configure::write('Mercure.url', 'http://internal.mercure:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://mercure.example.com/.well-known/mercure');

        $url = $this->helper->url();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test url falls back to url when public_url not set
     */
    public function testUrlFallsBackToUrlWhenPublicUrlNotSet(): void
    {
        Configure::write('Mercure.url', 'http://localhost:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', null);

        $url = $this->helper->url();
        $this->assertEquals('http://localhost:3000/.well-known/mercure', $url);
    }

    /**
     * Test url with public_url and topics
     */
    public function testUrlWithPublicUrlAndTopics(): void
    {
        Configure::write('Mercure.url', 'http://internal:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://public.example.com/.well-known/mercure');

        $url = $this->helper->url(['/feeds/123']);
        $this->assertStringContainsString('https://public.example.com', $url);
        $this->assertStringContainsString('topic=' . urlencode('/feeds/123'), $url);
    }

    /**
     * Test url() returns hub URL without topics
     */
    public function testUrlReturnsHubUrlWithoutTopics(): void
    {
        $url = $this->helper->url();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test url() with single topic as string
     */
    public function testUrlWithSingleTopicAsString(): void
    {
        $url = $this->helper->url('/feeds/123');
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . urlencode('/feeds/123');

        $this->assertEquals($expected, $url);
    }

    /**
     * Test url() with single topic as array
     */
    public function testUrlWithSingleTopicAsArray(): void
    {
        $url = $this->helper->url(['/feeds/123']);
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . urlencode('/feeds/123');

        $this->assertEquals($expected, $url);
    }

    /**
     * Test url() method with multiple topics
     */
    public function testUrlMethodWithMultipleTopics(): void
    {
        $url = $this->helper->url(['/feeds/123', '/notifications/*']);
        $expectedTopic1 = urlencode('/feeds/123');
        $expectedTopic2 = urlencode('/notifications/*');
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . $expectedTopic1 . '&topic=' . $expectedTopic2;

        $this->assertEquals($expected, $url);
    }

    /**
     * Test url() with empty array topics
     */
    public function testUrlWithEmptyArrayTopics(): void
    {
        $url = $this->helper->url([]);
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test url() with null topics
     */
    public function testUrlWithNullTopics(): void
    {
        $url = $this->helper->url(null);
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test default topics are merged with provided topics
     */
    public function testDefaultTopicsAreMergedWithProvidedTopics(): void
    {
        // Create helper with default topics
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/notifications', '/alerts'],
        ]);

        // Get URL with additional topics
        $url = $helper->url(['/books/123']);

        // Should contain all topics
        $this->assertStringContainsString('topic=%2Fnotifications', $url);
        $this->assertStringContainsString('topic=%2Falerts', $url);
        $this->assertStringContainsString('topic=%2Fbooks%2F123', $url);
    }

    /**
     * Test default topics work with url() method
     */
    public function testDefaultTopicsWorkWithUrlMethod(): void
    {
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/notifications'],
        ]);

        $url = $helper->url(['/books/123']);

        $this->assertStringContainsString('topic=%2Fnotifications', $url);
        $this->assertStringContainsString('topic=%2Fbooks%2F123', $url);
    }

    /**
     * Test default topics remove duplicates
     */
    public function testDefaultTopicsRemoveDuplicates(): void
    {
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/notifications', '/alerts'],
        ]);

        // Provide a topic that overlaps with defaults
        $url = $helper->url(['/notifications', '/books/123']);

        // Count occurrences of /notifications - should appear only once
        $count = substr_count($url, 'topic=%2Fnotifications');
        $this->assertEquals(1, $count, 'Duplicate topics should be removed');

        // Other topics should still be present
        $this->assertStringContainsString('topic=%2Falerts', $url);
        $this->assertStringContainsString('topic=%2Fbooks%2F123', $url);
    }

    /**
     * Test getConfig returns configured default topics
     */
    public function testGetConfigReturnsConfiguredDefaultTopics(): void
    {
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/notifications', '/alerts'],
        ]);

        $defaults = $helper->getConfig('defaultTopics');

        $this->assertEquals(['/notifications', '/alerts'], $defaults);
    }

    /**
     * Test getConfig returns empty array when default topics not configured
     */
    public function testGetConfigReturnsEmptyArrayWhenDefaultTopicsNotConfigured(): void
    {
        $defaults = $this->helper->getConfig('defaultTopics', []);

        $this->assertEquals([], $defaults);
    }

    /**
     * Test helper works without default topics
     */
    public function testHelperWorksWithoutDefaultTopics(): void
    {
        $url = $this->helper->url(['/feeds/123']);
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . urlencode('/feeds/123');

        $this->assertEquals($expected, $url);
    }

    /**
     * Test addTopic adds topic to default topics
     */
    public function testAddTopicAddsTopicToDefaultTopics(): void
    {
        $this->helper->addTopic('/user/123/messages');

        $url = $this->helper->url();
        $this->assertStringContainsString('topic=%2Fuser%2F123%2Fmessages', $url);
    }

    /**
     * Test addTopic prevents duplicates
     */
    public function testAddTopicPreventsDuplicates(): void
    {
        $this->helper->addTopic('/notifications');
        $this->helper->addTopic('/notifications');

        $url = $this->helper->url();
        $count = substr_count($url, 'topic=%2Fnotifications');
        $this->assertEquals(1, $count);
    }

    /**
     * Test addTopic returns this for chaining
     */
    public function testAddTopicReturnsThisForChaining(): void
    {
        $result = $this->helper->addTopic('/test');
        $this->assertSame($this->helper, $result);
    }

    /**
     * Test addTopic can be chained
     */
    public function testAddTopicCanBeChained(): void
    {
        $this->helper
            ->addTopic('/notifications')
            ->addTopic('/alerts')
            ->addTopic('/messages');

        $url = $this->helper->url();

        $this->assertStringContainsString('topic=%2Fnotifications', $url);
        $this->assertStringContainsString('topic=%2Falerts', $url);
        $this->assertStringContainsString('topic=%2Fmessages', $url);
    }

    /**
     * Test addTopics adds multiple topics
     */
    public function testAddTopicsAddsMultipleTopics(): void
    {
        $this->helper->addTopics(['/books/456', '/comments/789']);

        $url = $this->helper->url();

        $this->assertStringContainsString('topic=%2Fbooks%2F456', $url);
        $this->assertStringContainsString('topic=%2Fcomments%2F789', $url);
    }

    /**
     * Test addTopics prevents duplicates
     */
    public function testAddTopicsPreventsDuplicates(): void
    {
        $this->helper->addTopics(['/notifications', '/alerts']);
        $this->helper->addTopics(['/notifications', '/messages']);

        $url = $this->helper->url();

        $count = substr_count($url, 'topic=%2Fnotifications');
        $this->assertEquals(1, $count);

        $this->assertStringContainsString('topic=%2Falerts', $url);
        $this->assertStringContainsString('topic=%2Fmessages', $url);
    }

    /**
     * Test addTopics returns this for chaining
     */
    public function testAddTopicsReturnsThisForChaining(): void
    {
        $result = $this->helper->addTopics(['/test1', '/test2']);
        $this->assertSame($this->helper, $result);
    }

    /**
     * Test addTopic works with configured default topics
     */
    public function testAddTopicWorksWithConfiguredDefaultTopics(): void
    {
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/notifications'],
        ]);

        $helper->addTopic('/messages');

        $url = $helper->url();

        $this->assertStringContainsString('topic=%2Fnotifications', $url);
        $this->assertStringContainsString('topic=%2Fmessages', $url);
    }

    /**
     * Test dynamically added topics are merged with url method
     */
    public function testDynamicallyAddedTopicsAreMergedWithUrlMethod(): void
    {
        $this->helper->addTopic('/notifications');

        $url = $this->helper->url(['/books/123']);

        $this->assertStringContainsString('topic=%2Fnotifications', $url);
        $this->assertStringContainsString('topic=%2Fbooks%2F123', $url);
    }

    /**
     * Test dynamically added topics are merged with url
     */
    public function testDynamicallyAddedTopicsAreMergedWithUrl(): void
    {
        $this->helper->addTopics(['/notifications', '/alerts']);

        $url = $this->helper->url(['/books/123']);

        $this->assertStringContainsString('topic=%2Fnotifications', $url);
        $this->assertStringContainsString('topic=%2Falerts', $url);
        $this->assertStringContainsString('topic=%2Fbooks%2F123', $url);
    }

    /**
     * Test addTopics with empty array does nothing
     */
    public function testAddTopicsWithEmptyArrayDoesNothing(): void
    {
        $this->helper->addTopics([]);

        $url = $this->helper->url();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test addTopic does not mutate config
     */
    public function testAddTopicDoesNotMutateConfig(): void
    {
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/notifications'],
        ]);

        $helper->addTopic('/messages');

        // Config should still only have original default topics
        $config = $helper->getConfig('defaultTopics');
        $this->assertEquals(['/notifications'], $config);
    }

    /**
     * Test addTopics does not mutate config
     */
    public function testAddTopicsDoesNotMutateConfig(): void
    {
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/notifications'],
        ]);

        $helper->addTopics(['/messages', '/alerts']);

        // Config should still only have original default topics
        $config = $helper->getConfig('defaultTopics');
        $this->assertEquals(['/notifications'], $config);
    }

    /**
     * Test helper reads topics from view variable
     */
    public function testHelperReadsTopicsFromViewVariable(): void
    {
        $this->view->set('_mercureTopics', ['/component/topic']);

        $helper = new MercureHelper($this->view);

        $url = $helper->url();
        $this->assertStringContainsString('topic=%2Fcomponent%2Ftopic', $url);
    }

    /**
     * Test helper merges view variable with default topics
     */
    public function testHelperMergesViewVariableWithDefaultTopics(): void
    {
        $this->view->set('_mercureTopics', ['/component/topic']);

        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/helper/topic'],
        ]);

        $url = $helper->url();

        $this->assertStringContainsString('topic=%2Fcomponent%2Ftopic', $url);
        $this->assertStringContainsString('topic=%2Fhelper%2Ftopic', $url);
    }

    /**
     * Test helper works without view variable
     */
    public function testHelperWorksWithoutViewVariable(): void
    {
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/helper/topic'],
        ]);

        $url = $helper->url();
        $this->assertStringContainsString('topic=%2Fhelper%2Ftopic', $url);
    }

    /**
     * Test helper ignores non-array view variable
     */
    public function testHelperIgnoresNonArrayViewVariable(): void
    {
        $this->view->set('_mercureTopics', 'not-an-array');

        $helper = new MercureHelper($this->view);

        $url = $helper->url(['/test']);
        $this->assertStringContainsString('topic=%2Ftest', $url);
    }

    /**
     * Test helper removes duplicates from view variable
     */
    public function testHelperRemovesDuplicatesFromViewVariable(): void
    {
        $this->view->set('_mercureTopics', ['/notifications', '/notifications']);

        $helper = new MercureHelper($this->view);

        $url = $helper->url();
        $count = substr_count($url, 'topic=%2Fnotifications');
        $this->assertEquals(1, $count);
    }

    /**
     * Test helper merges all topic sources
     */
    public function testHelperMergesAllTopicSources(): void
    {
        // View variable topics
        $this->view->set('_mercureTopics', ['/component/topic']);

        // Helper default topics
        $helper = new MercureHelper($this->view, [
            'defaultTopics' => ['/helper/topic'],
        ]);

        // Dynamically added topics
        $helper->addTopic('/dynamic/topic');

        // URL method topics
        $url = $helper->url(['/url/topic']);

        // All should be present
        $this->assertStringContainsString('topic=%2Fcomponent%2Ftopic', $url);
        $this->assertStringContainsString('topic=%2Fhelper%2Ftopic', $url);
        $this->assertStringContainsString('topic=%2Fdynamic%2Ftopic', $url);
        $this->assertStringContainsString('topic=%2Furl%2Ftopic', $url);
    }
}
