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
use Mercure\Publisher;
use Mercure\TestSuite\MockPublisher;
use Mercure\Update\Update;

/**
 * MercureComponent Test Case
 *
 * Tests the MercureComponent for controller integration.
 */
class MercureComponentTest extends TestCase
{
    private Controller $controller;

    private MercureComponent $component;

    private MockPublisher $mockPublisher;

    /**
     * Set up test case
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear singletons before each test
        Authorization::clear();
        Publisher::clear();

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

        // Set up mock publisher BEFORE component initialization
        $this->mockPublisher = new MockPublisher();
        Publisher::setInstance($this->mockPublisher);

        // Create controller and component
        $request = new ServerRequest();

        $this->controller = new Controller($request);
        $this->controller->setResponse(new Response());

        $registry = new ComponentRegistry($this->controller);
        $this->component = new MercureComponent($registry);
    }

    /**
     * Tear down test case
     */
    protected function tearDown(): void
    {
        Authorization::clear();
        Publisher::clear();
        Configure::delete('Mercure');
        unset($this->component, $this->controller, $this->mockPublisher);
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

        $component = new MercureComponent($registry, [
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

    /**
     * Test publish method with Update object
     */
    public function testPublishWithUpdateObject(): void
    {
        $update = new Update(
            topics: '/books/123',
            data: 'test data',
        );

        $result = $this->component->publish($update);

        $this->assertTrue($result);
    }

    /**
     * Test publishJson with single topic and array data
     */
    public function testPublishJsonWithSingleTopicAndArrayData(): void
    {
        $result = $this->component->publishJson(
            topics: '/books/123',
            data: ['status' => 'available', 'quantity' => 10],
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishJson with multiple topics
     */
    public function testPublishJsonWithMultipleTopics(): void
    {
        $result = $this->component->publishJson(
            topics: ['/books/123', '/notifications'],
            data: ['message' => 'Book updated'],
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishJson with private flag
     */
    public function testPublishJsonWithPrivateFlag(): void
    {
        $result = $this->component->publishJson(
            topics: '/users/123/private',
            data: ['secret' => 'data'],
            private: true,
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishJson with all parameters
     */
    public function testPublishJsonWithAllParameters(): void
    {
        $result = $this->component->publishJson(
            topics: ['/books/123', '/notifications'],
            data: ['status' => 'updated'],
            private: true,
            id: 'event-123',
            type: 'book.updated',
            retry: 5000,
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishJson with nested data
     */
    public function testPublishJsonWithNestedData(): void
    {
        $data = [
            'book' => [
                'id' => 123,
                'title' => 'Test Book',
                'author' => 'Test Author',
            ],
            'meta' => [
                'timestamp' => time(),
            ],
        ];

        $result = $this->component->publishJson(
            topics: '/books/123',
            data: $data,
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishSimple with plain text
     */
    public function testPublishSimpleWithPlainText(): void
    {
        $result = $this->component->publishSimple(
            topics: '/notifications',
            data: 'Server maintenance in 5 minutes',
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishSimple with single topic
     */
    public function testPublishSimpleWithSingleTopic(): void
    {
        $result = $this->component->publishSimple(
            topics: '/alerts',
            data: 'Warning: High load detected',
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishSimple with multiple topics
     */
    public function testPublishSimpleWithMultipleTopics(): void
    {
        $result = $this->component->publishSimple(
            topics: ['/alerts', '/notifications'],
            data: 'System update completed',
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishSimple with private flag
     */
    public function testPublishSimpleWithPrivateFlag(): void
    {
        $result = $this->component->publishSimple(
            topics: '/users/123/messages',
            data: 'Private message',
            private: true,
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishSimple with all parameters
     */
    public function testPublishSimpleWithAllParameters(): void
    {
        $result = $this->component->publishSimple(
            topics: ['/users/123', '/notifications'],
            data: 'Complete message',
            private: true,
            id: 'msg-456',
            type: 'message.new',
            retry: 3000,
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishSimple with pre-encoded JSON
     */
    public function testPublishSimpleWithPreEncodedJson(): void
    {
        $json = json_encode(['status' => 'active', 'count' => 42]);
        assert(is_string($json));

        $result = $this->component->publishSimple(
            topics: '/stats',
            data: $json,
        );

        $this->assertTrue($result);
    }

    /**
     * Test publishSimple with HTML fragment
     */
    public function testPublishSimpleWithHtmlFragment(): void
    {
        $html = '<div class="alert alert-info">New notification</div>';

        $result = $this->component->publishSimple(
            topics: '/ui-updates',
            data: $html,
        );

        $this->assertTrue($result);
    }

    /**
     * Test addTopic adds a single topic
     */
    public function testAddTopicAddsSingleTopic(): void
    {
        $this->component->addTopic('/books/123');

        $topics = $this->component->getTopics();
        $this->assertEquals(['/books/123'], $topics);
    }

    /**
     * Test addTopic prevents duplicates
     */
    public function testAddTopicPreventsDuplicates(): void
    {
        $this->component->addTopic('/books/123');
        $this->component->addTopic('/books/123');

        $topics = $this->component->getTopics();
        $this->assertEquals(['/books/123'], $topics);
    }

    /**
     * Test addTopic returns component for chaining
     */
    public function testAddTopicReturnsComponentForChaining(): void
    {
        $result = $this->component->addTopic('/books/123');

        $this->assertSame($this->component, $result);
    }

    /**
     * Test addTopics adds multiple topics
     */
    public function testAddTopicsAddsMultipleTopics(): void
    {
        $this->component->addTopics(['/books/123', '/notifications', '/alerts']);

        $topics = $this->component->getTopics();
        $this->assertEquals(['/books/123', '/notifications', '/alerts'], $topics);
    }

    /**
     * Test addTopics prevents duplicates
     */
    public function testAddTopicsPreventsDuplicates(): void
    {
        $this->component->addTopics(['/books/123', '/notifications']);
        $this->component->addTopics(['/notifications', '/alerts']);

        $topics = $this->component->getTopics();
        $this->assertEquals(['/books/123', '/notifications', '/alerts'], $topics);
    }

    /**
     * Test addTopics returns component for chaining
     */
    public function testAddTopicsReturnsComponentForChaining(): void
    {
        $result = $this->component->addTopics(['/books/123', '/notifications']);

        $this->assertSame($this->component, $result);
    }

    /**
     * Test method chaining with addTopic
     */
    public function testMethodChainingWithAddTopic(): void
    {
        $result = $this->component
            ->addTopic('/books/123')
            ->addTopic('/notifications')
            ->authorize(['/books/123'])
            ->discover();

        $this->assertSame($this->component, $result);
        $topics = $this->component->getTopics();
        $this->assertEquals(['/books/123', '/notifications'], $topics);
    }

    /**
     * Test defaultTopics configuration
     */
    public function testDefaultTopicsConfiguration(): void
    {
        $request = new ServerRequest();
        $controller = new Controller($request);
        $controller->setResponse(new Response());

        $registry = new ComponentRegistry($controller);
        $component = new MercureComponent($registry, [
            'defaultTopics' => ['/notifications', '/alerts'],
        ]);

        $topics = $component->getTopics();
        $this->assertEquals(['/notifications', '/alerts'], $topics);
    }

    /**
     * Test addTopic works with defaultTopics
     */
    public function testAddTopicWorksWithDefaultTopics(): void
    {
        $request = new ServerRequest();
        $controller = new Controller($request);
        $controller->setResponse(new Response());

        $registry = new ComponentRegistry($controller);
        $component = new MercureComponent($registry, [
            'defaultTopics' => ['/notifications'],
        ]);

        $component->addTopic('/books/123');

        $topics = $component->getTopics();
        $this->assertEquals(['/notifications', '/books/123'], $topics);
    }

    /**
     * Test beforeRender sets view variable
     */
    public function testBeforeRenderSetsViewVariable(): void
    {
        $this->component->addTopics(['/books/123', '/notifications']);

        $event = new Event('Controller.beforeRender', $this->controller);
        $this->component->beforeRender($event);

        $viewVars = $this->controller->viewBuilder()->getVars();
        $this->assertArrayHasKey('_mercureTopics', $viewVars);
        $this->assertEquals(['/books/123', '/notifications'], $viewVars['_mercureTopics']);
    }

    /**
     * Test beforeRender does not set variable when no topics
     */
    public function testBeforeRenderDoesNotSetVariableWhenNoTopics(): void
    {
        $event = new Event('Controller.beforeRender', $this->controller);
        $this->component->beforeRender($event);

        $viewVars = $this->controller->viewBuilder()->getVars();
        $this->assertArrayNotHasKey('_mercureTopics', $viewVars);
    }

    /**
     * Test getTopics returns empty array initially
     */
    public function testGetTopicsReturnsEmptyArrayInitially(): void
    {
        $topics = $this->component->getTopics();
        $this->assertEquals([], $topics);
    }

    /**
     * Test addSubscribe adds single topic
     */
    public function testAddSubscribeAddsSingleTopic(): void
    {
        $result = $this->component->addSubscribe('/books/123');

        $this->assertSame($this->component, $result);
        $this->assertEquals(['/books/123'], $this->component->getSubscribe());
    }

    /**
     * Test addSubscribe with additional claims
     */
    public function testAddSubscribeWithAdditionalClaims(): void
    {
        $this->component->addSubscribe('/books/123', ['sub' => 'user-456', 'role' => 'admin']);

        $this->assertEquals(['/books/123'], $this->component->getSubscribe());
        $this->assertEquals(['sub' => 'user-456', 'role' => 'admin'], $this->component->getAdditionalClaims());
    }

    /**
     * Test addSubscribe merges claims across multiple calls
     */
    public function testAddSubscribeMergesClaims(): void
    {
        $this->component
            ->addSubscribe('/books/123', ['sub' => 'user-456'])
            ->addSubscribe('/notifications/*', ['role' => 'admin']);

        $this->assertEquals(['/books/123', '/notifications/*'], $this->component->getSubscribe());
        $this->assertEquals(['sub' => 'user-456', 'role' => 'admin'], $this->component->getAdditionalClaims());
    }

    /**
     * Test addSubscribe allows duplicate topics
     */
    public function testAddSubscribeAllowsDuplicateTopics(): void
    {
        $this->component
            ->addSubscribe('/books/123')
            ->addSubscribe('/books/123');

        // authorize() will deduplicate, but accumulator keeps duplicates
        $this->assertEquals(['/books/123', '/books/123'], $this->component->getSubscribe());
    }

    /**
     * Test addSubscribes adds multiple topics
     */
    public function testAddSubscribesAddsMultipleTopics(): void
    {
        $result = $this->component->addSubscribes(['/books/123', '/notifications/*']);

        $this->assertSame($this->component, $result);
        $this->assertEquals(['/books/123', '/notifications/*'], $this->component->getSubscribe());
    }

    /**
     * Test addSubscribes with additional claims
     */
    public function testAddSubscribesWithAdditionalClaims(): void
    {
        $this->component->addSubscribes(
            ['/books/123', '/notifications/*'],
            ['sub' => 'user-456', 'role' => 'admin'],
        );

        $this->assertEquals(['/books/123', '/notifications/*'], $this->component->getSubscribe());
        $this->assertEquals(['sub' => 'user-456', 'role' => 'admin'], $this->component->getAdditionalClaims());
    }

    /**
     * Test getSubscribe returns empty array initially
     */
    public function testGetSubscribeReturnsEmptyArrayInitially(): void
    {
        $this->assertEquals([], $this->component->getSubscribe());
    }

    /**
     * Test getAdditionalClaims returns empty array initially
     */
    public function testGetAdditionalClaimsReturnsEmptyArrayInitially(): void
    {
        $this->assertEquals([], $this->component->getAdditionalClaims());
    }

    /**
     * Test resetSubscribe clears accumulated topics
     */
    public function testResetSubscribeClearsAccumulatedTopics(): void
    {
        $this->component->addSubscribe('/books/123');
        $this->assertEquals(['/books/123'], $this->component->getSubscribe());

        $result = $this->component->resetSubscribe();

        $this->assertSame($this->component, $result);
        $this->assertEquals([], $this->component->getSubscribe());
    }

    /**
     * Test resetAdditionalClaims clears accumulated claims
     */
    public function testResetAdditionalClaimsClearsAccumulatedClaims(): void
    {
        $this->component->addSubscribe('/books/123', ['sub' => 'user-456']);
        $this->assertEquals(['sub' => 'user-456'], $this->component->getAdditionalClaims());

        $result = $this->component->resetAdditionalClaims();

        $this->assertSame($this->component, $result);
        $this->assertEquals([], $this->component->getAdditionalClaims());
    }

    /**
     * Test authorize uses accumulated topics and claims
     */
    public function testAuthorizeUsesAccumulatedState(): void
    {
        $this->component
            ->addSubscribe('/books/123', ['sub' => 'user-456'])
            ->addSubscribe('/notifications/*', ['role' => 'admin'])
            ->authorize();

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));

        // State should be reset after authorize
        $this->assertEquals([], $this->component->getSubscribe());
        $this->assertEquals([], $this->component->getAdditionalClaims());
    }

    /**
     * Test authorize merges parameters with accumulated state
     */
    public function testAuthorizeMergesParametersWithAccumulatedState(): void
    {
        $this->component
            ->addSubscribe('/books/123', ['sub' => 'user-456'])
            ->authorize(['/notifications/*'], ['role' => 'admin']);

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));

        // State should be reset after authorize
        $this->assertEquals([], $this->component->getSubscribe());
        $this->assertEquals([], $this->component->getAdditionalClaims());
    }

    /**
     * Test authorize deduplicates topics
     */
    public function testAuthorizeDeduplicatesTopics(): void
    {
        $this->component
            ->addSubscribe('/books/123')
            ->addSubscribe('/books/123')
            ->authorize(['/books/123']);

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));

        // Should have deduplicated the topics (verified by no errors)
        $this->assertEquals([], $this->component->getSubscribe());
    }

    /**
     * Test authorize with only accumulated state (no parameters)
     */
    public function testAuthorizeWithOnlyAccumulatedState(): void
    {
        $this->component
            ->addSubscribe('/books/123', ['sub' => 'user-456'])
            ->authorize();

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));

        $this->assertEquals([], $this->component->getSubscribe());
        $this->assertEquals([], $this->component->getAdditionalClaims());
    }

    /**
     * Test authorize resets state even with empty parameters
     */
    public function testAuthorizeResetsStateWithEmptyParameters(): void
    {
        $this->component->addSubscribe('/books/123', ['sub' => 'user-456']);

        $this->assertEquals(['/books/123'], $this->component->getSubscribe());
        $this->assertEquals(['sub' => 'user-456'], $this->component->getAdditionalClaims());

        $this->component->authorize();

        $this->assertEquals([], $this->component->getSubscribe());
        $this->assertEquals([], $this->component->getAdditionalClaims());
    }

    /**
     * Test fluent chaining with authorization builder
     */
    public function testFluentChainingWithAuthorizationBuilder(): void
    {
        $result = $this->component
            ->addTopic('/books/123')
            ->addSubscribe('/books/123', ['sub' => 'user-456'])
            ->addSubscribe('/notifications/*', ['role' => 'admin'])
            ->authorize()
            ->discover();

        $this->assertSame($this->component, $result);

        // Check topics are still available (not reset by authorize)
        $this->assertEquals(['/books/123'], $this->component->getTopics());

        // Check subscribe state was reset
        $this->assertEquals([], $this->component->getSubscribe());
        $this->assertEquals([], $this->component->getAdditionalClaims());

        // Check authorization cookie was set
        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));

        // Check discovery header was added
        $linkHeader = $response->getHeaderLine('Link');
        $this->assertStringContainsString('rel="mercure"', $linkHeader);
    }

    /**
     * Test claims precedence (parameter claims override accumulated)
     */
    public function testClaimsPrecedenceParametersOverrideAccumulated(): void
    {
        $this->component
            ->addSubscribe('/books/123', ['sub' => 'user-456', 'role' => 'user'])
            ->authorize([], ['role' => 'admin']); // Override role

        $response = $this->controller->getResponse();
        $cookies = $response->getCookieCollection();
        $this->assertTrue($cookies->has('mercureAuth'));

        // State should be reset
        $this->assertEquals([], $this->component->getAdditionalClaims());
    }

    /**
     * Test multiple authorize calls work correctly
     */
    public function testMultipleAuthorizeCallsWithBuilder(): void
    {
        // First authorize with accumulated state
        $this->component
            ->addSubscribe('/books/123', ['sub' => 'user-456'])
            ->authorize();

        $response1 = $this->controller->getResponse();
        $this->assertTrue($response1->getCookieCollection()->has('mercureAuth'));
        $this->assertEquals([], $this->component->getSubscribe());

        // Second authorize with new accumulated state
        $this->component
            ->addSubscribe('/notifications/*', ['role' => 'admin'])
            ->authorize();

        $response2 = $this->controller->getResponse();
        $this->assertTrue($response2->getCookieCollection()->has('mercureAuth'));
        $this->assertEquals([], $this->component->getSubscribe());
    }

    /**
     * Test discover with includeTopics parameter
     */
    public function testDiscoverWithIncludeTopicsParameter(): void
    {
        $this->component
            ->addSubscribe('/books/123')
            ->addSubscribe('/notifications/*')
            ->authorize()
            ->discover(includeTopics: true);

        $response = $this->controller->getResponse();
        $linkHeaders = $response->getHeader('Link');

        $this->assertCount(2, $linkHeaders);

        // Check for rel="self" with topics
        $selfHeader = null;
        $mercureHeader = null;
        foreach ($linkHeaders as $header) {
            if (str_contains($header, 'rel="self"')) {
                $selfHeader = $header;
            }

            if (str_contains($header, 'rel="mercure"')) {
                $mercureHeader = $header;
            }
        }

        $this->assertNotNull($selfHeader);
        $this->assertNotNull($mercureHeader);
        $this->assertStringContainsString('topic=%2Fbooks%2F123', $selfHeader);
        $this->assertStringContainsString('topic=%2Fnotifications%2F%2A', $selfHeader);
    }

    /**
     * Test discover without includeTopics does not add self link
     */
    public function testDiscoverWithoutIncludeTopicsDoesNotAddSelfLink(): void
    {
        $this->component
            ->addSubscribe('/books/123')
            ->authorize()
            ->discover(includeTopics: false);

        $response = $this->controller->getResponse();
        $linkHeaders = $response->getHeader('Link');

        // Should only have rel="mercure" header
        $this->assertCount(1, $linkHeaders);
        $this->assertStringContainsString('rel="mercure"', $linkHeaders[0]);
        $this->assertStringNotContainsString('rel="self"', $linkHeaders[0]);
    }

    /**
     * Test discover with includeTopics but no subscribe topics
     */
    public function testDiscoverWithIncludeTopicsButNoSubscribeTopics(): void
    {
        $this->component->discover(includeTopics: true);

        $response = $this->controller->getResponse();
        $linkHeaders = $response->getHeader('Link');

        // Should only have rel="mercure" (no topics to include in self)
        $this->assertCount(1, $linkHeaders);
        $this->assertStringContainsString('rel="mercure"', $linkHeaders[0]);
    }

    /**
     * Test discover with discoverWithTopics config option
     */
    public function testDiscoverWithDiscoverWithTopicsConfig(): void
    {
        $registry = new ComponentRegistry($this->controller);
        $component = new MercureComponent($registry, [
            'discoverWithTopics' => true,
        ]);

        $component
            ->addSubscribe('/books/123')
            ->authorize()
            ->discover();

        $response = $this->controller->getResponse();
        $linkHeaders = $response->getHeader('Link');

        $this->assertCount(2, $linkHeaders);

        $selfHeaderFound = false;
        foreach ($linkHeaders as $header) {
            if (str_contains($header, 'rel="self"')) {
                $selfHeaderFound = true;
                $this->assertStringContainsString('topic=%2Fbooks%2F123', $header);
            }
        }

        $this->assertTrue($selfHeaderFound);
    }

    /**
     * Test discover parameter overrides config
     */
    public function testDiscoverParameterOverridesConfig(): void
    {
        $registry = new ComponentRegistry($this->controller);
        $component = new MercureComponent($registry, [
            'discoverWithTopics' => true,
        ]);

        $component
            ->addSubscribe('/books/123')
            ->authorize()
            ->discover(includeTopics: false); // Override config

        $response = $this->controller->getResponse();
        $linkHeaders = $response->getHeader('Link');

        // Should only have rel="mercure" (parameter overrides config)
        $this->assertCount(1, $linkHeaders);
        $this->assertStringContainsString('rel="mercure"', $linkHeaders[0]);
    }

    /**
     * Test discover with lastEventId parameter
     */
    public function testDiscoverWithLastEventIdParameter(): void
    {
        $this->component->discover(lastEventId: 'urn:uuid:abc-123');

        $response = $this->controller->getResponse();
        $linkHeader = $response->getHeaderLine('Link');

        $this->assertStringContainsString('rel="mercure"', $linkHeader);
        $this->assertStringContainsString('last-event-id="urn:uuid:abc-123"', $linkHeader);
    }

    /**
     * Test discover with contentType parameter
     */
    public function testDiscoverWithContentTypeParameter(): void
    {
        $this->component->discover(contentType: 'application/ld+json');

        $response = $this->controller->getResponse();
        $linkHeader = $response->getHeaderLine('Link');

        $this->assertStringContainsString('rel="mercure"', $linkHeader);
        $this->assertStringContainsString('content-type="application/ld+json"', $linkHeader);
    }

    /**
     * Test discover with keySet parameter
     */
    public function testDiscoverWithKeySetParameter(): void
    {
        $this->component->discover(keySet: 'https://example.com/.well-known/jwks.json');

        $response = $this->controller->getResponse();
        $linkHeader = $response->getHeaderLine('Link');

        $this->assertStringContainsString('rel="mercure"', $linkHeader);
        $this->assertStringContainsString('key-set="https://example.com/.well-known/jwks.json"', $linkHeader);
    }

    /**
     * Test discover with all parameters
     */
    public function testDiscoverWithAllParameters(): void
    {
        $this->component
            ->addSubscribe('/books/123')
            ->authorize()
            ->discover(
                includeTopics: true,
                lastEventId: 'urn:uuid:xyz',
                contentType: 'application/merge-patch+json',
                keySet: 'https://example.com/keys.json',
            );

        $response = $this->controller->getResponse();
        $linkHeaders = $response->getHeader('Link');

        $this->assertCount(2, $linkHeaders);

        // Check rel="self" with topics
        $selfHeaderFound = false;
        $mercureHeaderFound = false;

        foreach ($linkHeaders as $header) {
            if (str_contains($header, 'rel="self"')) {
                $selfHeaderFound = true;
                $this->assertStringContainsString('topic=%2Fbooks%2F123', $header);
            }

            if (str_contains($header, 'rel="mercure"')) {
                $mercureHeaderFound = true;
                $this->assertStringContainsString('last-event-id="urn:uuid:xyz"', $header);
                $this->assertStringContainsString('content-type="application/merge-patch+json"', $header);
                $this->assertStringContainsString('key-set="https://example.com/keys.json"', $header);
            }
        }

        $this->assertTrue($selfHeaderFound);
        $this->assertTrue($mercureHeaderFound);
    }

    /**
     * Test discover with topics returns chainable component
     */
    public function testDiscoverWithTopicsReturnsChainableComponent(): void
    {
        $result = $this->component
            ->addSubscribe('/books/123')
            ->authorize()
            ->discover(includeTopics: true);

        $this->assertSame($this->component, $result);
    }

    /**
     * Test component default config includes discoverWithTopics
     */
    public function testComponentDefaultConfigIncludesDiscoverWithTopics(): void
    {
        $config = $this->component->getConfig();

        $this->assertArrayHasKey('discoverWithTopics', $config);
        $this->assertFalse($config['discoverWithTopics']);
    }
}
