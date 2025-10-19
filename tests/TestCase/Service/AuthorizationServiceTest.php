<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Service;

use Cake\Http\Response;
use Cake\TestSuite\TestCase;
use Mercure\Jwt\FirebaseTokenFactory;
use Mercure\Service\AuthorizationService;

/**
 * AuthorizationService Test Case
 *
 * Tests the AuthorizationService for managing subscriber JWT cookies.
 */
class AuthorizationServiceTest extends TestCase
{
    private FirebaseTokenFactory $tokenFactory;

    private AuthorizationService $service;

    /**
     * Set up test case
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenFactory = new FirebaseTokenFactory('test-secret', 'HS256');
        $this->service = new AuthorizationService($this->tokenFactory, [
            'name' => 'testAuth',
            'expires' => null,
            'path' => '/test',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'strict',
        ]);
    }

    /**
     * Test constructor with default config
     */
    public function testConstructorWithDefaults(): void
    {
        $service = new AuthorizationService($this->tokenFactory);
        $config = $service->getCookieConfig();

        $this->assertEquals('mercureAuthorization', $config['name']);
        $this->assertNull($config['expires']);
        $this->assertEquals('/', $config['path']);
        $this->assertFalse($config['secure']);
        $this->assertTrue($config['httponly']);
        $this->assertEquals('lax', $config['samesite']);
        $this->assertNull($config['domain']);
    }

    /**
     * Test constructor with custom config
     */
    public function testConstructorWithCustomConfig(): void
    {
        $config = $this->service->getCookieConfig();

        $this->assertEquals('testAuth', $config['name']);
        $this->assertEquals('/test', $config['path']);
        $this->assertEquals('/test', $config['path']);
        $this->assertTrue($config['secure']);
        $this->assertTrue($config['httponly']);
        $this->assertEquals('strict', $config['samesite']);
    }

    /**
     * Test setCookie creates cookie with JWT
     */
    public function testSetCookieCreatesJwtCookie(): void
    {
        $response = new Response();
        $subscribe = ['/feeds/123', '/notifications/*'];

        $result = $this->service->setCookie($response, $subscribe);

        $this->assertInstanceOf(Response::class, $result);
        $cookies = $result->getCookieCollection();
        $this->assertTrue($cookies->has('testAuth'));

        $cookie = $cookies->get('testAuth');
        $this->assertNotEmpty($cookie->getValue());
        $this->assertEquals('/test', $cookie->getPath());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $sameSite = $cookie->getSameSite();
        $this->assertNotNull($sameSite);
        $this->assertEquals('Strict', $sameSite->value);
    }

    /**
     * Test setCookie with empty topics
     */
    public function testSetCookieWithEmptyTopics(): void
    {
        $response = new Response();
        $result = $this->service->setCookie($response, []);

        $this->assertInstanceOf(Response::class, $result);
        $cookies = $result->getCookieCollection();
        $this->assertTrue($cookies->has('testAuth'));
    }

    /**
     * Test setCookie with additional claims
     */
    public function testSetCookieWithAdditionalClaims(): void
    {
        $response = new Response();
        $subscribe = ['/feeds/123'];
        $additionalClaims = [
            'aud' => 'my-app',
            'exp' => time() + 3600,
        ];

        $result = $this->service->setCookie($response, $subscribe, $additionalClaims);

        $this->assertInstanceOf(Response::class, $result);
        $cookies = $result->getCookieCollection();
        $this->assertTrue($cookies->has('testAuth'));

        $cookie = $cookies->get('testAuth');
        $jwt = $cookie->getValue();
        $this->assertNotEmpty($jwt);
    }

    /**
     * Test clearCookie removes authorization cookie
     */
    public function testClearCookieRemovesAuthorizationCookie(): void
    {
        $response = new Response();

        // First set a cookie
        $response = $this->service->setCookie($response, ['/feeds/123']);
        $this->assertTrue($response->getCookieCollection()->has('testAuth'));

        // Then clear it
        $result = $this->service->clearCookie($response);

        $cookies = $result->getCookieCollection();
        $this->assertTrue($cookies->has('testAuth'));

        $cookie = $cookies->get('testAuth');
        $this->assertTrue($cookie->isExpired());
    }

    /**
     * Test getCookieName returns configured name
     */
    public function testGetCookieNameReturnsConfiguredName(): void
    {
        $this->assertEquals('testAuth', $this->service->getCookieName());
    }

    /**
     * Test cookie with null expires
     */
    public function testCookieWithNullExpires(): void
    {
        $service = new AuthorizationService($this->tokenFactory, [
            'name' => 'testCookie',
            'expires' => null,
        ]);

        $response = new Response();
        $result = $service->setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testCookie');

        $this->assertNotEmpty($cookie->getValue());
    }

    /**
     * Test cookie with domain
     */
    public function testCookieWithDomain(): void
    {
        $service = new AuthorizationService($this->tokenFactory, [
            'name' => 'domainCookie',
            'domain' => '.example.com',
        ]);

        $response = new Response();
        $result = $service->setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('domainCookie');

        $this->assertEquals('.example.com', $cookie->getDomain());
    }

    /**
     * Test cookie with different sameSite values
     */
    public function testCookieWithDifferentSameSiteValues(): void
    {
        $sameSiteValues = ['strict', 'lax', 'none'];

        foreach ($sameSiteValues as $sameSite) {
            $service = new AuthorizationService($this->tokenFactory, [
                'name' => 'testCookie',
                'samesite' => $sameSite,
            ]);

            $response = new Response();
            $result = $service->setCookie($response, ['/feeds/123']);

            $cookies = $result->getCookieCollection();
            $cookie = $cookies->get('testCookie');

            $sameSiteEnum = $cookie->getSameSite();
            $this->assertNotNull($sameSiteEnum);
            $this->assertEquals(ucfirst($sameSite), $sameSiteEnum->value);
        }
    }

    /**
     * Test cookie is not httpOnly when configured
     */
    public function testCookieNotHttpOnlyWhenConfigured(): void
    {
        $service = new AuthorizationService($this->tokenFactory, [
            'name' => 'testCookie',
            'httponly' => false,
        ]);

        $response = new Response();
        $result = $service->setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testCookie');

        $this->assertFalse($cookie->isHttpOnly());
    }

    /**
     * Test cookie is not secure when configured
     */
    public function testCookieNotSecureWhenConfigured(): void
    {
        $service = new AuthorizationService($this->tokenFactory, [
            'name' => 'httpCookie',
            'secure' => false,
        ]);

        $response = new Response();
        $result = $service->setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('httpCookie');

        $this->assertFalse($cookie->isSecure());
    }

    /**
     * Test getCookieConfig returns all configuration
     */
    public function testGetCookieConfigReturnsAllConfiguration(): void
    {
        $config = $this->service->getCookieConfig();

        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('expires', $config);
        $this->assertArrayHasKey('path', $config);
        $this->assertArrayHasKey('secure', $config);
        $this->assertArrayHasKey('httponly', $config);
        $this->assertArrayHasKey('samesite', $config);
        $this->assertArrayHasKey('domain', $config);
    }

    /**
     * Test cookie preserves response
     */
    public function testCookiePreservesResponse(): void
    {
        $response = new Response(['status' => 201]);
        $result = $this->service->setCookie($response, ['/feeds/123']);

        $this->assertEquals(201, $result->getStatusCode());
    }

    /**
     * Test clearCookie preserves response
     */
    public function testClearCookiePreservesResponse(): void
    {
        $response = new Response(['status' => 204]);
        $result = $this->service->clearCookie($response);

        $this->assertEquals(204, $result->getStatusCode());
    }
}
