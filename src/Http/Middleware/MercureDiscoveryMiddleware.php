<?php
declare(strict_types=1);

namespace Mercure\Http\Middleware;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Mercure\Authorization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Mercure Discovery Middleware
 *
 * Automatically adds the Mercure discovery Link header to all responses.
 * This allows clients to discover the Mercure hub URL via the Link header
 * with rel="mercure".
 *
 * Usage in Application.php:
 * ```
 * public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
 * {
 *     $middlewareQueue
 *         // ... other middleware
 *         ->add(new \Mercure\Http\Middleware\MercureDiscoveryMiddleware());
 *
 *     return $middlewareQueue;
 * }
 * ```
 *
 * The middleware adds a Link header like:
 * Link: <https://mercure.example.com/.well-known/mercure>; rel="mercure"
 */
class MercureDiscoveryMiddleware implements MiddlewareInterface
{
    /**
     * Process the request and add discovery header to response
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The request handler
     * @return \Psr\Http\Message\ResponseInterface A response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // Add discovery header if response and request are CakePHP instances
        if ($response instanceof Response && $request instanceof ServerRequest) {
            $response = Authorization::addDiscoveryHeader($response, $request);
        }

        return $response;
    }
}
