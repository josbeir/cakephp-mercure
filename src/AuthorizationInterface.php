<?php
declare(strict_types=1);

namespace Mercure;

use Cake\Http\Response;

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
     * @param \Cake\Http\Response $response The response object to modify
     * @return \Cake\Http\Response Modified response with discovery header
     * @throws \Mercure\Exception\MercureException
     */
    public function addDiscoveryHeader(Response $response): Response;
}
