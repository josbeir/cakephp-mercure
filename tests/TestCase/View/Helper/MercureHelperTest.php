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
 * Tests the MercureHelper view helper for hub URL generation and cookie management.
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
            'cookie' => [
                'name' => 'mercureAuth',
                'lifetime' => 3600,
            ],
        ]);

        $this->view = new View();
        $this->view->setResponse(new Response());

        $this->helper = new MercureHelper($this->view);
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
     * Test getHubUrl returns configured URL
     */
    public function testGetHubUrlReturnsConfiguredUrl(): void
    {
        $url = $this->helper->getHubUrl();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test getHubUrl throws exception when not configured
     */
    public function testGetHubUrlThrowsExceptionWhenNotConfigured(): void
    {
        Configure::write('Mercure.url', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        $this->helper->getHubUrl();
    }

    /**
     * Test getHubUrl with single topic
     */
    public function testGetHubUrlWithSingleTopic(): void
    {
        $url = $this->helper->getHubUrl(['/feeds/123']);
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . urlencode('/feeds/123');

        $this->assertEquals($expected, $url);
    }

    /**
     * Test getHubUrl with multiple topics
     */
    public function testGetHubUrlWithMultipleTopics(): void
    {
        $url = $this->helper->getHubUrl(['/feeds/123', '/notifications/*']);
        $expectedTopic1 = urlencode('/feeds/123');
        $expectedTopic2 = urlencode('/notifications/*');
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . $expectedTopic1 . '&topic=' . $expectedTopic2;

        $this->assertEquals($expected, $url);
    }

    /**
     * Test getHubUrl with topics and additional options
     */
    public function testGetHubUrlWithTopicsAndOptions(): void
    {
        $url = $this->helper->getHubUrl(['/feeds/123'], ['lastEventId' => 'abc-123']);
        $this->assertStringContainsString('topic=' . urlencode('/feeds/123'), $url);
        $this->assertStringContainsString('lastEventId=abc-123', $url);
    }

    /**
     * Test getHubUrl with array option values
     */
    public function testGetHubUrlWithArrayOptionValues(): void
    {
        $url = $this->helper->getHubUrl([], ['filter' => ['status', 'priority']]);
        $this->assertStringContainsString('filter=status', $url);
        $this->assertStringContainsString('filter=priority', $url);
    }

    /**
     * Test getHubUrl handles existing query string
     */
    public function testGetHubUrlHandlesExistingQueryString(): void
    {
        Configure::write('Mercure.url', 'https://mercure.example.com/.well-known/mercure?existing=param');

        $url = $this->helper->getHubUrl(['/feeds/123']);
        $this->assertStringContainsString('existing=param', $url);
        $this->assertStringContainsString('&topic=', $url);
    }

    /**
     * Test authorize sets authorization cookie
     */
    public function testAuthorizeSetsCookie(): void
    {
        $this->helper->authorize(['/feeds/123', '/notifications/*']);

        $response = $this->view->getResponse();
        $cookies = $response->getCookieCollection();

        $this->assertTrue($cookies->has('mercureAuth'));
        $cookie = $cookies->get('mercureAuth');
        $this->assertNotEmpty($cookie->getValue());
    }

    /**
     * Test authorize with empty topics
     */
    public function testAuthorizeWithEmptyTopics(): void
    {
        $this->helper->authorize([]);

        $response = $this->view->getResponse();
        $cookies = $response->getCookieCollection();

        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test authorize with additional claims
     */
    public function testAuthorizeWithAdditionalClaims(): void
    {
        $additionalClaims = [
            'aud' => 'my-app',
            'exp' => time() + 3600,
        ];

        $this->helper->authorize(['/feeds/123'], $additionalClaims);

        $response = $this->view->getResponse();
        $cookies = $response->getCookieCollection();

        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test clearAuthorization removes cookie
     */
    public function testClearAuthorizationRemovesCookie(): void
    {
        // First set a cookie
        $this->helper->authorize(['/feeds/123']);

        // Then clear it
        $this->helper->clearAuthorization();

        $response = $this->view->getResponse();
        $cookies = $response->getCookieCollection();

        $this->assertTrue($cookies->has('mercureAuth'));
        $cookie = $cookies->get('mercureAuth');
        $this->assertTrue($cookie->isExpired());
    }

    /**
     * Test getCookieName returns name from authorization
     */
    public function testGetCookieNameReturnsNameFromAuthorization(): void
    {
        $name = $this->helper->getCookieName();
        $this->assertEquals('mercureAuth', $name);
    }

    /**
     * Test URL encoding handles special characters
     */
    public function testUrlEncodingHandlesSpecialCharacters(): void
    {
        $url = $this->helper->getHubUrl(['/feeds/{id}', '/user/name@example.com']);

        $this->assertStringContainsString(urlencode('/feeds/{id}'), $url);
        $this->assertStringContainsString(urlencode('/user/name@example.com'), $url);
    }

    /**
     * Test helper works without explicit configuration
     */
    public function testHelperWorksWithoutExplicitConfiguration(): void
    {
        // Helper should use singleton automatically
        $url = $this->helper->getHubUrl();
        $this->assertNotEmpty($url);

        $this->helper->authorize(['/test']);
        $cookies = $this->view->getResponse()->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test authorize when JWT secret is missing
     */
    public function testAuthorizeThrowsExceptionWhenJwtSecretMissing(): void
    {
        Authorization::clear();
        Configure::write('Mercure.jwt.secret', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('JWT secret is not configured');

        $this->helper->authorize(['/feeds/123']);
    }

    /**
     * Test multiple helpers share same singleton
     */
    public function testMultipleHelpersShareSameSingleton(): void
    {
        $view2 = new View();
        $view2->setResponse(new Response());

        $helper2 = new MercureHelper($view2);

        $this->assertEquals($this->helper->getCookieName(), $helper2->getCookieName());
        $this->assertEquals($this->helper->getHubUrl(), $helper2->getHubUrl());
    }

    /**
     * Test getHubUrl returns public_url when configured
     */
    public function testGetHubUrlReturnsPublicUrlWhenConfigured(): void
    {
        Configure::write('Mercure.url', 'http://internal.mercure:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://mercure.example.com/.well-known/mercure');

        $url = $this->helper->getHubUrl();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test getHubUrl falls back to url when public_url not set
     */
    public function testGetHubUrlFallsBackToUrlWhenPublicUrlNotSet(): void
    {
        Configure::write('Mercure.url', 'http://localhost:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', null);

        $url = $this->helper->getHubUrl();
        $this->assertEquals('http://localhost:3000/.well-known/mercure', $url);
    }

    /**
     * Test getHubUrl with public_url and topics
     */
    public function testGetHubUrlWithPublicUrlAndTopics(): void
    {
        Configure::write('Mercure.url', 'http://internal:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://public.example.com/.well-known/mercure');

        $url = $this->helper->getHubUrl(['/feeds/123']);
        $this->assertStringContainsString('https://public.example.com', $url);
        $this->assertStringContainsString('topic=' . urlencode('/feeds/123'), $url);
    }

    /**
     * Test url() returns hub URL without topics or authorization
     */
    public function testUrlReturnsHubUrlWithoutTopicsOrAuthorization(): void
    {
        $url = $this->helper->url();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);

        // Verify no cookie was set
        $cookies = $this->view->getResponse()->getCookieCollection();
        $this->assertFalse($cookies->has('mercureAuth'));
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
     * Test url() with multiple topics
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
     * Test url() with subscribe topics sets authorization cookie
     */
    public function testUrlWithSubscribeTopicsSetsAuthorizationCookie(): void
    {
        $url = $this->helper->url(['/feeds/123'], ['/feeds/123', '/notifications/*']);

        // Verify URL is correct
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . urlencode('/feeds/123');
        $this->assertEquals($expected, $url);

        // Verify cookie was set
        $cookies = $this->view->getResponse()->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test url() with subscribe topics and no connection topics
     */
    public function testUrlWithSubscribeTopicsAndNoConnectionTopics(): void
    {
        $url = $this->helper->url(null, ['/feeds/123', '/notifications/*']);

        // Verify URL is hub URL without query params
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);

        // Verify cookie was set
        $cookies = $this->view->getResponse()->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test url() with additional JWT claims
     */
    public function testUrlWithAdditionalJwtClaims(): void
    {
        $additionalClaims = [
            'aud' => 'my-app',
            'exp' => time() + 3600,
        ];

        $url = $this->helper->url(['/feeds/123'], ['/feeds/123'], $additionalClaims);

        // Verify URL is correct
        $expected = 'https://mercure.example.com/.well-known/mercure?topic=' . urlencode('/feeds/123');
        $this->assertEquals($expected, $url);

        // Verify cookie was set
        $cookies = $this->view->getResponse()->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));
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
     * Test url() with both topics and subscribe parameters
     */
    public function testUrlWithBothTopicsAndSubscribeParameters(): void
    {
        // Should set cookie and return URL with topic
        $url = $this->helper->url(
            ['https://example.com/books/{id}'],
            ['https://example.com/books/{id}'],
        );

        $expectedUrl = 'https://mercure.example.com/.well-known/mercure?topic=' . urlencode('https://example.com/books/{id}');
        $this->assertEquals($expectedUrl, $url);

        $cookies = $this->view->getResponse()->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test url() with public_url configuration
     */
    public function testUrlWithPublicUrlConfiguration(): void
    {
        Configure::write('Mercure.url', 'http://internal:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://public.example.com/.well-known/mercure');

        $url = $this->helper->url(['/feeds/123']);
        $this->assertStringContainsString('https://public.example.com', $url);
        $this->assertStringContainsString('topic=' . urlencode('/feeds/123'), $url);
    }

    /**
     * Test discover() adds Link header to response
     */
    public function testDiscoverAddsLinkHeaderToResponse(): void
    {
        Configure::write('Mercure.public_url', 'https://mercure.example.com/.well-known/mercure');

        $this->helper->discover();

        $response = $this->view->getResponse();
        $linkHeader = $response->getHeaderLine('Link');
        $this->assertEquals('<https://mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test discover() uses public_url when configured
     */
    public function testDiscoverUsesPublicUrl(): void
    {
        Configure::write('Mercure.url', 'http://internal:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://public.example.com/.well-known/mercure');

        $this->helper->discover();

        $response = $this->view->getResponse();
        $linkHeader = $response->getHeaderLine('Link');
        $this->assertEquals('<https://public.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test discover() falls back to url when public_url not set
     */
    public function testDiscoverFallsBackToUrl(): void
    {
        Configure::write('Mercure.url', 'https://fallback.example.com/.well-known/mercure');
        Configure::write('Mercure.public_url', null);

        $this->helper->discover();

        $response = $this->view->getResponse();
        $linkHeader = $response->getHeaderLine('Link');
        $this->assertEquals('<https://fallback.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test discover() throws exception when no URL configured
     */
    public function testDiscoverThrowsExceptionWhenNoUrlConfigured(): void
    {
        Configure::write('Mercure', [
            'url' => '',
            'public_url' => '',
            'jwt' => [
                'secret' => 'test-secret-key',
                'algorithm' => 'HS256',
            ],
        ]);
        Authorization::clear();

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        $this->helper->discover();
    }

    /**
     * Test discover() preserves existing headers
     */
    public function testDiscoverPreservesExistingHeaders(): void
    {
        $response = $this->view->getResponse();
        $response = $response->withHeader('X-Custom-Header', 'custom-value');

        $this->view->setResponse($response);

        $this->helper->discover();

        $response = $this->view->getResponse();
        $this->assertEquals('custom-value', $response->getHeaderLine('X-Custom-Header'));
        $this->assertNotEmpty($response->getHeaderLine('Link'));
    }

    /**
     * Test discover() can be combined with multiple Link headers
     */
    public function testDiscoverCanBeCombinedWithMultipleLinkHeaders(): void
    {
        $response = $this->view->getResponse();
        $response = $response->withAddedHeader('Link', '<https://example.com/other>; rel="other"');

        $this->view->setResponse($response);

        $this->helper->discover();

        $response = $this->view->getResponse();
        $linkHeaders = $response->getHeader('Link');
        $this->assertCount(2, $linkHeaders);
        $this->assertContains('<https://example.com/other>; rel="other"', $linkHeaders);
        $this->assertContains('<https://mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeaders);
    }

    /**
     * Test discover() can be called multiple times
     */
    public function testDiscoverCanBeCalledMultipleTimes(): void
    {
        $this->helper->discover();
        $this->helper->discover();

        $response = $this->view->getResponse();
        $linkHeaders = $response->getHeader('Link');

        // Should have two headers since we called discover() twice
        $this->assertCount(2, $linkHeaders);
    }

    /**
     * Test discover() preserves response status
     */
    public function testDiscoverPreservesResponseStatus(): void
    {
        $response = new Response(['status' => 201]);
        $this->view->setResponse($response);

        $this->helper->discover();

        $response = $this->view->getResponse();
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertNotEmpty($response->getHeaderLine('Link'));
    }

    /**
     * Test discover() can be combined with authorize()
     */
    public function testDiscoverCanBeCombinedWithAuthorize(): void
    {
        $this->helper->authorize(['/feeds/123']);
        $this->helper->discover();

        $response = $this->view->getResponse();

        // Check cookie was set
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));

        // Check Link header was added
        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('rel="mercure"', $linkHeader);
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
        $url = $helper->getHubUrl(['/books/123']);

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
        $url = $helper->getHubUrl(['/notifications', '/books/123']);

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
     * Test helper works without default topics (backward compatibility)
     */
    public function testHelperWorksWithoutDefaultTopics(): void
    {
        $url = $this->helper->getHubUrl(['/books/123']);

        $this->assertStringContainsString('topic=%2Fbooks%2F123', $url);
        $this->assertEquals(1, substr_count($url, 'topic='));
    }
}
