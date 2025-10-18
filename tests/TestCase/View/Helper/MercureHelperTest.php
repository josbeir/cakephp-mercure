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
}
