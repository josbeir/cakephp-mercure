<?php
declare(strict_types=1);

namespace Mercure\Service;

use Cake\Http\Response;
use Cake\Http\ServerRequest;

/**
 * Authorization Interface
 *
 * Defines the contract for managing JWT cookies for authenticating subscribers
 * to private Mercure topics.
 */
interface AuthorizationInterface
{
    /**
     * Create and set the authorization cookie on the response
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @param array<string> $subscribe Array of topics the subscriber can access
     * @param array<string, mixed> $additionalClaims Additional JWT claims to include
     * @return \Cake\Http\Response Modified response with cookie set
     * @throws \Mercure\Exception\MercureException
     */
    public function setCookie(
        Response $response,
        array $subscribe = [],
        array $additionalClaims = [],
    ): Response;

    /**
     * Clear the authorization cookie from the response
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @return \Cake\Http\Response Modified response with cookie cleared
     */
    public function clearCookie(Response $response): Response;

    /**
     * Get cookie configuration
     *
     * @return array<string, mixed>
     */
    public function getCookieConfig(): array;

    /**
     * Get the cookie name
     */
    public function getCookieName(): string;

    /**
     * Add the Mercure discovery header to the response
     *
     * Adds a Link header with rel="mercure" to advertise the Mercure hub URL.
     * This allows clients to discover the hub endpoint automatically.
     *
     * Skips CORS preflight requests to prevent conflicts with CORS middleware.
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @param \Cake\Http\ServerRequest|null $request Optional request to check for preflight
     * @return \Cake\Http\Response Modified response with discovery header
     * @throws \Mercure\Exception\MercureException
     */
    public function addDiscoveryHeader(Response $response, ?ServerRequest $request = null): Response;
}
