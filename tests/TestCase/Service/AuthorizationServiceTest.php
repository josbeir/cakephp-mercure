<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\Http\Cookie\CookieInterface;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Mercure\Exception\MercureException;
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

        // Configure Mercure for discovery tests
        Configure::write('Mercure', [
            'url' => 'https://mercure.example.com/.well-known/mercure',
            'public_url' => 'https://public.mercure.example.com/.well-known/mercure',
        ]);

        $this->tokenFactory = new FirebaseTokenFactory('test-secret', 'HS256');
        $this->service = new AuthorizationService($this->tokenFactory, [
            'name' => 'testAuth',
            'expires' => null,
            'lifetime' => null,
            'path' => '/test',
            'secure' => true,
            'httponly' => true,
            'samesite' => CookieInterface::SAMESITE_STRICT,
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
     * Test constructor with default config
     */
    public function testConstructorWithDefaults(): void
    {
        $service = new AuthorizationService($this->tokenFactory);
        $config = $service->getCookieConfig();

        $this->assertEquals('mercureAuthorization', $config['name']);
        $this->assertNull($config['expires']);
        $this->assertNull($config['lifetime']);
        $this->assertEquals('/', $config['path']);
        $this->assertFalse($config['secure']);
        $this->assertTrue($config['httponly']);
        $this->assertEquals(CookieInterface::SAMESITE_STRICT, $config['samesite']);
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
        $this->assertEquals(CookieInterface::SAMESITE_STRICT, $config['samesite']);
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
        $this->assertEquals(CookieInterface::SAMESITE_STRICT, $sameSite->value);
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
        $sameSiteValues = ['Strict', 'Lax', 'None'];

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

    /**
     * Test addDiscoveryHeader adds Link header
     */
    public function testAddDiscoveryHeaderAddsLinkHeader(): void
    {
        $response = new Response();
        $result = $this->service->addDiscoveryHeader($response);

        $this->assertInstanceOf(Response::class, $result);
        $linkHeader = $result->getHeaderLine('Link');
        $this->assertStringContainsString('public.mercure.example.com', $linkHeader);
        $this->assertStringContainsString('rel="mercure"', $linkHeader);
        $this->assertEquals('<https://public.mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test addDiscoveryHeader uses public_url when configured
     */
    public function testAddDiscoveryHeaderUsesPublicUrl(): void
    {
        Configure::write('Mercure.public_url', 'https://custom.hub.example.com/hub');

        $response = new Response();
        $result = $this->service->addDiscoveryHeader($response);

        $linkHeader = $result->getHeaderLine('Link');
        $this->assertEquals('<https://custom.hub.example.com/hub>; rel="mercure"', $linkHeader);
    }

    /**
     * Test addDiscoveryHeader falls back to url when public_url not set
     */
    public function testAddDiscoveryHeaderFallsBackToUrl(): void
    {
        Configure::write('Mercure.public_url', null);

        $response = new Response();
        $result = $this->service->addDiscoveryHeader($response);

        $linkHeader = $result->getHeaderLine('Link');
        $this->assertEquals('<https://mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test addDiscoveryHeader throws exception when no URL configured
     */
    public function testAddDiscoveryHeaderThrowsExceptionWhenNoUrlConfigured(): void
    {
        Configure::delete('Mercure');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        $response = new Response();
        $this->service->addDiscoveryHeader($response);
    }

    /**
     * Test addDiscoveryHeader preserves existing headers
     */
    public function testAddDiscoveryHeaderPreservesExistingHeaders(): void
    {
        $response = new Response();
        $response = $response->withHeader('X-Custom-Header', 'value');

        $result = $this->service->addDiscoveryHeader($response);

        $this->assertEquals('value', $result->getHeaderLine('X-Custom-Header'));
        $this->assertNotEmpty($result->getHeaderLine('Link'));
    }

    /**
     * Test addDiscoveryHeader can be combined with multiple Link headers
     */
    public function testAddDiscoveryHeaderCanBeCombinedWithMultipleLinkHeaders(): void
    {
        $response = new Response();
        $response = $response->withAddedHeader('Link', '<https://example.com/other>; rel="other"');

        $result = $this->service->addDiscoveryHeader($response);

        $linkHeaders = $result->getHeader('Link');
        $this->assertCount(2, $linkHeaders);
        $this->assertContains('<https://example.com/other>; rel="other"', $linkHeaders);
        $this->assertContains('<https://public.mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeaders);
    }

    /**
     * Test addDiscoveryHeader preserves response status
     */
    public function testAddDiscoveryHeaderPreservesResponseStatus(): void
    {
        $response = new Response(['status' => 201]);
        $result = $this->service->addDiscoveryHeader($response);

        $this->assertEquals(201, $result->getStatusCode());
    }

    /**
     * Test JWT expiry is set automatically when using lifetime config
     */
    public function testJwtExpirySetAutomaticallyWithLifetime(): void
    {
        $service = new AuthorizationService($this->tokenFactory, [
            'name' => 'testCookie',
            'lifetime' => 3600, // 1 hour
        ]);

        $response = new Response();
        $result = $service->setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testCookie');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);

        // Decode JWT to check exp claim
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertArrayHasKey('exp', $payload);
        $this->assertIsInt($payload['exp']);

        // Exp should be approximately 1 hour from now
        $expectedExp = time() + 3600;
        $this->assertGreaterThan(time(), $payload['exp']);
        $this->assertLessThan($expectedExp + 10, $payload['exp']); // Allow 10 second tolerance
    }

    /**
     * Test JWT expiry defaults to 1 hour when lifetime is 0 (session cookie)
     */
    public function testJwtExpiryDefaultsToOneHourForSessionCookie(): void
    {
        $service = new AuthorizationService($this->tokenFactory, [
            'name' => 'testCookie',
            'lifetime' => 0, // Session cookie
        ]);

        $response = new Response();
        $result = $service->setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testCookie');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);

        // Decode JWT to check exp claim
        $parts = explode('.', $jwt);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertArrayHasKey('exp', $payload);

        // Should be approximately 1 hour from now
        $expectedExp = time() + 3600;
        $this->assertGreaterThan(time(), $payload['exp']);
        $this->assertLessThan($expectedExp + 10, $payload['exp']);
    }

    /**
     * Test JWT expiry uses explicit expires config
     */
    public function testJwtExpiryUsesExplicitExpiresConfig(): void
    {
        $futureTime = '+2 hours';
        $service = new AuthorizationService($this->tokenFactory, [
            'name' => 'testCookie',
            'expires' => $futureTime,
        ]);

        $response = new Response();
        $result = $service->setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testCookie');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);

        // Decode JWT to check exp claim
        $parts = explode('.', $jwt);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertArrayHasKey('exp', $payload);

        // Should be approximately 2 hours from now
        $expectedExp = strtotime($futureTime);
        $this->assertGreaterThan(time() + 3600, $payload['exp']);
        $this->assertLessThan($expectedExp + 10, $payload['exp']);
    }

    /**
     * Test JWT expiry can be overridden via additionalClaims
     */
    public function testJwtExpiryCanBeOverriddenViaAdditionalClaims(): void
    {
        $customExp = time() + 7200; // 2 hours
        $service = new AuthorizationService($this->tokenFactory, [
            'name' => 'testCookie',
            'lifetime' => 3600, // This should be ignored
        ]);

        $response = new Response();
        $result = $service->setCookie($response, ['/feeds/123'], [
            'exp' => $customExp,
        ]);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testCookie');
        $jwt = $cookie->getValue();
        $this->assertIsString($jwt);

        // Decode JWT to check exp claim
        $parts = explode('.', $jwt);
        $payload = json_decode(base64_decode($parts[1]), true);

        $this->assertArrayHasKey('exp', $payload);
        $this->assertEquals($customExp, $payload['exp']);
    }

    /**
     * Test JWT expiry falls back to session.cookie_lifetime
     */
    public function testJwtExpiryFallsBackToSessionCookieLifetime(): void
    {
        // Save original value
        $originalLifetime = ini_get('session.cookie_lifetime');

        // Set custom session lifetime
        ini_set('session.cookie_lifetime', '7200'); // 2 hours

        try {
            $service = new AuthorizationService($this->tokenFactory, [
                'name' => 'testCookie',
                // No lifetime or expires set
            ]);

            $response = new Response();
            $result = $service->setCookie($response, ['/feeds/123']);

            $cookies = $result->getCookieCollection();
            $cookie = $cookies->get('testCookie');
            $jwt = $cookie->getValue();
            $this->assertIsString($jwt);

            // Decode JWT to check exp claim
            $parts = explode('.', $jwt);
            $payload = json_decode(base64_decode($parts[1]), true);

            $this->assertArrayHasKey('exp', $payload);

            // Should be approximately 2 hours from now
            $expectedExp = time() + 7200;
            $this->assertGreaterThan(time() + 3600, $payload['exp']);
            $this->assertLessThan($expectedExp + 10, $payload['exp']);
        } finally {
            // Restore original value
            ini_set('session.cookie_lifetime', $originalLifetime);
        }
    }

    /**
     * Test JWT expiry defaults to 1 hour when session.cookie_lifetime is 0
     */
    public function testJwtExpiryDefaultsToOneHourWhenSessionLifetimeIsZero(): void
    {
        // Save original value
        $originalLifetime = ini_get('session.cookie_lifetime');

        // Set session lifetime to 0
        ini_set('session.cookie_lifetime', '0');

        try {
            $service = new AuthorizationService($this->tokenFactory, [
                'name' => 'testCookie',
                // No lifetime or expires set
            ]);

            $response = new Response();
            $result = $service->setCookie($response, ['/feeds/123']);

            $cookies = $result->getCookieCollection();
            $cookie = $cookies->get('testCookie');
            $jwt = $cookie->getValue();
            $this->assertIsString($jwt);

            // Decode JWT to check exp claim
            $parts = explode('.', $jwt);
            $payload = json_decode(base64_decode($parts[1]), true);

            $this->assertArrayHasKey('exp', $payload);

            // Should be approximately 1 hour from now (default for session cookies)
            $expectedExp = time() + 3600;
            $this->assertGreaterThan(time(), $payload['exp']);
            $this->assertLessThan($expectedExp + 10, $payload['exp']);
        } finally {
            // Restore original value
            ini_set('session.cookie_lifetime', $originalLifetime);
        }
    }

    /**
     * Test addDiscoveryHeader skips preflight requests
     */
    public function testAddDiscoveryHeaderSkipsPreflightRequest(): void
    {
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'OPTIONS'],
        ]);
        $request = $request->withHeader('Access-Control-Request-Method', 'POST');

        $response = new Response();
        $result = $this->service->addDiscoveryHeader($response, $request);

        // Should NOT have Link header
        $this->assertEmpty($result->getHeaderLine('Link'));
    }

    /**
     * Test addDiscoveryHeader adds header for non-preflight OPTIONS request
     */
    public function testAddDiscoveryHeaderAddsHeaderForNonPreflightOptions(): void
    {
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'OPTIONS'],
        ]);
        // No Access-Control-Request-Method header

        $response = new Response();
        $result = $this->service->addDiscoveryHeader($response, $request);

        // Should have Link header
        $linkHeader = $result->getHeaderLine('Link');
        $this->assertNotEmpty($linkHeader);
        $this->assertStringContainsString('public.mercure.example.com', $linkHeader);
        $this->assertStringContainsString('rel="mercure"', $linkHeader);
    }

    /**
     * Test addDiscoveryHeader adds header for GET request
     */
    public function testAddDiscoveryHeaderAddsHeaderForGetRequest(): void
    {
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $request = $request->withHeader('Access-Control-Request-Method', 'POST');

        $response = new Response();
        $result = $this->service->addDiscoveryHeader($response, $request);

        // Should have Link header (not OPTIONS so not preflight)
        $linkHeader = $result->getHeaderLine('Link');
        $this->assertNotEmpty($linkHeader);
        $this->assertStringContainsString('rel="mercure"', $linkHeader);
    }

    /**
     * Test addDiscoveryHeader adds header when request is null (backward compatibility)
     */
    public function testAddDiscoveryHeaderAddsHeaderWhenRequestIsNull(): void
    {
        $response = new Response();
        $result = $this->service->addDiscoveryHeader($response, null);

        // Should have Link header (no preflight check)
        $linkHeader = $result->getHeaderLine('Link');
        $this->assertNotEmpty($linkHeader);
        $this->assertStringContainsString('public.mercure.example.com', $linkHeader);
        $this->assertStringContainsString('rel="mercure"', $linkHeader);
    }

    /**
     * Test addDiscoveryHeader with preflight request but no request passed
     */
    public function testAddDiscoveryHeaderWithoutRequestParameter(): void
    {
        $response = new Response();
        $result = $this->service->addDiscoveryHeader($response);

        // Should have Link header (backward compatibility)
        $linkHeader = $result->getHeaderLine('Link');
        $this->assertNotEmpty($linkHeader);
        $this->assertStringContainsString('rel="mercure"', $linkHeader);
    }
}
