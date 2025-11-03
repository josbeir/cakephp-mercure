<?php
declare(strict_types=1);

namespace Mercure\Test\TestCase\Http\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Mercure\Authorization;
use Mercure\Exception\MercureException;
use Mercure\Http\Middleware\MercureDiscoveryMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MercureDiscoveryMiddleware Test Case
 *
 * Tests the middleware that automatically adds Mercure discovery headers.
 */
class MercureDiscoveryMiddlewareTest extends TestCase
{
    private MercureDiscoveryMiddleware $middleware;

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
        ]);

        $this->middleware = new MercureDiscoveryMiddleware();
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
     * Test middleware adds discovery header to response
     */
    public function testMiddlewareAddsDiscoveryHeaderToResponse(): void
    {
        $request = new ServerRequest();
        $response = new Response();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertInstanceOf(Response::class, $result);

        $linkHeader = $result->getHeaderLine('Link');
        $this->assertEquals('<https://public.mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test middleware uses public_url when configured
     */
    public function testMiddlewareUsesPublicUrl(): void
    {
        Configure::write('Mercure.url', 'http://internal:3000/.well-known/mercure');
        Configure::write('Mercure.public_url', 'https://custom.hub.example.com/hub');
        Authorization::clear();

        $request = new ServerRequest();
        $response = new Response();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        $linkHeader = $result->getHeaderLine('Link');
        $this->assertEquals('<https://custom.hub.example.com/hub>; rel="mercure"', $linkHeader);
    }

    /**
     * Test middleware falls back to url when public_url not set
     */
    public function testMiddlewareFallsBackToUrl(): void
    {
        Configure::write('Mercure.url', 'https://fallback.example.com/.well-known/mercure');
        Configure::write('Mercure.public_url');
        Authorization::clear();

        $request = new ServerRequest();
        $response = new Response();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        $linkHeader = $result->getHeaderLine('Link');
        $this->assertEquals('<https://fallback.example.com/.well-known/mercure>; rel="mercure"', $linkHeader);
    }

    /**
     * Test middleware throws exception when no URL configured
     */
    public function testMiddlewareThrowsExceptionWhenNoUrlConfigured(): void
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

        $request = new ServerRequest();
        $response = new Response();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('Mercure hub URL is not configured');

        $this->middleware->process($request, $handler);
    }

    /**
     * Test middleware preserves existing headers
     */
    public function testMiddlewarePreservesExistingHeaders(): void
    {
        $request = new ServerRequest();
        $response = new Response();
        $response = $response->withHeader('X-Custom-Header', 'custom-value');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        $this->assertEquals('custom-value', $result->getHeaderLine('X-Custom-Header'));
        $this->assertNotEmpty($result->getHeaderLine('Link'));
    }

    /**
     * Test middleware can combine with existing Link headers
     */
    public function testMiddlewareCanCombineWithExistingLinkHeaders(): void
    {
        $request = new ServerRequest();
        $response = new Response();
        $response = $response->withAddedHeader('Link', '<https://example.com/other>; rel="other"');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        $linkHeaders = $result->getHeader('Link');
        $this->assertCount(2, $linkHeaders);
        $this->assertContains('<https://example.com/other>; rel="other"', $linkHeaders);
        $this->assertContains('<https://public.mercure.example.com/.well-known/mercure>; rel="mercure"', $linkHeaders);
    }

    /**
     * Test middleware preserves response status code
     */
    public function testMiddlewarePreservesResponseStatusCode(): void
    {
        $request = new ServerRequest();
        $response = new Response(['status' => 201]);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        $this->assertEquals(201, $result->getStatusCode());
        $this->assertNotEmpty($result->getHeaderLine('Link'));
    }

    /**
     * Test middleware preserves response body
     */
    public function testMiddlewarePreservesResponseBody(): void
    {
        $request = new ServerRequest();
        $response = new Response();
        $response->getBody()->write('Test response body');

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        $this->assertEquals('Test response body', (string)$result->getBody());
        $this->assertNotEmpty($result->getHeaderLine('Link'));
    }

    /**
     * Test middleware works with different request methods
     */
    public function testMiddlewareWorksWithDifferentRequestMethods(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            Authorization::clear();

            $request = new ServerRequest(['environment' => ['REQUEST_METHOD' => $method]]);
            $response = new Response();

            $handler = $this->createMock(RequestHandlerInterface::class);
            $handler->method('handle')->willReturn($response);

            $result = $this->middleware->process($request, $handler);

            $linkHeader = $result->getHeaderLine('Link');
            $this->assertStringContainsString('rel="mercure"', $linkHeader, 'Failed for method: ' . $method);
        }
    }

    /**
     * Test middleware handles non-CakePHP Response objects gracefully
     */
    public function testMiddlewareHandlesNonCakePHPResponseGracefully(): void
    {
        $request = new ServerRequest();

        // Create a mock PSR-7 ResponseInterface that is NOT a CakePHP Response
        $response = $this->createMock(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        // Should return the response unchanged since it's not a CakePHP Response
        $this->assertSame($response, $result);
    }

    /**
     * Test middleware can be instantiated multiple times
     */
    public function testMiddlewareCanBeInstantiatedMultipleTimes(): void
    {
        $middleware1 = new MercureDiscoveryMiddleware();
        $middleware2 = new MercureDiscoveryMiddleware();

        $this->assertInstanceOf(MercureDiscoveryMiddleware::class, $middleware1);
        $this->assertInstanceOf(MercureDiscoveryMiddleware::class, $middleware2);
        $this->assertNotSame($middleware1, $middleware2);
    }

    /**
     * Test middleware with cookies in response
     */
    public function testMiddlewareWithCookiesInResponse(): void
    {
        $request = new ServerRequest();
        $response = new Response();
        $response = Authorization::setCookie($response, ['/feeds/123']);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        $result = $this->middleware->process($request, $handler);

        // Result should be a CakePHP Response
        $this->assertInstanceOf(Response::class, $result);

        // Should preserve cookies
        $cookies = $result->getCookieCollection();
        $this->assertGreaterThan(0, $cookies->count());

        // Should add Link header
        $linkHeader = $result->getHeaderLine('Link');
        $this->assertStringContainsString('rel="mercure"', $linkHeader);
    }
}
