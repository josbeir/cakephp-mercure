<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\TestSuite\TestCase;
use Mercure\Authorization;
use Mercure\AuthorizationInterface;
use Mercure\Exception\MercureException;
use Mercure\Jwt\FirebaseTokenFactory;
use Mercure\Service\AuthorizationService;

/**
 * Authorization Test Case
 *
 * Tests the Authorization facade for managing subscriber JWT cookies.
 */
class AuthorizationTest extends TestCase
{
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
                'name' => 'testAuth',
                'expires' => null,
                'path' => '/test',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'strict',
            ],
        ]);
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
     * Test getInstance creates instance from configuration
     */
    public function testGetInstanceCreatesInstanceFromConfig(): void
    {
        $service = Authorization::create();

        $this->assertInstanceOf(AuthorizationInterface::class, $service);
        $config = $service->getCookieConfig();
        $this->assertEquals('testAuth', $config['name']);
        $this->assertEquals('/test', $config['path']);
    }

    /**
     * Test getInstance returns same instance (singleton)
     */
    public function testGetInstanceReturnsSameInstance(): void
    {
        $instance1 = Authorization::create();
        $instance2 = Authorization::create();

        $this->assertSame($instance1, $instance2);
    }

    /**
     * Test getInstance with default cookie config
     */
    public function testGetInstanceWithDefaultCookieConfig(): void
    {
        Configure::write('Mercure.cookie', []);

        $service = Authorization::create();
        $config = $service->getCookieConfig();

        $this->assertEquals('mercureAuthorization', $config['name']);
        $this->assertNull($config['expires']);
        $this->assertEquals('/', $config['path']);
        $this->assertFalse($config['secure']);
        $this->assertTrue($config['httponly']);
        $this->assertEquals('lax', $config['samesite']);
    }

    /**
     * Test getInstance throws exception when JWT secret missing
     */
    public function testGetInstanceThrowsExceptionWhenJwtSecretMissing(): void
    {
        Configure::write('Mercure.jwt.secret', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('JWT secret is not configured');

        Authorization::create();
    }

    /**
     * Test getInstance with custom token factory
     */
    public function testGetInstanceWithCustomTokenFactory(): void
    {
        Configure::write('Mercure.jwt.factory', FirebaseTokenFactory::class);

        $service = Authorization::create();

        $this->assertInstanceOf(AuthorizationInterface::class, $service);
    }

    /**
     * Test getInstance throws exception with non-existent factory class
     */
    public function testGetInstanceThrowsExceptionWithNonExistentFactory(): void
    {
        Configure::write('Mercure.jwt.factory', 'NonExistent\FactoryClass');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Token factory class');

        Authorization::create();
    }

    /**
     * Test setInstance allows custom instance
     */
    public function testSetInstanceAllowsCustomInstance(): void
    {
        $tokenFactory = new FirebaseTokenFactory('custom-secret', 'HS256');
        $customService = new AuthorizationService($tokenFactory, ['name' => 'customCookie']);

        Authorization::setInstance($customService);

        $this->assertSame($customService, Authorization::create());
        $this->assertEquals('customCookie', Authorization::getCookieName());
    }

    /**
     * Test clear resets instance
     */
    public function testClearResetsInstance(): void
    {
        $instance1 = Authorization::create();
        Authorization::clear();
        $instance2 = Authorization::create();

        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * Test setCookie creates cookie with JWT (static method)
     */
    public function testSetCookieCreatesJwtCookie(): void
    {
        $response = new Response();
        $subscribe = ['/feeds/123', '/notifications/*'];

        $result = Authorization::setCookie($response, $subscribe);

        $this->assertInstanceOf(Response::class, $result);
        $cookies = $result->getCookieCollection();
        $this->assertTrue($cookies->has('testAuth'));

        $cookie = $cookies->get('testAuth');
        $this->assertNotEmpty($cookie->getValue());
        $this->assertEquals('/test', $cookie->getPath());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        // Note: samesite 'strict' gets normalized to 'Strict' by CakePHP
        $sameSite = $cookie->getSameSite();
        $this->assertNotNull($sameSite);
        $this->assertEquals('Strict', $sameSite->value);
    }

    /**
     * Test setCookie with empty subscribe topics
     */
    public function testSetCookieWithEmptyTopics(): void
    {
        $response = new Response();
        $result = Authorization::setCookie($response, []);

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

        $result = Authorization::setCookie($response, $subscribe, $additionalClaims);

        $this->assertInstanceOf(Response::class, $result);
        $cookies = $result->getCookieCollection();
        $this->assertTrue($cookies->has('testAuth'));

        $cookie = $cookies->get('testAuth');
        $jwt = $cookie->getValue();
        $this->assertNotEmpty($jwt);
    }

    /**
     * Test clearCookie removes authorization cookie (static method)
     */
    public function testClearCookieRemovesAuthorizationCookie(): void
    {
        $response = new Response();

        // First set a cookie
        $response = Authorization::setCookie($response, ['/feeds/123']);
        $this->assertTrue($response->getCookieCollection()->has('testAuth'));

        // Then clear it
        $result = Authorization::clearCookie($response);

        $cookies = $result->getCookieCollection();
        $this->assertTrue($cookies->has('testAuth'));

        $cookie = $cookies->get('testAuth');
        $this->assertTrue($cookie->isExpired());
    }

    /**
     * Test getCookieName returns configured name (static method)
     */
    public function testGetCookieNameReturnsConfiguredName(): void
    {
        $this->assertEquals('testAuth', Authorization::getCookieName());
    }

    /**
     * Test getHubUrl returns configured URL
     */
    public function testGetHubUrlReturnsConfiguredUrl(): void
    {
        $hubUrl = Authorization::getHubUrl();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $hubUrl);
    }

    /**
     * Test getHubUrl throws exception when not configured
     */
    public function testGetHubUrlThrowsExceptionWhenNotConfigured(): void
    {
        Configure::write('Mercure.url', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        Authorization::getHubUrl();
    }

    /**
     * Test cookie with session lifetime (0)
     */
    public function testCookieWithNullExpires(): void
    {
        Configure::write('Mercure.cookie.expires', null);
        Authorization::clear();

        $response = new Response();
        $result = Authorization::setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testAuth');

        $this->assertNotEmpty($cookie->getValue());
    }

    /**
     * Test cookie with domain
     */
    public function testCookieWithDomain(): void
    {
        Configure::write('Mercure.cookie.domain', '.example.com');
        Authorization::clear();

        $response = new Response();
        $result = Authorization::setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testAuth');

        $this->assertEquals('.example.com', $cookie->getDomain());
    }

    /**
     * Test cookie with different sameSite values
     */
    public function testCookieWithDifferentSameSiteValues(): void
    {
        $sameSiteValues = ['strict', 'lax', 'none'];

        foreach ($sameSiteValues as $sameSite) {
            Configure::write('Mercure.cookie.samesite', $sameSite);
            Authorization::clear();

            $response = new Response();
            $result = Authorization::setCookie($response, ['/feeds/123']);

            $cookies = $result->getCookieCollection();
            $cookie = $cookies->get('testAuth');

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
        Configure::write('Mercure.cookie.httponly', false);
        Authorization::clear();

        $response = new Response();
        $result = Authorization::setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testAuth');

        $this->assertFalse($cookie->isHttpOnly());
    }

    /**
     * Test cookie is not secure when configured
     */
    public function testCookieNotSecureWhenConfigured(): void
    {
        Configure::write('Mercure.cookie.secure', false);
        Authorization::clear();

        $response = new Response();
        $result = Authorization::setCookie($response, ['/feeds/123']);

        $cookies = $result->getCookieCollection();
        $cookie = $cookies->get('testAuth');

        $this->assertFalse($cookie->isSecure());
    }

    /**
     * Test getCookieConfig returns all configuration
     */
    public function testGetCookieConfigReturnsAllConfiguration(): void
    {
        $service = Authorization::create();
        $config = $service->getCookieConfig();

        $this->assertArrayHasKey('name', $config);
        $this->assertArrayHasKey('expires', $config);
        $this->assertArrayHasKey('path', $config);
        $this->assertArrayHasKey('secure', $config);
        $this->assertArrayHasKey('httponly', $config);
        $this->assertArrayHasKey('samesite', $config);
        $this->assertArrayHasKey('domain', $config);
    }

    /**
     * Test instance methods work correctly
     */
    public function testInstanceMethodsWorkCorrectly(): void
    {
        $service = Authorization::create();
        $response = new Response();

        $result = $service->setCookie($response, ['/feeds/123']);
        $this->assertTrue($result->getCookieCollection()->has('testAuth'));

        $result = $service->clearCookie($result);
        $cookie = $result->getCookieCollection()->get('testAuth');
        $this->assertTrue($cookie->isExpired());
    }

    /**
     * Test getPublicUrl returns public_url when configured
     */
    public function testGetPublicUrlReturnsPublicUrlWhenConfigured(): void
    {
        Configure::write('Mercure.url', 'http://internal.mercure:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://mercure.example.com/.well-known/mercure');

        $publicUrl = Authorization::getPublicUrl();
        $this->assertEquals('https://mercure.example.com/.well-known/mercure', $publicUrl);
    }

    /**
     * Test getPublicUrl falls back to url when public_url not set
     */
    public function testGetPublicUrlFallsBackToUrlWhenPublicUrlNotSet(): void
    {
        Configure::write('Mercure.url', 'http://localhost:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', null);

        $publicUrl = Authorization::getPublicUrl();
        $this->assertEquals('http://localhost:3000/.well-known/mercure', $publicUrl);
    }

    /**
     * Test getPublicUrl throws exception when neither url nor public_url set
     */
    public function testGetPublicUrlThrowsExceptionWhenNotConfigured(): void
    {
        Configure::write('Mercure.url', '');
        Configure::write('Mercure.public_url', '');

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        Authorization::getPublicUrl();
    }
}
