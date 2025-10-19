<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Mercure\Authorization;
use Mercure\Controller\Component\MercureComponent;
use Mercure\Exception\MercureException;

/**
 * MercureComponent Test Case
 *
 * Tests the MercureComponent for controller integration.
 */
class MercureComponentTest extends TestCase
{
    private Controller $controller;

    private MercureComponent $component;

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
            'public_url' => 'https://public.mercure.example.com/.well-known/mercure',
            'jwt' => [
                'secret' => 'test-secret-key',
                'algorithm' => 'HS256',
            ],
            'cookie' => [
                'name' => 'mercureAuth',
            ],
        ]);

        // Create controller and component
        $request = new ServerRequest();

        $this->controller = new Controller($request);
        $this->controller->setResponse(new Response());

        $registry = new ComponentRegistry($this->controller);

        // Create authorization service
        $authorizationService = Authorization::create();

        $this->component = new MercureComponent($registry, $authorizationService);
    }

    /**
     * Tear down test case
     */
    protected function tearDown(): void
    {
        Authorization::clear();
        Configure::delete('Mercure');
        unset($this->component, $this->controller);
        parent::tearDown();
    }

    /**
     * Test authorize sets authorization cookie
     */
    public function testAuthorizeSetsCookie(): void
    {
        $subscribe = ['/feeds/123', '/notifications/*'];

        $result = $this->component->authorize($subscribe);

        // Should return component for chaining
        $this->assertSame($this->component, $result);

        // Check cookie was set
        $response = $this->controller->getResponse();
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
        $result = $this->component->authorize([]);

        $this->assertSame($this->component, $result);

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test authorize with additional claims
     */
    public function testAuthorizeWithAdditionalClaims(): void
    {
        $subscribe = ['/feeds/123'];
        $additionalClaims = [
            'sub' => 'user-123',
            'aud' => 'my-app',
        ];

        $result = $this->component->authorize($subscribe, $additionalClaims);

        $this->assertSame($this->component, $result);

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test authorize modifies controller response
     */
    public function testAuthorizeModifiesControllerResponse(): void
    {
        $originalResponse = $this->controller->getResponse();

        $this->component->authorize(['/feeds/123']);

        $newResponse = $this->controller->getResponse();

        // Response should be modified (different instance)
        $this->assertNotSame($originalResponse, $newResponse);

        // Cookie should be present
        $this->assertTrue($newResponse->getCookieCollection()->has('mercureAuth'));
    }

    /**
     * Test clearAuthorization removes cookie
     */
    public function testClearAuthorizationRemovesCookie(): void
    {
        // First set a cookie
        $this->component->authorize(['/feeds/123']);
        $response = $this->controller->getResponse();
        $this->assertTrue($response->getCookieCollection()->has('mercureAuth'));

        // Then clear it
        $result = $this->component->clearAuthorization();

        // Should return component for chaining
        $this->assertSame($this->component, $result);

        // Cookie should be expired
        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));

        $cookie = $cookies->get('mercureAuth');
        $this->assertTrue($cookie->isExpired());
    }

    /**
     * Test clearAuthorization modifies controller response
     */
    public function testClearAuthorizationModifiesControllerResponse(): void
    {
        $this->component->authorize(['/feeds/123']);
        $responseAfterAuthorize = $this->controller->getResponse();

        $this->component->clearAuthorization();

        $responseAfterClear = $this->controller->getResponse();

        // Response should be modified
        $this->assertNotSame($responseAfterAuthorize, $responseAfterClear);
    }

    /**
     * Test discover adds Link header
     */
    public function testDiscoverAddsLinkHeader(): void
    {
        $result = $this->component->discover();

        // Should return component for chaining
        $this->assertSame($this->component, $result);

        // Check Link header was added
        $response = $this->controller->getResponse();
        $linkHeader = $response->getHeaderLine('Link');
        $this->assertEquals('<https://public.mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test discover modifies controller response
     */
    public function testDiscoverModifiesControllerResponse(): void
    {
        $originalResponse = $this->controller->getResponse();

        $this->component->discover();

        $newResponse = $this->controller->getResponse();

        // Response should be modified
        $this->assertNotSame($originalResponse, $newResponse);

        // Link header should be present
        $this->assertNotEmpty($newResponse->getHeaderLine('Link'));
    }

    /**
     * Test method chaining with authorize and discover
     */
    public function testMethodChainingAuthorizeAndDiscover(): void
    {
        $result = $this->component->authorize(['/feeds/123'])->discover();

        // Should return component
        $this->assertSame($this->component, $result);

        $response = $this->controller->getResponse();

        // Should have both cookie and Link header
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));
        $this->assertNotEmpty($response->getHeaderLine('Link'));
    }

    /**
     * Test method chaining with clearAuthorization and discover
     */
    public function testMethodChainingClearAndDiscover(): void
    {
        $this->component->authorize(['/feeds/123']);

        $result = $this->component->clearAuthorization()->discover();

        $this->assertSame($this->component, $result);

        $response = $this->controller->getResponse();

        // Cookie should be expired
        $cookie = $response->getCookieCollection()->get('mercureAuth');
        $this->assertTrue($cookie->isExpired());

        // Link header should be present
        $this->assertNotEmpty($response->getHeaderLine('Link'));
    }

    /**
     * Test getCookieName returns configured name
     */
    public function testGetCookieNameReturnsConfiguredName(): void
    {
        $name = $this->component->getCookieName();
        $this->assertEquals('mercureAuth', $name);
    }

    /**
     * Test getHubUrl returns configured URL
     */
    public function testGetHubUrlReturnsConfiguredUrl(): void
    {
        $url = $this->component->getHubUrl();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test getHubUrl throws exception when not configured
     */
    public function testGetHubUrlThrowsExceptionWhenNotConfigured(): void
    {
        Configure::write('Mercure.url', '');
        Configure::write('Mercure.public_url', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        $this->component->getHubUrl();
    }

    /**
     * Test getPublicUrl returns configured public URL
     */
    public function testGetPublicUrlReturnsConfiguredPublicUrl(): void
    {
        $url = $this->component->getPublicUrl();
        $this->assertEquals('https://public.mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test getPublicUrl falls back to url when public_url not set
     */
    public function testGetPublicUrlFallsBackToUrl(): void
    {
        Configure::write('Mercure.public_url', null);

        $url = $this->component->getPublicUrl();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $url);
    }

    /**
     * Test startup with autoDiscover disabled
     */
    public function testStartupWithAutoDiscoverDisabled(): void
    {
        $this->component->setConfig('autoDiscover', false);

        $event = new Event('Controller.startup', $this->controller);
        $this->component->startup($event);

        $response = $this->controller->getResponse();
        $linkHeader = $response->getHeaderLine('Link');

        // Link header should not be added
        $this->assertEmpty($linkHeader);
    }

    /**
     * Test startup with autoDiscover enabled
     */
    public function testStartupWithAutoDiscoverEnabled(): void
    {
        $this->component->setConfig('autoDiscover', true);

        $event = new Event('Controller.startup', $this->controller);
        $this->component->startup($event);

        $response = $this->controller->getResponse();
        $linkHeader = $response->getHeaderLine('Link');

        // Link header should be added automatically
        $this->assertEquals('<https://public.mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test component with custom controller response
     */
    public function testComponentWithCustomControllerResponse(): void
    {
        // Set a custom response with specific status
        $customResponse = new Response(['status' => 201]);
        $this->controller->setResponse($customResponse);

        $this->component->authorize(['/feeds/123']);

        $response = $this->controller->getResponse();

        // Status should be preserved
        $this->assertEquals(201, $response->getStatusCode());

        // Cookie should be added
        $this->assertTrue($response->getCookieCollection()->has('mercureAuth'));
    }

    /**
     * Test component preserves existing response headers
     */
    public function testComponentPreservesExistingResponseHeaders(): void
    {
        $response = $this->controller->getResponse();
        $response = $response->withHeader('X-Custom-Header', 'custom-value');

        $this->controller->setResponse($response);

        $this->component->authorize(['/feeds/123'])->discover();

        $response = $this->controller->getResponse();

        // Custom header should be preserved
        $this->assertEquals('custom-value', $response->getHeaderLine('X-Custom-Header'));

        // New headers/cookies should be added
        $this->assertTrue($response->getCookieCollection()->has('mercureAuth'));
        $this->assertNotEmpty($response->getHeaderLine('Link'));
    }

    /**
     * Test component default configuration
     */
    public function testComponentDefaultConfiguration(): void
    {
        $config = $this->component->getConfig();

        $this->assertArrayHasKey('autoDiscover', $config);
        $this->assertFalse($config['autoDiscover']);
    }

    /**
     * Test component with custom configuration
     */
    public function testComponentWithCustomConfiguration(): void
    {
        $request = new ServerRequest();

        $controller = new Controller($request);
        $controller->setResponse(new Response());

        $registry = new ComponentRegistry($controller);

        // Create authorization service
        $authorizationService = Authorization::create();

        $component = new MercureComponent($registry, $authorizationService, [
            'autoDiscover' => true,
        ]);

        $config = $component->getConfig();

        $this->assertTrue($config['autoDiscover']);
    }

    /**
     * Test component creation throws exception when JWT secret missing
     */
    public function testComponentCreationThrowsExceptionWhenJwtSecretMissing(): void
    {
        Authorization::clear();
        Configure::write('Mercure.jwt.secret', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('JWT secret is not configured');

        // Exception should be thrown when creating the service
        Authorization::create();
    }

    /**
     * Test multiple authorize calls update cookie
     */
    public function testMultipleAuthorizeCallsUpdateCookie(): void
    {
        $this->component->authorize(['/feeds/123']);
        $response1 = $this->controller->getResponse();
        $cookie1 = $response1->getCookieCollection()->get('mercureAuth');

        $this->component->authorize(['/feeds/456', '/notifications']);
        $response2 = $this->controller->getResponse();
        $cookie2 = $response2->getCookieCollection()->get('mercureAuth');

        // Cookies should be different (different topics)
        $this->assertNotEquals($cookie1->getValue(), $cookie2->getValue());
    }

    /**
     * Test discover can be called multiple times
     */
    public function testDiscoverCanBeCalledMultipleTimes(): void
    {
        $this->component->discover();
        $this->component->discover();

        $response = $this->controller->getResponse();
        $linkHeaders = $response->getHeader('Link');

        // Should have two Link headers
        $this->assertCount(2, $linkHeaders);
    }

    /**
     * Test component works with existing cookies in response
     */
    public function testComponentWorksWithExistingCookiesInResponse(): void
    {
        // Add an existing cookie
        $response = $this->controller->getResponse();
        $cookie = Cookie::create('existing', 'test');
        $response = $response->withCookie($cookie);
        $this->controller->setResponse($response);

        $this->component->authorize(['/feeds/123']);

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();

        // Both cookies should exist
        $this->assertTrue($cookies->has('existing'));
        $this->assertTrue($cookies->has('mercureAuth'));
    }

    /**
     * Test fluent interface returns correct instance
     */
    public function testFluentInterfaceReturnsCorrectInstance(): void
    {
        $result1 = $this->component->authorize(['/feeds/123']);
        $result2 = $result1->discover();
        $result3 = $result2->clearAuthorization();

        $this->assertSame($this->component, $result1);
        $this->assertSame($this->component, $result2);
        $this->assertSame($this->component, $result3);
    }

    /**
     * Test component with wildcard topics
     */
    public function testComponentWithWildcardTopics(): void
    {
        $this->component->authorize(['*']);

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();

        $this->assertTrue($cookies->has('mercureAuth'));
        $this->assertNotEmpty($cookies->get('mercureAuth')->getValue());
    }

    /**
     * Test component with user-specific topics
     */
    public function testComponentWithUserSpecificTopics(): void
    {
        $userId = 'user-123';
        $topics = [
            sprintf('/users/%s/notifications', $userId),
            sprintf('/users/%s/messages', $userId),
        ];

        $this->component->authorize(
            subscribe: $topics,
            additionalClaims: [
                'sub' => $userId,
                'aud' => 'my-app',
            ],
        );

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();

        $this->assertTrue($cookies->has('mercureAuth'));
    }
}
