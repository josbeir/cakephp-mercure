<?php
declare(strict_types=1);

namespace Mercure;

use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Mercure\Exception\MercureException;
use Mercure\Internal\ConfigurationHelper;
use Mercure\Jwt\FirebaseTokenFactory;
use Mercure\Jwt\TokenFactoryInterface;
use Mercure\Service\AuthorizationInterface;
use Mercure\Service\AuthorizationService;

/**
 * Authorization Facade
 *
 * Provides a static accessor pattern for the AuthorizationService.
 * Manages JWT cookies for authenticating subscribers to private Mercure topics.
 *
 * Example usage:
 * ```
 * // In a controller
 * $response = Authorization::setCookie($response, ['/feeds/123', '/notifications/*']);
 *
 * // Clear authorization
 * $response = Authorization::clearCookie($response);
 * ```
 */
class Authorization
{
    private static ?AuthorizationInterface $instance = null;

    /**
     * Create the AuthorizationService instance
     *
     * @throws \Mercure\Exception\MercureException
     */
    public static function create(): AuthorizationInterface
    {
        if (!self::$instance instanceof AuthorizationInterface) {
            $jwtConfig = ConfigurationHelper::getJwtConfig();
            $cookieConfig = ConfigurationHelper::getCookieConfig();

            // Create token factory for subscriber tokens
            $secret = $jwtConfig['secret'] ?? '';
            if (empty($secret)) {
                throw new MercureException('JWT secret is not configured for Authorization');
            }

            $algorithm = $jwtConfig['algorithm'] ?? 'HS256';

            // Check for custom factory
            if (isset($jwtConfig['factory'])) {
                $factoryClass = $jwtConfig['factory'];
                if (!class_exists($factoryClass)) {
                    throw new MercureException(sprintf("Token factory class '%s' not found", $factoryClass));
                }

                $factory = new $factoryClass($secret, $algorithm);
                if (!$factory instanceof TokenFactoryInterface) {
                    throw new MercureException('Token factory must implement TokenFactoryInterface');
                }
            } else {
                // Use default Firebase factory
                $factory = new FirebaseTokenFactory($secret, $algorithm);
            }

            self::$instance = new AuthorizationService($factory, $cookieConfig);
        }

        return self::$instance;
    }

    /**
     * Set a custom AuthorizationService instance
     *
     * Useful for testing or when you want to use a different configuration.
     *
     * @param \Mercure\Service\AuthorizationInterface $service Authorization service instance
     */
    public static function setInstance(AuthorizationInterface $service): void
    {
        self::$instance = $service;
    }

    /**
     * Clear the singleton instance
     */
    public static function clear(): void
    {
        self::$instance = null;
    }

    /**
     * Create and set the authorization cookie on the response
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @param array<string> $subscribe Array of topics the subscriber can access
     * @param array<string, mixed> $additionalClaims Additional JWT claims to include
     * @return \Cake\Http\Response Modified response with cookie set
     * @throws \Mercure\Exception\MercureException
     */
    public static function setCookie(
        Response $response,
        array $subscribe = [],
        array $additionalClaims = [],
    ): Response {
        return self::create()->setCookie($response, $subscribe, $additionalClaims);
    }

    /**
     * Clear the authorization cookie from the response
     *
     * @param \Cake\Http\Response $response The response object to modify
     * @return \Cake\Http\Response Modified response with cookie cleared
     * @throws \Mercure\Exception\MercureException
     */
    public static function clearCookie(Response $response): Response
    {
        return self::create()->clearCookie($response);
    }

    /**
     * Get the cookie name
     */
    public static function getCookieName(): string
    {
        return self::create()->getCookieName();
    }

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
     * Example usage:
     * ```
     * // Basic discovery
     * $response = Authorization::addDiscoveryHeader($response);
     *
     * // With canonical topic URL
     * $response = Authorization::addDiscoveryHeader(
     *     response: $response,
     *     selfUrl: '/books/123'
     * );
     *
     * // With all parameters
     * $response = Authorization::addDiscoveryHeader(
     *     response: $response,
     *     selfUrl: '/books/123.jsonld',
     *     lastEventId: 'urn:uuid:abc-123',
     *     contentType: 'application/ld+json',
     *     keySet: 'https://example.com/.well-known/jwks.json'
     * );
     * ```
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
    public static function addDiscoveryHeader(
        Response $response,
        ?ServerRequest $request = null,
        ?string $selfUrl = null,
        ?string $lastEventId = null,
        ?string $contentType = null,
        ?string $keySet = null,
    ): Response {
        return self::create()->addDiscoveryHeader(
            $response,
            $request,
            $selfUrl,
            $lastEventId,
            $contentType,
            $keySet,
        );
    }
}
