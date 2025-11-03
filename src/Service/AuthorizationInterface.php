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
     * Add the Mercure discovery headers to the response
     *
     * Adds Link headers for Mercure discovery according to the Mercure specification:
     * - rel="mercure": The hub URL for subscriptions (required)
     * - rel="self": The canonical topic URL for this resource (optional)
     *
     * The rel="mercure" link may include optional attributes:
     * - last-event-id: The last event ID for reconciliation
     * - content-type: Content type hint for updates (e.g., for partial updates)
     * - key-set: URL to JWK key set for encrypted updates
     *
     * Skips CORS preflight requests to prevent conflicts with CORS middleware.
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @param \Cake\Http\ServerRequest|null $request Optional request to check for preflight
     * @param string|null $selfUrl Canonical topic URL for rel="self" link
     * @param string|null $lastEventId Last event ID for state reconciliation
     * @param string|null $contentType Content type of updates
     * @param string|null $keySet URL to JWK key set for encryption
     * @return \Cake\Http\Response Modified response with discovery headers
     * @throws \Mercure\Exception\MercureException
     */
    public function addDiscoveryHeader(
        Response $response,
        ?ServerRequest $request = null,
        ?string $selfUrl = null,
        ?string $lastEventId = null,
        ?string $contentType = null,
        ?string $keySet = null,
    ): Response;
}
